# Tasks — courier-credentials-and-order-ops

## Phase 1 — Courier: one credential truth (RED first)
- [ ] 1.1 RED `tests/Feature/Courier/CourierCredentialsTest.php` — a Steadfast courier with an empty `config` but keys in the legacy `steadfast` settings group reports `isConfigured() === true`. **Fails today — this is the owner's bug.**
- [ ] 1.2 `Courier::safeConfig()` — `DecryptException` → `report()` + `[]` (never a 500)
- [ ] 1.3 `Courier::credential()` falls back to the legacy `steadfast` settings group
- [ ] 1.4 Remove the now-duplicated `?? $settings->get('steadfast', …)` from `RepositoryServiceProvider`
- [ ] 1.5 `SettingsService::decrypt()` guards `DecryptException` the same way
- [ ] 1.6 Data migration: copy `settings[steadfast.*]` into `couriers.config` for Steadfast couriers missing them (idempotent, wrapped in try/catch so a bad APP_KEY can't block the deploy)
- [ ] 1.7 Tests green: config-only · legacy-only · both (config wins) · neither · undecryptable config

## Phase 2 — Courier: loud adapters
- [ ] 2.1 `App\Support\Courier\CourierException` — `http()` / `missingCredentials()` / `unreachable()`
- [ ] 2.2 `SteadFastCourier` — `timeout(20)`, `connectTimeout(10)`, `retry(2, 300)`, `successful()` check, body (truncated 300 chars) in the message, `ConnectionException` → `unreachable()`
- [ ] 2.3 Same for `RedxCourier` + `PathaoCourier`
- [ ] 2.4 `ShipmentController::store` — try/catch `CourierException` → red toast with the provider's message + `report()` (no more 500)
- [ ] 2.5 `CourierLocationController::guard()` — `report($e)` before returning the empty list
- [ ] 2.6 Tests (`Http::fake`): 401 → CourierException with the status · 422 → provider message surfaces · connection error → "could not reach" · booking failure flashes an error instead of 500-ing

## Phase 3 — Courier: "Test connection"
- [ ] 3.1 `App\Support\Courier\TestsConnection` interface (`testConnection(): string`, throws `CourierException`)
- [ ] 3.2 SteadFast → `GET /get_balance`; RedX → `GET /areas`; Pathao → issue token (cache busted first)
- [ ] 3.3 Route `POST admin/shipping/couriers/{courier}/test` (`permission:couriers.manage`) → `CourierUiController@test`
- [ ] 3.4 `shipping/couriers/index.tsx` — a "Test" button per API courier, showing the real result toast
- [ ] 3.5 `CourierUiController::update()` — forget `courier:pathao:token:{id}` so a credential change takes effect immediately
- [ ] 3.6 Tests: success message · 401 message · manual courier → "no API" · permission-gated

## Phase 4 — Shipping calculator + totals (groundwork)
- [ ] 4.1 `App\Services\Shipping\ShippingCalculator::minorFor(Collection $lines, ?ShippingZone $zone): int` — extracted verbatim from `PlaceOrder.php:130-148`
- [ ] 4.2 `PlaceOrder` uses it. **Existing `PlaceOrderTest` + `FreeShippingProductTest` must stay green untouched** — that is the parity proof.
- [ ] 4.3 `App\Services\Orders\RecalculateOrderTotals` — `total = subtotal + shipping_cost`, then `OrderPaymentReconciler`
- [ ] 4.4 Unit tests for the calculator (zone base once · per-unit × qty · free-shipping lines excluded · no zone → 0)

## Phase 5 — Admin note
- [ ] 5.1 Migration `orders.admin_note` (text, nullable)
- [ ] 5.2 `Order` fillable + docblock
- [ ] 5.3 `UpdateOrderNoteRequest` (`orders.manage`, nullable|string|max:2000)
- [ ] 5.4 Route `PUT admin/orders/{order}/note` → `OrderController@updateNote`
- [ ] 5.5 `orders/show.tsx` — "Admin note" card (textarea + Save)
- [ ] 5.6 Expose `admin_note` in both the index and show payloads; add it to `searchColumns`
- [ ] 5.7 Test: note persists across a status change (unlike `pending_note`) · permission-gated · audit-logged

## Phase 6 — Orders list: pending reason
- [ ] 6.1 `OrderRepository::listConfig()` — `filters` += `pending_reason`; `searchColumns` += `admin_note`
- [ ] 6.2 `OrderController@index` — pass the current `pending_reason` filter + `Order::PENDING_REASONS` options; add `admin_note` + `pending_note` per row
- [ ] 6.3 Shared `resources/js/lib/order-labels.ts` (`PENDING_REASON_LABELS`) — de-dupe `index.tsx:39-45` and `show.tsx:10-16`
- [ ] 6.4 `orders/index.tsx` — pending-reason filter select; include it in `buildParams()` so "select all matching" respects it
- [ ] 6.5 `orders/index.tsx` — inline pending-reason select + Save per pending row (mirrors `RowStatus`); choosing "Other" reveals the required note
- [ ] 6.6 `orders/index.tsx` — Notes column showing the admin note
- [ ] 6.7 Tests: filter narrows the list · unknown value rejected by the whitelist · bulk "all matching" respects the filter

## Phase 7 — Customer + address editing
- [ ] 7.1 `UpdateOrderCustomerRequest` — name/email nullable; mobile required + BD-normalised + unique (ignoring this customer); address required max:1000; `shipping_zone_id` nullable + must be active
- [ ] 7.2 `App\Actions\Orders\UpdateOrderCustomer` — updates the customer row + the order's address/zone; recomputes shipping + total via the calculator **only when the zone changed**; reconciles payment status; audit-logged
- [ ] 7.3 Guards: reject a zone change on a **paid** order · reject when the new total would fall below `advance_paid` · warn (but allow) when a consignment is already booked
- [ ] 7.4 Route `PUT admin/orders/{order}/customer` (`orders.manage`)
- [ ] 7.5 `orders/show.tsx` — editable Customer + Delivery address card (name / mobile / email / address / zone), with the "this edits the customer everywhere" note and the booked-consignment warning
- [ ] 7.6 Tests: corner cases 15–20 from design.md

## Phase 8 — Gates
- [ ] 8.1 Pest green (chunked, `-d memory_limit=1G`)
- [ ] 8.2 Larastan max clean · Pint clean
- [ ] 8.3 `npm run types:check` + `lint:check`

## Phase 9 — Deploy + verify in production
- [ ] 9.1 Commit + push
- [ ] 9.2 **Owner deploys the backend** (migrations run automatically)
- [ ] 9.3 Shipping → Couriers → SteadFast → **Test connection** → expect "Connected. Balance ৳X"
- [ ] 9.4 Order detail → Book button enabled, no "needs credentials" warning → book a real consignment
- [ ] 9.5 Orders list → filter by pending reason; set one inline; add an admin note and see it in the table
- [ ] 9.6 Order detail → correct an address + phone; change the zone and confirm the total, invoice and pay link all follow

## Phase 10 — Archive
- [ ] 10.1 Sync delta specs into `openspec/specs/`
- [ ] 10.2 Archive to `openspec/changes/archive/2026-07-14-courier-credentials-and-order-ops/`
