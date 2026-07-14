## Why

### 1. Courier booking is dead, and the two admin pages contradict each other

The owner entered real SteadFast keys and the app tells them two different things at the same time:

| Page | Says |
|---|---|
| Settings → Integrations → SteadFast | **`Configured`** ✅ |
| Order detail → Book courier | *"Steadfast — needs credentials"* ❌ |

Both are reading the truth — just **two different truths**. Courier credentials live in **two stores**:

- `couriers.config` (`encrypted:array`) — written by **Shipping → Couriers**.
- the legacy `steadfast` **settings** group — written by **Settings → Integrations** (`IntegrationSettingController::updateSteadfast`).

The booking gate is `Courier::isConfigured()` (`app/Models/Courier.php:125-138`), which reads **only** `couriers.config`. So it returns `false`, and:

- `CourierManager::canBookViaApi()` → false (`CourierManager.php:48-53`)
- `ShipmentController::store` refuses to book (`ShipmentController.php:43-50`)
- the order page disables the Book button (`OrderController.php:96` → `orders/show.tsx`)
- `OrderObserver` silently skips the auto-push on confirm

Yet the driver factory that makes the *actual* API call **has always read both** (`RepositoryServiceProvider.php:85-86`):

```php
$courier->credential('api_key') ?? $settings->get('steadfast', 'api_key')
```

So the keys would work. The door is simply bolted from the inside. And `docs/PAYMENT-COURIER-HANDOVER.md:77` sends the owner to the page that trips the bolt.

### 2. When the courier API *does* fail, nobody can tell why

- `SteadFastCourier` (`:26-48`) never checks `$response->successful()`, never logs the body, and sets **no timeout**. A 401 (bad key / IP not whitelisted), a 422 (duplicate `invoice` — `order_no` is unique per merchant, so a re-book after a partial failure fails) and a network timeout all surface as the same useless `RuntimeException('Failed to create SteadFast consignment.')`.
- `ShipmentController::store` has **no try/catch** → that exception becomes an Inertia 500 screen.
- `CourierLocationController::guard()` (`:80-90`) catches `Throwable` and **never calls `report()`** → RedX/Pathao failures are invisible in the error log.
- Nothing catches `DecryptException`. An APP_KEY mismatch turns the Integrations page and the Couriers list into hard 500s (`SettingsService.php:60`, the `encrypted:array` cast at `Courier.php:76`).
- **There is no way to test a credential.** The owner asked, reasonably: *"how do I verify the keys are properly added?"* Today: you can't. You place a real order and hope.

### 3. The orders list can't filter by pending reason, and there's nowhere to write an admin note

- `pending_reason` is **displayed** in the list (`OrderController.php:42`) but is not in the filter whitelist (`OrderRepository.php:34` = `['status','payment_status']`), so `?pending_reason=…` is silently dropped by `ListQuery`. There is also no inline way to change it from the list — you must open each order.
- There is **no admin note anywhere**. `orders.notes` is the *customer's* checkout note (read-only). `pending_note` cannot serve: it is gated to `status = pending` and is **auto-nulled on any forward transition** (`OrderController.php:189-191`, `:237-239`).

### 4. Customer details and the delivery address are frozen after checkout

`address` is a read-only `<p>` on the order page, and the customer's name / mobile / email cannot be corrected at all. The single most common shop operation — *"the customer gave the wrong address / a typo'd phone number, fix it before booking the courier"* — is impossible.

## What Changes

### Courier
- **One credential truth.** `Courier::credential()` falls back to the legacy `steadfast` settings group, so `isConfigured()` finally agrees with the driver factory that has always used that fallback. The owner's existing keys start working **without re-entering them**.
- **A data migration** copies the legacy `steadfast` settings into `couriers.config`, making `couriers.config` the single store going forward. The fallback stays as a safety net.
- **`Courier::safeConfig()`** swallows a `DecryptException` into an empty config + `report()`, so an APP_KEY mismatch degrades to "not configured" instead of a 500. `SettingsService` does the same.
- **Loud adapters.** Every courier HTTP call gets a `timeout` + `connectTimeout`, checks the HTTP status, and throws a **`CourierException`** carrying the provider's status code and (redacted) response body.
- **`ShipmentController::store` catches it** and flashes the provider's real message instead of 500-ing. `CourierLocationController` calls `report()` instead of swallowing.
- **A "Test connection" button** on the Couriers page. It makes a real, read-only call to the provider (SteadFast `GET /get_balance`, RedX `GET /areas`, Pathao token issue) and shows the actual result — *"Connected. Balance ৳1,240"* or *"SteadFast rejected the credentials (HTTP 401)"*. **This is the answer to "how do I verify the keys?"**
- Changing a Pathao courier's credentials **busts its cached OAuth token** (it was surviving up to ~5 days, so a key change kept 401-ing).

### Orders list
- `pending_reason` becomes a **filter** (and is included in "select all matching" so bulk actions respect it).
- An **inline pending-reason select** per pending row, mirroring the existing inline status control. Choosing "Other" reveals the required note inline.
- A **Notes column** showing the admin note.

### Admin note
- `orders.admin_note` (text, nullable) + `PUT /admin/orders/{order}/note` (`orders.manage`) + a card on the order page. Audit-logged, never wiped by a status change — unlike `pending_note`.

### Customer + address editing
- `PUT /admin/orders/{order}/customer` (`orders.manage`) edits the customer's **name, mobile, email** and the order's **delivery address** and **shipping zone**.
- Changing the zone **recomputes shipping and the total** through a new `ShippingCalculator` service extracted from `PlaceOrder` — so the pay link and the invoice follow automatically, and the PHP formula stops being duplicated.
- Guards: the mobile stays unique; a zone change that would drop the total below what the customer has already paid is rejected; a paid order cannot have its total changed; if a consignment is already booked, the edit is allowed but the admin is warned that the courier still holds the old address.

## Non-goals

- **Post-order admin discount** (`orders.discount`) — the next change. The `ShippingCalculator` + `RecalculateOrderTotals` extraction here is deliberately the groundwork for it.
- **Admin "create order"** page — a later change.
- **Editing order line items / quantities** — out of scope; the totals invariant gets much harder and it is not what was asked for.
- Removing the SteadFast card from Settings → Integrations. Left in place for now (it keeps working); a later cleanup can retire it once `couriers.config` is confirmed populated everywhere.

## Capabilities

### New Capabilities
- `courier-credentials`: one resolved credential source per courier, a connection test, and provider errors that reach the admin instead of a 500.
- `order-admin-editing`: admin note, customer/address/zone correction with a server-side total recompute.

### Modified Capabilities
- `order-admin-listing`: pending-reason filter + inline set + an admin-note column.

## Impact

- **DB**: `orders.admin_note` (text nullable); a data migration copying `settings[steadfast.*]` → `couriers.config`.
- **Backend**: `Courier` (safeConfig + credential fallback), `SettingsService` (DecryptException guard), new `CourierException` + `TestsConnection` interface, `SteadFastCourier` / `RedxCourier` / `PathaoCourier` (timeouts + status checks + test), `CourierManager`, `RepositoryServiceProvider`, `CourierUiController` (+`test`), `ShipmentController` (try/catch), `CourierLocationController` (report), `OrderController` (+`updateNote`, `updateCustomer`, index payload), `OrderRepository` (filter whitelist), new `ShippingCalculator` + `RecalculateOrderTotals`, `PlaceOrder` (uses the calculator), new FormRequests, `routes/web.php`.
- **Admin UI**: `orders/index.tsx` (filter + inline reason + notes column), `orders/show.tsx` (admin note card, editable customer/address card), `shipping/couriers/index.tsx` (Test connection).
- **Storefront**: none.
- **Risk**: the credential fallback is additive (a courier that is configured today stays configured). The shipping recompute is the sharp edge — it is guarded, tested, and reuses the exact formula `PlaceOrder` already ships with.
