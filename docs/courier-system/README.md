# Courier Management System

How furnib-ecommerce ships orders — with an integrated courier API (Steadfast,
RedX, Pathao) **or** with a manual, no-API courier — and how to add more.

> TL;DR — every courier is a row in `couriers` with a `driver`. A `driver` of
> `manual` books by hand (name only, printed on the label); `steadfast` / `redx`
> / `pathao` call the provider API. All API drivers share one small abstraction
> (`CourierGateway`) resolved from a registry (`CourierManager`), so a new
> provider is a new class + one registration line — nothing else changes.

---

## 1. Goals & design principles

- **One order flow, many couriers.** The admin picks any active courier per order.
  An API courier books immediately; a manual courier is recorded only.
- **Open for extension, closed for modification (OCP).** Adding RedX/Pathao did
  not touch `CreateConsignment`, the order page, or the status poller — they
  depend on the `CourierGateway` abstraction, not on any concrete provider.
- **Interface Segregation (ISP).** Booking-time location lookups (RedX area,
  Pathao city/zone/area) are *optional* capabilities, not forced onto every
  driver. They live in separate small interfaces a driver opts into.
- **Credentials never leave the server.** Per-courier secrets are stored
  encrypted; the browser only ever sees "is set" flags and id/name option lists.
- **Deleting a courier never breaks history.** Couriers soft-delete, and every
  shipment keeps a **name snapshot** so old labels/PDFs still read correctly.

---

## 2. Data model

### `couriers` table
Migration: `2026_07_05_000002_create_couriers_table.php`

| Column           | Notes |
|------------------|-------|
| `name`           | Display name — printed on the shipping label/PDF. |
| `slug`           | Unique stable identifier (auto from name). |
| `driver`         | `manual \| steadfast \| redx \| pathao`. Decides API vs no-API. |
| `is_active`      | Selectable on orders when true. |
| `is_default`     | At most one — auto-booked on order confirm. |
| `position_order` | Sort order in dropdowns. |
| `config`         | **Encrypted** per-courier credentials/settings (`encrypted:array`). Never sent to the browser. |
| *soft deletes*   | Removing a courier keeps historical shipments intact. |

The `Courier` model (`app/Models/Courier.php`) centralises the driver knowledge:

- `DRIVERS` / `API_DRIVERS` — the full list and the subset that call an API.
- `REQUIRED_CREDENTIALS` — the config keys each API driver needs to be "configured":
  - `steadfast` → `api_key`, `secret_key`
  - `redx` → `access_token`, `pickup_store_id`
  - `pathao` → `client_id`, `client_secret`, `username`, `password`, `store_id`
- `isApi()` — is this an API driver (vs manual)?
- `isConfigured()` — a manual courier is always usable; an API courier needs all
  of its `REQUIRED_CREDENTIALS` present.
- `credential(key)` — read one value out of the encrypted config.
- `Courier::default()` — the active default courier (for auto-push).
- Audit log records everything **except** `config` (secrets are never logged).

### `shipments` table (courier-related columns)
- `courier_id` — FK, `nullOnDelete` (a deleted courier doesn't cascade-delete the shipment).
- `courier` — **name snapshot** (string). What the label prints, even after deletion.
- `consignment_id`, `tracking_code`, `status` — filled by the API (blank for a fresh manual booking).
- `cod_amount` — remaining balance to collect (money as integer minor units).
- `raw_payload` — encrypted full provider response.
- `meta` — **encrypted** booking-time location snapshot (added in
  `2026_07_05_000005_add_meta_to_shipments_table.php`). Holds the RedX
  `delivery_area_id`/`delivery_area`, or the Pathao `recipient_city/zone/area`.
  Steadfast/manual leave it `null`.

---

## 3. Driver architecture

```
                         ┌─────────────────────────┐
   caller code  ────────▶│      CourierManager      │  factory registry
   (CreateConsignment,   │  register(driver, fn)    │  (open for extension)
    ShipmentController,  │  driverFor(Courier)      │
    SyncCourierStatuses) │  canBookViaApi(Courier)  │
                         └───────────┬─────────────┘
                                     │ resolves by $courier->driver
              ┌──────────────────────┼───────────────────────┐
              ▼                      ▼                        ▼
      SteadFastCourier          RedxCourier             PathaoCourier
      (CourierGateway)   (CourierGateway,          (CourierGateway,
                          ListsDeliveryAreas)       CascadesLocations)

      'manual' / unregistered driver ─────▶ driverFor() returns null
                                            (record only, book by hand)
```

### `CourierGateway` — the core abstraction
`app/Support/Courier/CourierGateway.php`

```php
interface CourierGateway
{
    /** @return array{consignment_id: string, tracking_code: string, status: string} */
    public function createConsignment(Shipment $shipment): array;

    public function getStatus(string $trackingCode): string;
}
```

Every API driver implements exactly this. Callers depend only on this interface;
the concrete class is swapped for `FakeCourierGateway` in tests.

### Capability interfaces (ISP) — optional, booking-time location lookups
Only some providers need a location chosen at booking time. Rather than bloat
`CourierGateway`, those live in their own interfaces:

- `ListsDeliveryAreas` → `areas(): array` — **RedX** (a single delivery-area pick).
- `CascadesLocations` → `cities()`, `zones($cityId)`, `areas($zoneId)` — **Pathao**
  (a city → zone → area cascade).

The location proxy controller resolves the driver and does an `instanceof` check,
so a driver that needs no location simply doesn't implement these — and the UI
shows no location selector for it.

### `CourierManager` — the registry
`app/Support/Courier/CourierManager.php`

- `register(driver, Closure)` — bind a driver string to a factory that builds the
  gateway from a `Courier` (reading its encrypted config).
- `driverFor(Courier): ?CourierGateway` — the gateway, or `null` for a manual /
  unregistered driver (callers treat `null` as "record only").
- `canBookViaApi(Courier): bool` — API driver **and** registered **and** configured.

Registration happens once in `RepositoryServiceProvider::register()`:

```php
$this->app->singleton(CourierManager::class, function ($app) {
    $manager = new CourierManager;

    $manager->register(Courier::DRIVER_STEADFAST, fn (Courier $c) => new SteadFastCourier(
        $c->credential('api_key')    ?? $settings->get('steadfast', 'api_key'),
        $c->credential('secret_key') ?? $settings->get('steadfast', 'secret_key'),
    ));

    $manager->register(Courier::DRIVER_REDX, fn (Courier $c) => new RedxCourier(
        $c->credential('access_token'), $c->credential('pickup_store_id'),
        (bool) ($c->config['sandbox'] ?? false),
    ));

    $manager->register(Courier::DRIVER_PATHAO, fn (Courier $c) => new PathaoCourier(
        $c->credential('client_id'), $c->credential('client_secret'),
        $c->credential('username'),  $c->credential('password'),
        $c->credential('store_id'),  (bool) ($c->config['sandbox'] ?? false),
        'courier:pathao:token:'.$c->id,   // per-courier token cache key
    ));

    return $manager;
});
```

> The Steadfast factory falls back to the legacy `steadfast` settings group, so
> installs that pre-date the courier table keep working with no reconfiguration.

---

## 4. The four drivers

### `manual` — a courier **without** an API
No class, no network. `driverFor()` returns `null`. `CreateConsignment` records
the shipment (recipient, COD, note, **name snapshot**) and stops — no API call.
The admin arranges pickup themselves and updates the status by hand. The name
still prints on the label/PDF. Use this for any local courier that has no API
(e.g. "Sundarban Courier", "SA Paribahan").

### `steadfast` — API
`app/Support/Courier/SteadFastCourier.php`. Base `https://portal.packzy.com/api/v1`.
Headers `Api-Key` / `Secret-Key`. `POST /create_order`, `GET /status_by_trackingcode/{code}`.
Needs no booking-time location.

### `redx` — API + single delivery-area pick
`app/Support/Courier/RedxCourier.php`. Implements `CourierGateway` **and**
`ListsDeliveryAreas`. Base `openapi.redx.com.bd` (live) / `sandbox.redx.com.bd`
(sandbox), version prefix `/v1.0.0-beta`. Header `API-ACCESS-TOKEN: Bearer <jwt>`.

- `POST /parcel` — reads `meta.delivery_area_id` + `meta.delivery_area`, the
  configured `pickup_store_id`, recipient fields, and the COD amount → returns a
  `tracking_id` (used as both consignment id and tracking code).
- `GET /parcel/track/{id}` — the latest tracking event's message is the status.
- `GET /areas` — the delivery-area option list for the booking selector.

Config: `access_token`, `pickup_store_id`, `sandbox`.

### `pathao` — API + city/zone/area cascade + OAuth
`app/Support/Courier/PathaoCourier.php`. Implements `CourierGateway` **and**
`CascadesLocations`. Base `api-hermes.pathao.com` (live) /
`courier-api-sandbox.pathao.com` (sandbox).

- **OAuth**: `POST /aladdin/api/v1/issue-token` (`grant_type=password`). The
  access token is **cached per courier** (`courier:pathao:token:{id}`), TTL =
  `expires_in − 300s`, so we don't re-issue a token on every call.
- `POST /aladdin/api/v1/orders` — reads `meta.recipient_city/zone/area`, the
  configured `store_id`, recipient fields, `delivery_type=48` (normal),
  `item_type=2` (parcel), and `amount_to_collect` (COD) → returns
  `data.consignment_id`.
- **Location cascade** (numeric ids): `GET /aladdin/api/v1/city-list` →
  `GET /aladdin/api/v1/cities/{id}/zone-list` → `GET /aladdin/api/v1/zones/{id}/area-list`.
- `GET /aladdin/api/v1/orders/{id}/info` — `data.order_status` for polling.

Config: `client_id`, `client_secret`, `username`, `password`, `store_id`, `sandbox`.

---

## 5. Booking flow

### The one action: `CreateConsignment`
`app/Actions/Shipments/CreateConsignment.php`

```php
public function handle(Order $order, Courier $courier, ?string $note = null, array $meta = []): Shipment
```

1. Find-or-new the shipment for the order. **Idempotent**: if an API consignment
   already exists, return it untouched (never double-book).
2. Fill recipient details, the **name snapshot** (`courier` = `$courier->name`),
   `courier_id`, the **COD = remaining balance** (`total − advance_paid`, computed
   server-side), and the booking `meta` (location snapshot, or `null`).
3. Resolve the driver via `CourierManager::driverFor($courier)`:
   - **`null` (manual / unregistered)** → stop. Recorded only, booked by hand.
   - **a gateway (API)** → call `createConsignment($shipment)` and store the
     returned `consignment_id` / `tracking_code` / `status` / raw payload.

### Manual admin booking: `ShipmentController::store`
`POST /admin/orders/{order}/ship` (permission `orders.manage`).

1. Look up the chosen courier; the `exists … is_active` rule rejects an unknown
   or inactive selection with a validation error.
2. **Driver-specific location validation** (`metaRules`): RedX requires
   `delivery_area_id` + `delivery_area`; Pathao requires `recipient_city/zone/area`;
   others require nothing.
3. Refuse an API courier that isn't configured (flash an error, don't book).
4. Shape the booking `meta` per driver (`metaFor`) and call `CreateConsignment`.

### Auto-booking on confirm: `OrderObserver`
`app/Observers/OrderObserver.php`. On the `pending → confirmed` transition, if the
`courier.auto_push` setting is on (default) **and** there is a default courier
that `canBookViaApi()`, it dispatches `PushOrderToCourier` (queued, unique per
order, retryable). A manual default has no API to call, so it is skipped.
`CreateConsignment`'s idempotency guarantees exactly one consignment even across
retries.

---

## 6. Booking-time location selection (RedX / Pathao)

These providers need a location that the order's free-text address can't supply,
so it's chosen when booking, on the order page (`orders/show.tsx`,
`BookCourierCard`):

- **RedX** — one "Delivery area" dropdown.
- **Pathao** — a **City → Zone → Area** cascade (each level loads after the parent
  is picked; children reset when a parent changes).

The dropdowns are populated by a **server-side proxy**, so provider credentials
never reach the browser:

`CourierLocationController` (routes under `couriers/{courier}/locations/...`,
permission `orders.manage`):

- `GET …/areas` → RedX `areas()` (guarded by `instanceof ListsDeliveryAreas`).
- `GET …/cities`, `…/zones?city_id=`, `…/pathao-areas?zone_id=` → Pathao
  `CascadesLocations`.

The controller catches provider errors and returns an empty list + a message
(HTTP 200) rather than a 500 — a courier outage degrades to "try again" instead
of breaking the order page. The chosen ids are submitted as hidden fields and
snapshotted into `shipments.meta`; the submit button stays disabled until the
location is complete.

---

## 7. Status tracking

API couriers have no delivery webhook, so we **poll**. `SyncCourierStatuses`
(scheduled, `app/Jobs/SyncCourierStatuses.php`) walks every shipment that has a
tracking code, a courier, and a non-terminal status; resolves that shipment's own
driver; and maps the fetched status onto our record. **Manual couriers are
skipped** (`driverFor()` is `null`). Terminal statuses (`delivered`,
`partial_delivered`, `cancelled`, `returned`) are never re-polled. Transient
provider errors are reported and retried next run. The admin can also poll one
order on demand via `ShipmentController::track` (`POST …/track`).

These statuses also feed the per-customer fraud/return-ratio score shown on the
order page (`CustomerCourierStats`).

---

## 8. Security

- **Secrets encrypted at rest** (`config` is `encrypted:array`) and sent only to
  the provider over HTTPS. Never returned to the browser, never logged (audit log
  excludes `config`), never in the Next bundle.
- **Blank-keeps semantics** — leaving a credential field blank on edit keeps the
  stored secret; the form only exposes `*_set` booleans.
- **Authorization** — courier CRUD needs `couriers.manage`; booking/tracking and
  the location proxy need `orders.manage`. Every route is permission-guarded.
- **COD is derived server-side** (`total − advance_paid`) — never trusted from the
  client.
- **Location proxy** keeps provider tokens on the server; the browser only sees
  id/name option lists.

---

## 9. Admin UI

- **Shipping → Couriers** (`shipping/couriers/index.tsx`, `form.tsx`): CRUD. The
  form shows the driver-specific credential section (Steadfast / RedX / Pathao)
  plus a **Sandbox** toggle for API drivers, and a "not set / set — blank keeps"
  hint per secret. Manual shows an explanatory note and no credentials.
- **Order page** (`orders/show.tsx`, `BookCourierCard`): courier dropdown + note,
  the RedX/Pathao location selector when relevant, and a Book/Record button whose
  label reflects API vs manual.

---

## 10. How to add a new courier provider

Everything below is *additive* — no existing caller changes.

1. **Write the driver** `app/Support/Courier/<Name>Courier.php` implementing
   `CourierGateway`. If it needs a booking-time location, also implement
   `ListsDeliveryAreas` or `CascadesLocations` (or define a new capability
   interface for a different shape).
2. **Declare it on the model** — add the `DRIVER_*` const, and its keys in
   `Courier::DRIVERS`, `API_DRIVERS`, and `REQUIRED_CREDENTIALS`.
3. **Register the factory** in `RepositoryServiceProvider` (`$manager->register(...)`),
   building the driver from `$courier->credential(...)`.
4. **Expose it in the form** — add to `CourierFormRequest::SELECTABLE_DRIVERS`,
   add its credential validation rules, handle its keys in
   `CourierUiController::buildConfig`/`formData`, and add a credential section +
   driver label in `couriers/form.tsx`.
5. **Booking meta (only if it needs a location)** — add cases to
   `ShipmentController::metaRules`/`metaFor`, a `CourierLocationController`
   endpoint if the location shape is new, and a selector in `BookCourierCard`.
6. **Test it** with `Http::fake` (see below).

For a courier that has an API we *don't* integrate, just create it as a **manual**
courier — no code needed.

---

## 11. Testing

- **Driver unit tests** (`tests/Feature/Courier/RedxCourierTest.php`,
  `PathaoCourierTest.php`) use `Http::fake` — no real network. They cover create,
  status, area/cascade lookups, the "missing location" refusal, token caching
  (Pathao issues a token once across calls), and sandbox-vs-live host selection.
- **Booking/flow tests** (`tests/Feature/Admin/CourierTest.php`) register a
  `FakeCourierGateway` on the manager for the driver under test, then assert the
  meta snapshot, COD math, idempotency, and authorization.
- **CRUD tests** (`tests/Feature/Admin/CourierCrudTest.php`) cover encrypted
  credential storage, blank-keeps, the "unconfigured until every key set" rule,
  single-default enforcement, and that secrets never reach the browser.
- **Automation tests** (`tests/Feature/Courier/CourierAutomationTest.php`) cover
  auto-push on confirm and the status poller skipping manual couriers.

Run (the full suite OOMs on this machine due to dompdf — run the courier dirs):

```bash
php -d memory_limit=1G vendor/bin/pest tests/Feature/Courier \
  tests/Feature/Admin/CourierTest.php tests/Feature/Admin/CourierCrudTest.php
```

> **Live vendors are not tested here** — RedX/Pathao have no local sandbox
> credentials, so the drivers are coded to the documented contracts and verified
> with faked HTTP. Confirm field/response shapes on each provider's sandbox
> (Sandbox toggle on) before switching a courier to live.

---

## 12. Operations / deploy

1. Deploy; run migrations (`php artisan migrate --force` or on boot). This creates
   `couriers`, seeds a default **Steadfast** (carrying any legacy settings creds)
   and a **Manual** courier, and adds `shipments.courier_id` + `shipments.meta`.
2. In **Shipping → Couriers**, add real credentials for each provider you use,
   tick **Sandbox** while testing, set the **default** courier (for auto-push).
3. Make sure the queue worker and scheduler run (EasyPanel: supervisor) — booking
   is queued (`PushOrderToCourier`) and status polling is scheduled
   (`SyncCourierStatuses`).

## File map

| Concern | File |
|---|---|
| Abstraction | `app/Support/Courier/CourierGateway.php` |
| Capabilities | `app/Support/Courier/ListsDeliveryAreas.php`, `CascadesLocations.php` |
| Registry | `app/Support/Courier/CourierManager.php` |
| Drivers | `app/Support/Courier/{SteadFast,Redx,Pathao}Courier.php`, `FakeCourierGateway.php` |
| Model | `app/Models/Courier.php`, `app/Models/Shipment.php` |
| Booking action | `app/Actions/Shipments/CreateConsignment.php` |
| Auto-push / polling | `app/Observers/OrderObserver.php`, `app/Jobs/{PushOrderToCourier,SyncCourierStatuses}.php` |
| Admin booking + tracking | `app/Http/Controllers/Admin/ShipmentController.php` |
| Location proxy | `app/Http/Controllers/Admin/CourierLocationController.php` |
| Courier CRUD | `app/Http/Controllers/Admin/Catalog/CourierUiController.php`, `Http/Requests/Admin/CourierFormRequest.php` |
| Registration | `app/Providers/RepositoryServiceProvider.php` |
| Admin UI | `resources/js/pages/shipping/couriers/{index,form}.tsx`, `resources/js/pages/orders/show.tsx` |
| Migrations | `database/migrations/2026_07_05_00000{2,3,4,5}_*.php` |

---

# Appendix A — Provider API reference (research notes)

> These are the vendor API contracts the drivers were built against. They are
> **private Bangladeshi courier APIs** — `context7` does not cover them, so they
> were researched from each provider's official developer docs and public SDKs
> (see **Sources** at the end). Field/response shapes should be re-confirmed on
> each provider's own sandbox before go-live, since these vendors version their
> APIs without much notice.

## A.1 Steadfast (driver `steadfast`)

- **Base URL:** `https://portal.packzy.com/api/v1`
- **Auth:** two headers — `Api-Key: <key>` and `Secret-Key: <secret>` (plus
  `Content-Type: application/json`). No OAuth, no token exchange.
- **Sandbox:** Steadfast has no separate sandbox host; test with a real staging
  merchant account.

### Create order — `POST /create_order`
Request body:

| Field | Notes |
|---|---|
| `invoice` | Our `order_no` (must be unique per merchant). |
| `recipient_name` | |
| `recipient_phone` | 11-digit BD number. |
| `recipient_address` | Free text. |
| `cod_amount` | Cash to collect, decimal string (we send whole taka `"1500.00"`). |
| `note` | Optional delivery instruction. |

Response (relevant): `consignment.consignment_id`, `consignment.tracking_code`,
`consignment.status`.

### Track — `GET /status_by_trackingcode/{tracking_code}`
Response: `delivery_status` (string). Other lookups exist
(`status_by_cid/{id}`, `status_by_invoice/{invoice}`) but we track by code.

### Delivery status values
`pending`, `in_review`, `delivered_approval_pending`, `partial_delivered_approval_pending`,
`cancelled_approval_pending`, `unknown_approval_pending`, `delivered`,
`partial_delivered`, `cancelled`, `hold`, `in_transit`, `return`. We treat
`delivered`, `partial_delivered`, `cancelled`, `returned` as terminal.

## A.2 RedX (driver `redx`)

- **Base URL:** `https://openapi.redx.com.bd` (live) /
  `https://sandbox.redx.com.bd` (sandbox). **Version prefix `/v1.0.0-beta`** on
  every path.
- **Auth:** single header `API-ACCESS-TOKEN: Bearer <jwt>`. The JWT is obtained
  from the RedX merchant panel (Developer/API section) — it is long-lived, so we
  store it directly as `access_token` (no runtime token exchange).
- **Pickup store:** parcels are booked *from* a pickup store; its id
  (`pickup_store_id`) is a fixed per-merchant setting we store in config.

### Create parcel — `POST /v1.0.0-beta/parcel`
Request body:

| Field | Notes |
|---|---|
| `customer_name` | Recipient. |
| `customer_phone` | |
| `delivery_area` | Area **name** string (must match an id from `/areas`). |
| `delivery_area_id` | Area **numeric id** from `/areas`. Both name + id are required. |
| `customer_address` | Free text. |
| `merchant_invoice_id` | Our `order_no`. |
| `cash_collection_amount` | COD (we send whole taka as a string). |
| `parcel_weight` | Grams (we default 500). |
| `value` | Declared value. |
| `pickup_store_id` | Fixed per-merchant. |
| `instruction` | Optional. |
| `is_closed_box` | Optional. |
| `parcel_details_json` | Optional array of `{name, category, value}` line items. |

Response: `tracking_id` (string). RedX exposes a **single** id — we use it as both
the consignment id and the tracking code.

### Areas — `GET /v1.0.0-beta/areas`
Response: `areas: [{ id, name, post_code, ... }]`. Populates the booking
delivery-area selector.

### Track — `GET /v1.0.0-beta/parcel/track/{tracking_id}`
Response: `tracking: [{ message_en, message_bn, time, ... }]` (newest first). We
read the latest event's `message_en` as the status.

### Other RedX endpoints (not used by the driver, for reference)
- `POST /v1.0.0-beta/pickup/store` — create a pickup store.
- `GET /v1.0.0-beta/pickup/stores` — list pickup stores (to find `pickup_store_id`).
- `GET /v1.0.0-beta/areas?district_name=&post_code=` — filtered area lookup.

## A.3 Pathao (driver `pathao`) — "Aladdin" Merchant API

- **Base URL:** `https://api-hermes.pathao.com` (live) /
  `https://courier-api-sandbox.pathao.com` (sandbox).
- **Auth:** OAuth2 *password* grant. You get `client_id`, `client_secret`,
  `username`, `password` from the Pathao Merchant panel (Developer API). Exchange
  them for an access token, then send it as `Authorization: Bearer <token>`.
- **Store:** orders are booked from a Pathao store; its `store_id` is a fixed
  per-merchant setting.

### Issue token — `POST /aladdin/api/v1/issue-token`
Request body: `client_id`, `client_secret`, `username`, `password`,
`grant_type: "password"`. Response: `access_token`, `refresh_token`, `token_type`
(`Bearer`), `expires_in` (seconds — typically ~5 days / `432000`). A
`grant_type: "refresh_token"` variant also exists. **We cache the access token
per courier** (`courier:pathao:token:{id}`) for `expires_in − 300s` so we don't
re-issue on every call.

### Location cascade (numeric ids, required for booking)
- `GET /aladdin/api/v1/city-list` → `data.data: [{ city_id, city_name }]`
- `GET /aladdin/api/v1/cities/{city_id}/zone-list` → `data.data: [{ zone_id, zone_name }]`
- `GET /aladdin/api/v1/zones/{zone_id}/area-list` → `data.data: [{ area_id, area_name }]`

(Responses nest the list under `data.data`.) These feed the City → Zone → Area
cascade in the booking UI.

### Create order — `POST /aladdin/api/v1/orders`
Request body:

| Field | Notes |
|---|---|
| `store_id` | Fixed per-merchant. |
| `merchant_order_id` | Our `order_no` (optional but recommended). |
| `recipient_name` | |
| `recipient_phone` | |
| `recipient_address` | Free text. |
| `recipient_city` | Numeric `city_id` from the cascade. |
| `recipient_zone` | Numeric `zone_id`. |
| `recipient_area` | Numeric `area_id`. |
| `delivery_type` | `48` = Normal, `12` = On-Demand. We send `48`. |
| `item_type` | `1` = Document, `2` = Parcel. We send `2`. |
| `special_instruction` | Optional. |
| `item_quantity` | Default 1. |
| `item_weight` | Kg, string (e.g. `"0.5"`). Pathao range ~0.5–10. |
| `amount_to_collect` | COD (whole taka). `0` for prepaid. |

Response: `data.consignment_id`, `data.merchant_order_id`, `data.order_status`,
`data.delivery_fee`.

### Order info / status — `GET /aladdin/api/v1/orders/{consignment_id}/info`
Response: `data.order_status` (and fee/details). Used by the poller.

### Other Pathao endpoints (not used by the driver, for reference)
- `GET /aladdin/api/v1/stores` — list merchant stores (to find `store_id`).
- `POST /aladdin/api/v1/merchant/price-plan` — price/fee estimate before booking.
- `POST /aladdin/api/v1/orders/bulk` — bulk order creation.

## A.4 Design decisions that came out of the research

- **Single tracking id (RedX) vs consignment+tracking split (Steadfast).** Our
  `CourierGateway` returns both `consignment_id` and `tracking_code`; for RedX we
  set both to the one `tracking_id`, so the rest of the system (labels, polling)
  doesn't special-case it.
- **Location at booking, not at checkout.** RedX needs an area id and Pathao needs
  city/zone/area ids that a customer's free-text address can't provide reliably.
  Rather than force the storefront customer through a courier-specific location
  picker (and lock the order to one courier early), the location is chosen by the
  admin **at booking time** and snapshotted onto `shipments.meta`. Steadfast and
  manual need none, so they show no selector.
- **Per-courier token cache (Pathao).** Because a merchant can have more than one
  Pathao courier row (e.g. two stores), the OAuth token is cached under a
  courier-scoped key, never globally.
- **Sandbox as a per-courier flag**, not an app-wide env — so you can run one
  courier live and another in sandbox side by side while testing.
- **Weight/among defaults.** RedX `parcel_weight` (grams) and Pathao `item_weight`
  (kg) default to a small parcel; both can be overridden via `meta` if a
  per-order weight is added later.

## Sources

- Steadfast Courier API — <https://steadfast.com.bd/> (merchant API docs, developer section)
- RedX Developer API — <https://redx.com.bd/developer-api/>
- Pathao Merchant (Aladdin) API — <https://merchant.pathao.com/> and Pathao
  merchant developer docs
- Cross-checked against public community SDKs (Laravel/PHP, Node, Python) for
  RedX and Pathao to confirm endpoint paths, request fields, and response nesting.
