# Session handover — 2026-07-05

Context-preserving summary so work continues cleanly after `/compact`.
All commits below are on `master` and **pushed**. **Nothing is deployed yet** —
backend + storefront redeploy pending (EasyPanel; migrations run on boot or via
`php artisan migrate --force` in the container terminal).

## Commits this session (oldest → newest)
1. `eaabf14` fix(storefront): mobile-only banner no longer leaks onto desktop; image size hints
2. `12e64a4` fix(catalog): surface product-form validation errors (rejected save ≠ silent revert)
3. `a46d38e` feat(catalog): per-product `shipping_charge_allowed` (free delivery) + latest-first category ordering
4. `8d00717` fix(checkout): fully hide shipping UI for a free-shipping product
5. `db3220f` feat(payments): delivery-charge + full buttons on COD success view, remaining-due charging, per-order payment history
6. `a7e8359` fix(storefront): mobile home banner → 2:1 landscape (desktop unchanged)
7. `33ee7ae` feat(courier): **Phase 1** courier management system (CRUD) + SOLID multi-driver, manual + Steadfast

## What each feature does (key files)

### Free shipping per product (`a46d38e`, `8d00717`)
- `products.shipping_charge_allowed` (bool, default true). False = zero delivery everywhere.
- `PlaceOrder`: a free line contributes nothing; zone base charged once only if ≥1 chargeable line; all-free cart ships free. `Product::extraPerUnitMinorFor()` returns 0 when disabled.
- Admin product form: "Charge delivery" toggle; per-zone charges section hides + `syncShippingCharges` wipes rows when off.
- Storefront `CheckoutForm`: free product hides the whole shipping selector + summary row, sends `shipping_zone_id: null`. `ProductResource.free_shipping`, `ProductShippingZoneController` zeros base+extra.
- Category listing order: `position_order` ASC, then `created_at DESC, id DESC` (latest published leads order 0).

### COD pay buttons + payment history + remaining-due (`db3220f`)
- **Crux fix**: `PaymentAmount::for()` now charges the REMAINING amount for every type (`target − advance_paid`) → paying delivery then full never double-charges.
- `PayableState` (shared by pay page + success): due/shipping/can_pay_shipping/can_pay_full. Delivery button only when charge exists, uncovered, and due > shipping.
- `PaymentHistory` — customer-safe rows (success + pending, newest first; no tran_id/val_id/raw payload; failed/cancelled hidden). Exposed on `OrderStatusController` (mobile-gated) + `PayPageController` (token-gated). Admin ledger already existed.
- Storefront: `checkout/success` COD branch = "Pay delivery charge" + "Pay full payment" + history list; `/pay/[order]` gains history.

### Mobile banner (`a7e8359`)
- `BannerCarousel` mobile slot `aspect-4/5` → `aspect-2/1` (desktop `aspect-9/2` UNCHANGED — user confirmed desktop is perfect). Admin hint mobile 800×1000 → 1080×540 (2:1). Desktop + mobile have separate image uploads.

### Courier Phase 1 (`33ee7ae`) — the big one
Decisions (user-approved): **phased** (P1 core+manual+steadfast → P2 RedX → P3 Pathao); **per-courier encrypted config JSON**; new **`couriers.manage`** permission (owner+admin); **default courier** for auto-push.

- `couriers` table: name, slug (unique), driver (manual|steadfast|redx|pathao), is_active, is_default, position_order, `config` (encrypted:array), softDeletes. `Courier` model: `default()`, `isApi()`, `credential()`, `isConfigured()`, `REQUIRED_CREDENTIALS`, audit excludes config.
- `CourierManager` (`app/Support/Courier/`): factory registry `register(driver, closure)` + `driverFor(Courier): ?CourierGateway` (manual/unregistered → null) + `canBookViaApi()`. Registered in `RepositoryServiceProvider` (steadfast factory: per-courier config, legacy `steadfast` settings fallback). **Tests register a fake driver on the manager** (not the old `CourierGateway` binding, which was removed from `$bindings`).
- `SteadFastCourier` now takes injected `(?apiKey, ?secretKey)` — no longer reads settings.
- `Shipment` + migration: `courier_id` FK (nullOnDelete) + kept `courier` string = **name snapshot**. `courierModel()` relation.
- Refactored: `CreateConsignment::handle(Order, Courier, ?note)`, `PushOrderToCourier(orderId, courierId)`, `SyncCourierStatuses` (per-shipment driver, skips manual), `OrderObserver` (auto-push default API courier; setting group now `courier.auto_push`), `ShipmentController::store` (validates active courier_id; manual = record, API = book; rejects unconfigured).
- Admin CRUD: `CourierUiController` + `CourierFormRequest` (`SELECTABLE_DRIVERS = [manual, steadfast]` for P1), routes under `/admin/shipping/couriers`, sidebar "Couriers" item, `OrderController::show` passes active `couriers`, order page `BookCourierCard` selector.
- Seed migration: default **Steadfast** (copies legacy settings creds) + **Manual** courier.
- Tests: `CourierCrudTest` (9), rewritten `CourierTest` + `CourierAutomationTest`.

## Gates status (all green at session end)
Pest full suite green (run in chunks — the machine OOMs on the whole suite at once because of dompdf; use `-d memory_limit=1G` and split dirs). Pint clean. Larastan max 0. Admin build + storefront typecheck/build green.

### WhatsApp country code fix (`lib/whatsapp.ts`)
Local BD numbers (e.g. `01748870651`) produced "chat not found". Added
`normalizeWhatsappNumber()` in the single shared `link()` builder → every button
(floating, footer, product-card inquiry, hero, mobile tab bar, header search,
order) now sends `880XXXXXXXXXX`. No raw `wa.me` links exist elsewhere.

## Courier Phase 2 (RedX) + Phase 3 (Pathao) — DONE (this session, after compact)
Both API drivers built SOLID/DRY on top of the Phase-1 registry — **callers untouched**, just two new `register(...)` lines. Gates green (Pint · Larastan 0 · eslint · admin build · 117 Orders+Courier+Payments tests pass).

- **Shared location snapshot**: new migration `2026_07_05_000005_add_meta_to_shipments_table` → `shipments.meta` (encrypted:array). Booking-time location ids live here; Steadfast/manual leave it null. `CreateConsignment::handle(Order, Courier, ?note, array $meta = [])`.
- **Capability interfaces (ISP)**: `ListsDeliveryAreas` (RedX — `areas()`), `CascadesLocations` (Pathao — `cities()/zones($cityId)/areas($zoneId)`). Location proxy controller resolves the driver and `instanceof`-checks the capability, so credentials never leave the server.
- **`RedxCourier`** (`ListsDeliveryAreas`): `POST /v1.0.0-beta/parcel` (header `API-ACCESS-TOKEN: Bearer`), `GET /parcel/track/{id}`, `GET /areas`. Config: `access_token`, `pickup_store_id`, `sandbox`. Reads `meta.delivery_area_id` + `meta.delivery_area`.
- **`PathaoCourier`** (`CascadesLocations`): OAuth `POST /aladdin/api/v1/issue-token` **cached per courier** (`courier:pathao:token:{id}`, TTL = expires_in − 300s); `POST /aladdin/api/v1/orders`; cascade `city-list`→`cities/{id}/zone-list`→`zones/{id}/area-list`; status `GET /orders/{id}/info`. Config: `client_id/client_secret/username/password/store_id/sandbox`. Reads `meta.recipient_city/zone/area`.
- **Registry**: `RepositoryServiceProvider` registers `redx` + `pathao` factories. `Courier::REQUIRED_CREDENTIALS` now `redx => [access_token, pickup_store_id]`.
- **Admin courier form**: `SELECTABLE_DRIVERS` = all four; driver-aware `buildConfig`/`formData` (blank-keeps per key + `sandbox` flag); form.tsx has RedX/Pathao credential sections + Sandbox toggle; `*_set` flags only (no secrets to browser).
- **Booking (order page)**: `ShipmentController::store` validates + snapshots driver-specific meta (`metaRules`/`metaFor`). `CourierLocationController` + routes `couriers/{courier}/locations/{areas,cities,zones,pathao-areas}` (permission:orders.manage). `orders/show.tsx` `BookCourierCard` shows a RedX area selector / Pathao city→zone→area cascade that fetches those endpoints; submit disabled until the location is fully chosen. Manual/Steadfast unaffected.
- **Tests**: `RedxCourierTest`, `PathaoCourierTest` (Http::fake — create, missing-meta throw, status, area/cascade, token caching, sandbox vs live host), plus booking-meta snapshot tests in `CourierTest` and RedX/Pathao CRUD in `CourierCrudTest`.

⚠️ **Not verifiable against live vendor APIs** (no sandbox creds here) — drivers coded to the documented contracts + tested with faked HTTP. First real booking on staging should confirm field names/response shapes with each provider's sandbox before go-live.

## Pending / next
- **Deploy** everything (backend + storefront). Migrations run on boot / `php artisan migrate --force`. Then in admin under Couriers: add real Steadfast/RedX/Pathao creds, set the default, tick Sandbox while testing. Upload 2:1 mobile banners, BTRC-vet SMS templates, regenerate the leaked Automas key.
- **Verify RedX/Pathao on each vendor's sandbox** before switching a courier off Sandbox mode (confirm parcel/area field names + response ids).
- context7 does NOT cover Steadfast/Automas/RedX/Pathao (private BD vendors) — researched via web/official docs.

## Standing project rules (unchanged)
Git standing permission for this project (routine git just do it; confirm force-push/reset/history-rewrite). Money = integer minor units (paisa), whole taka. MoneyCast gotcha: a raw int passed to MoneyCast is treated as DISPLAY (×100); tests pass `Money::fromMinor()`. PHP 8.3 CLI: `/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php.exe`; admin build `PATH="/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64:$PATH" npm run build`. Reply in Dhakaiya Bangla, tech/UI terms English. Never commit `docs/sms-gateway/code-examples.txt` / `notes.txt` (real API key).
