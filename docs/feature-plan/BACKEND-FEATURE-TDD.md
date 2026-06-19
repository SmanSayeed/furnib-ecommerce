# Furnib.com — Backend Feature Breakdown + TDD Plan

> **Methodology (locked with owner, 2026-06-19): backend-first.**
> For each phase we complete the **entire backend** (migrations → models → DTOs →
> services/actions → requests → controllers/routes → resources → API), fully
> tested and green, **before** building any frontend (admin Inertia pages or
> storefront). Frontend for a phase only starts once that phase's backend DoD is met.
>
> Companion to `MASTER-PLAN.md` (architecture/decisions §0–§9), `MODULES.md`
> (module map M0–M20 / S1–S11) and `ROADMAP.md` (ordered tasks). This file is the
> **executable TDD checklist**: every feature lists the artifacts to build and the
> Pest tests to write **first (RED)**.

## How to read this
- **Each feature** = a small shippable unit with: **Artifacts** (what code) + **RED tests** (write these first, watch them fail) + **DoD**.
- **RED → GREEN → REFACTOR** per feature (MASTER-PLAN §2). Money = integer paisa everywhere.
- **DoD (every feature):** Pest green · Larastan max clean · Pint clean · authz (Policy/RBAC gate) + validation present · ownership checks on `:id` (no IDOR) · rate limits on public/order/OTP · **no secret in client bundle** · audit log on sensitive writes.
- Integrations (SSLCommerz / SteadFast / SMS / SMTP) sit behind **interfaces** and are **faked** in tests; one optional sandbox contract test each.
- Test types: **[U]** unit (pure logic, no DB) · **[F]** feature (HTTP + DB) · **[I]** integration (faked external).

Legend: ✅ done · 🔜 next · ⬜ not started.

---

# PHASE 3 — Orders & Web Checkout (backend) `feat/phase-3-orders`

Locked data model (MASTER-PLAN §3): `shipping_zones`, `customers`, `orders`,
`order_items`. (`payments`, `shipments`, `otp_codes` are Phase 4.)
Order status enum: `pending|confirmed|processing|shipped|delivered|cancelled|returned`.
Payment status enum: `unpaid|partial|paid`.

## 3.1 Shipping Zones (M6) — ✅ written, pending verify
**Artifacts:** `shipping_zones` migration (name, cost int paisa, status, position_order); `ShippingZone` model (MoneyCast cost, Auditable, scopes); `ShippingZoneFactory`; `Admin\ShippingZoneFormRequest` (orders.manage); `Admin\ShippingZoneController` (Inertia CRUD); routes `admin/shipping/zones`.
**RED tests** (`tests/Feature/Admin/ShippingZoneUiTest.php`):
- [F] blocks `orders.view`-only user from creating (403).
- [F] lists zones for `orders.view`.
- [F] creates zone, stores cost as paisa (80.50 → 8050).
- [F] updates zone.
- [F] deletes zone.
**Still to add (backend-first hardening):**
- [U] `ShippingZone::active()` scope returns only active.
- [F] public storefront API `GET /api/v1/shipping-zones` returns active zones only (id, name, cost) for checkout — **add this** (checkout needs it).

## 3.2 Customer resolution (M5 foundation) — 🔜
**Artifacts:** `customers` migration (name, mobile unique+normalized, email nullable, timestamps, softDeletes); `Customer` model (relations: orders); `Support/Mobile` value object or `MobileNumber` normalizer (BD `+88`, 11-digit local `01XXXXXXXXX`); `CustomerService::findOrCreateByMobile()`.
**RED tests:**
- [U] `MobileNumber`: `01712345678` → `+8801712345678`; `+8801712345678` stays; rejects `123`, non-BD, wrong length, letters.
- [U] two different display forms of the same number normalize equal.
- [F] `findOrCreateByMobile` creates a new customer (normalized stored).
- [F] same mobile (any display form) reuses the existing customer (no duplicate).
- [F] fills `name`/`email` when previously blank; never overwrites a non-empty name with blank.
**DoD:** mobile uniqueness enforced at DB + service; no plaintext sensitive leak in logs.

## 3.3 Order domain + `PlaceOrder` action (M7 core) — ⬜
**Artifacts:**
- `orders` migration: order_no (unique), customer_id (FK), status (enum, default pending), subtotal/shipping_cost/total/advance_paid (int paisa), payment_status (enum, default unpaid), shipping_zone_id (FK nullable), address (text), customer_ip, user_agent, notes (nullable), timestamps, softDeletes.
- `order_items` migration: order_id (FK, cascade), product_id (FK nullable on delete set null), title/sku/price (snapshot), qty (uint), line_total (int paisa).
- `Order` model (Money casts on money cols, status/payment_status casts, relations customer/items/shippingZone, Auditable, softDeletes), `OrderItem` model (Money casts price/line_total).
- `Support/OrderNumber` generator — format `FNB-YYYYMMDD-XXXX`, collision-safe.
- `DTOs/PlaceOrderData` (spatie/laravel-data): items[{product_id, qty}], customer{name, mobile, email?}, shipping_zone_id, address, ip, user_agent, notes?.
- `Actions/PlaceOrder`: DB transaction → resolve customer (3.2) → load products → **snapshot** title/sku/price per item → compute line_total, subtotal, shipping (from zone), total, advance_paid (per advance-payment rule) → persist order + items → capture ip/ua → return Order. Audit-logged.
- `Support/AdvancePayment` calculator (full → total; partial percentage → total×%; partial amount → fixed).
**RED tests:**
- [U] line_total = price × qty (paisa, no float drift).
- [U] subtotal = Σ line_totals; total = subtotal + shipping_cost.
- [U] advance: `full` → total; `partial/percentage` (e.g. 30%) → round(total×0.30); `partial/amount` → fixed; non-advance product → advance_paid = 0.
- [U] `OrderNumber` format matches `/^FNB-\d{8}-\d{4}$/` and is unique across 1000 generations.
- [F] `PlaceOrder` persists order + items; **price snapshot survives** later product price change (change product price after order → order_item.price unchanged).
- [F] creates/links customer by mobile; stores `customer_ip` + `user_agent`.
- [F] unknown product id / empty items → throws (no partial write — assert DB rolled back).
- [F] shipping_cost copied from selected zone; total reflects it.
- [F] order_no is unique on concurrent-ish creation (loop create N, all distinct).
- **Decision to confirm with owner:** does a web order **decrement stock**? (catalog/inquiry model historically didn't.) Default plan: **no auto-decrement**; record only. Mark `// TODO(owner)` if undecided.
**DoD:** transaction-safe, audit on create, Money integer-only, Larastan max.

## 3.4 Checkout API (S7 backend) — ⬜
**Artifacts:** `Api\CheckoutController@store` (`POST /api/v1/orders`); `StoreOrderRequest` (Zod mirrored server-side): items required array, each {product_id exists+published, qty 1..n}; customer.name required; customer.mobile BD `+88` 11-digit; address required; shipping_zone_id exists+active; email nullable; honeypot/rate-limit. Maps request → `PlaceOrderData` (inject `ip`, `user_agent` from request) → `PlaceOrder` → `OrderResource` (201). Throttle middleware (e.g. `throttle:orders`).
**RED tests:**
- [F] valid payload → 201, returns order_no + total + items.
- [F] invalid mobile (`12345`, non-BD, wrong length) → 422.
- [F] missing address / empty items / unknown product / inactive zone → 422.
- [F] unpublished/disabled product rejected.
- [F] captures real client IP + UA into the order.
- [F] rate limit: N+1 rapid requests → 429.
- [F] no auth required (public) but no IDOR — cannot set arbitrary customer_id/order_no/total from input (server computes totals; ignores client-sent money).
**DoD:** server recomputes all money (never trusts client amounts); rate-limited; validation complete.

## 3.5 Invoice PDF (M7) — ⬜
**Artifacts:** `Actions/GenerateInvoicePdf` (barryvdh/laravel-dompdf); invoice Blade view (branding: logo/site_name/address from settings); access via **signed/expiring URL** keyed to order_no (storefront success page) **and** admin (`orders.view`). `InvoiceController@show`.
**RED tests:**
- [F] returns `application/pdf`, non-empty body, filename has order_no.
- [F] PDF generation path uses order snapshot data (totals/items match order).
- [F] unsigned/expired link → 403/404 (no IDOR — can't fetch another order's invoice by guessing id).
- [F] admin with `orders.view` can fetch any invoice; staff without it → 403.
**DoD:** no public enumeration of invoices; authz enforced.

## 3.6 Admin Orders API + management (M7) — ⬜
**Artifacts:** `OrderRepository::adminPaginate(filters)` (status, mobile, customer name, address LIKE, date range, search, sort); `Admin\OrderController` (`index`, `show`, `updateStatus`); `UpdateOrderStatusRequest` (orders.manage) with **allowed transition map** (e.g. pending→confirmed→processing→shipped→delivered; any→cancelled; delivered→returned; reject illegal jumps); audit-logged status change; `OrderResource`/`OrderDetailResource`.
**RED tests:**
- [F] index filters by status / mobile / name / date range; sorts; paginates.
- [F] `orders.view` can list + view; cannot change status (403 on updateStatus).
- [F] `orders.manage` can change status on a legal transition.
- [F] **illegal transition rejected** (e.g. delivered→processing) → 422.
- [F] status change is **audit-logged** (actor + from→to + IP).
- [F] show enforces existence (404) — no info leak.
**DoD:** transition rules unit-coverable; audit on every change; authz split view/manage.

### Phase 3 backend EXIT GATE (before any Phase 3 frontend)
All of 3.1–3.6 green · Larastan max · Pint · API resources stable · `openspec/changes/phase-3-orders` archived · `v0.2.0` candidate.
**Then** frontend: admin Orders pages (Inertia, on the component kit) + storefront checkout/success (S7/S8).

---

# PHASE 4 — Payments / Customer Auth / SMS / Courier (backend) `feat/phase-4-integrations`

> All integrations behind `Support/*` interfaces; **faked in tests**; secrets in encrypted settings — never in client bundle.

## 4.1 Customer OTP auth (S10/M28) — ⬜
**Artifacts:** `otp_codes` migration (mobile, code **hashed**, expires_at, attempts); `OtpService` (issue, verify, rate-limit, expiry); `Api\Auth\OtpController` (`request`, `verify`) issuing **Sanctum** tokens; throttle.
**RED tests:** [U] OTP hashed (never stored/returned plaintext); [U] expiry + max-attempts lockout; [F] request → issues (faked SMS); [F] verify correct → token; wrong/expired → 422; [F] rate-limit → 429; [F] auto-register customer on first verify.

## 4.2 SSLCommerz (M8) — ⬜
**Artifacts:** `payments` migration (order_id, gateway, amount, type full|partial|shipping, tran_id, val_id, status, raw_payload **encrypted**); `Support/Payments/SslCommerzGateway` interface + impl (dynamic creds from encrypted settings); `Api\Payment\SslController` (init, ipn, success/fail/cancel); idempotent `RecordPayment` action.
**RED tests:** [I] init builds session (faked); [F] **`val_id` verified server-side** — forged/redirect-only success **rejected**; [F] **idempotent**: duplicate IPN/return for same tran_id records once; [F] partial vs full vs shipping amounts; [U] amount reconciliation vs order; [F] secret creds never serialized to JSON/response.

## 4.3 SMS gateway (M9) — ⬜
**Artifacts:** `Support/Sms/SmsGateway` interface (+ null/log driver now, BD adapter later); order-confirmation + OTP send; dynamic creds.
**RED tests:** [I] order confirmed → SMS dispatched (fake) with order_no; [U] provider-agnostic contract; [F] failure is non-fatal (order still succeeds, logged).

## 4.4 SteadFast courier (M10) — ⬜
**Artifacts:** `shipments` migration; `Support/Courier/SteadFast` interface+impl (dynamic creds); create consignment from order, fetch tracking.
**RED tests:** [I] create consignment stores consignment_id/tracking_code (faked); [F] authz orders.manage; [F] tracking status fetch maps to model.

## 4.5 SMTP (M11) — ⬜
**Artifacts:** dynamic SMTP settings (encrypted), test-send action, transactional order mail (when email present).
**RED tests:** [F] settings save (secret encrypted at rest, masked in response); [I] test-send uses configured transport (Mail::fake); [F] order mail queued when email present, skipped otherwise.

---

# PHASE 5 — Marketing / SEO / Analytics (backend) `feat/phase-5-marketing`

## 5.1 Public analytics IDs (M14) — ⬜
**Artifacts:** settings group `marketing` (GTM, GA4, Pixel, Clarity = **public IDs**; CAPI token = **secret**). Public `GET /api/v1/marketing` returns **public IDs only**.
**RED tests:** [F] endpoint returns public IDs; [F] **CAPI token never present** in any public response/bundle; [F] settings.manage to edit.

## 5.2 Meta CAPI server-side (M14) — ⬜
**Artifacts:** `Support/Capi/ConversionApi` interface+impl (token server-side); event dedup (event_id shared with pixel); ViewContent/InitiateCheckout/Purchase.
**RED tests:** [I] Purchase sent server-side on paid order (faked) with dedup id; [U] payload mapping; [F] token never leaves server.

## 5.3 Visitor tracking (M16) — ⬜
**Artifacts:** `visitors` migration (session, ip, ua, path, referrer, utm, ts); capture middleware/endpoint; link visitor→order by IP where possible.
**RED tests:** [F] visit recorded; [F] privacy: no PII beyond disclosed; [U] UTM parse.

## 5.4 SEO (M13) — ⬜
**Artifacts:** global SEO defaults + `seo_meta` polymorphic overrides; sitemap.xml + robots.txt generators; JSON-LD (Product, Breadcrumb) data builders; dynamic OG thumbnail resolver (home→banner, product/category→own image).
**RED tests:** [F] sitemap lists published products/categories only; [F] robots.txt content; [U] JSON-LD shape (Product/Breadcrumb); [U] OG fallback chain.

## 5.5 Product feed (M15) — ⬜
**Artifacts:** scheduled CSV/feed export (league/csv) for Meta/Google; feed endpoint (price, availability, image, SKU, link).
**RED tests:** [F] feed includes published+in-stock mapping; availability reflects stock logic; [U] row schema; [F] only published exposed.

---

# PHASE 6 — Settings surfaces & hardening (backend) `feat/phase-6-hardening`

## 6.1 Gateway/settings editors (M12) — ⬜ (mostly admin UI; backend = encrypted setting save/test endpoints per gateway)
**RED tests:** [F] each gateway settings save → secrets encrypted at rest + masked on read; [F] settings.manage gate; [F] test-send/verify endpoints behind auth.

## 6.2 Owner Maintenance Lock (M19) — ⬜
**Artifacts:** `license`/`maintenance` settings group; owner-only actions: enable maintenance (storefront returns maintenance page via API flag), global session revocation, key rotation — **reversible, never deletes files**; audit-logged.
**RED tests:** [F] owner-only (others 403); [F] enabling sets storefront flag; [F] reversible (disable restores); [F] every action audit-logged; [U] **no filesystem-deletion call exists** (guard test / static assertion).

## 6.3 Security pass — ⬜
**Artifacts:** rate limits on public/order/OTP/payment; security headers (CSP/HSTS) middleware; CORS locked to storefront origin; `composer/npm audit`; Larastan max; load/perf check; backup strategy doc.
**RED tests:** [F] CORS rejects non-storefront origin; [F] security headers present; [F] throttled endpoints return 429; [F] no `NEXT_PUBLIC_*` secret + no secret in any API resource (sweep test).

---

## Cross-cutting test conventions
- One `*UiTest` (Inertia/admin) or `*ApiTest` (storefront JSON) per controller; `*ServiceTest`/`*ActionTest` for logic; pure `*Test` units for Money/normalizers/calculators.
- `Storage::fake('public')` for uploads; `Mail::fake()`, `Http::fake()`, and interface fakes for integrations.
- Seed `PermissionRoleSeeder` in `beforeEach`; use role helpers (`owner/admin/manager/sub-admin`) to assert the RBAC matrix (MASTER-PLAN §6).
- Money asserted in **minor units** (`->toMinor()`), never floats.
- Every `:id`/public endpoint gets an explicit **IDOR / authz** test.

## Build order (backend-first)
1. Finish **Phase 3 backend** (3.1→3.6) fully green → exit gate.
2. Then Phase 3 **frontend** (admin Orders + storefront checkout/success).
3. Repeat per phase (4 → 5 → 6): **all backend first, tested**, then frontend.
