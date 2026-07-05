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

## Pending / next
- **Deploy** everything (backend + storefront). Then in admin: add real Steadfast creds under Couriers (or they carried over from legacy settings), set the default, upload 2:1 mobile banners, BTRC-vet SMS templates.
- **Courier Phase 2 — RedX**: base `openapi.redx.com.bd` (live) / `sandbox.redx.com.bd` (sandbox); header `API-ACCESS-TOKEN: Bearer <jwt>`; `POST /parcel` (customer_name, customer_phone, delivery_area, delivery_area_id, customer_address, merchant_invoice_id, cash_collection_amount, parcel_weight, value, pickup_store_id → resp `tracking_id`); `GET /areas`; `GET /parcel/track/{tracking_id}`. Needs a booking-time **area selector** (order has no RedX area id). Register `redx` factory + `RedxCourier` driver + add to `SELECTABLE_DRIVERS` + credential fields.
- **Courier Phase 3 — Pathao**: base `api-hermes.pathao.com` (live) / `courier-api-sandbox.pathao.com` (sandbox); OAuth `POST /aladdin/api/v1/issue-token` (client_id, client_secret, username, password, grant_type=password → access_token + refresh_token, cache per courier); location cascade `city-list` → `cities/{id}/zone-list` → `zones/{id}/area-list` (numeric ids); `POST /aladdin/api/v1/orders` (store_id, recipient_*, recipient_city/zone/area, delivery_type 48/12, item_type 2, amount_to_collect=COD). Needs booking-time city→zone→area cascade UI.
- context7 does NOT cover Steadfast/Automas/RedX/Pathao (private BD vendors) — researched via web/official docs.

## Standing project rules (unchanged)
Git standing permission for this project (routine git just do it; confirm force-push/reset/history-rewrite). Money = integer minor units (paisa), whole taka. MoneyCast gotcha: a raw int passed to MoneyCast is treated as DISPLAY (×100); tests pass `Money::fromMinor()`. PHP 8.3 CLI: `/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php.exe`; admin build `PATH="/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64:$PATH" npm run build`. Reply in Dhakaiya Bangla, tech/UI terms English. Never commit `docs/sms-gateway/code-examples.txt` / `notes.txt` (real API key).
