# Furnib — Payment + Courier Handover / Context

Living context for: SSLCommerz go-live, payment-failure hardening, and the
Steadfast courier integration. Written so work can resume after a `/compact`.

Language for chat with the owner: **casual Dhakaiya Bangla** (tech terms in English).
PHP CLI in the tool shell is 8.1 — always run artisan/pest/pint with the full 8.3
binary: `/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php.exe`. Admin build needs
8.3 on PATH: `PATH="/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64:$PATH" npm run build`.

---

## 1. Status snapshot (what's DONE + on `master`)

Recent commits (newest first):
- `dbe0612` fix(sslcommerz): send cus_state/cus_postcode/ship_state for live sessions
- `0022b9b` docs: SSLCommerz order advance/shipping test plan (`docs/SSLCOMMERZ-ORDER-TEST-PLAN.md`)
- `ec728af` feat(orders): manual payment ledger (credit/debit + note, reconciler, never touch original)
- `8893926` feat(admin-orders): top bulk toolbar, per-row invoice/label/status, rows-per-page
- `6c9a737` feat(invoice): Advance Paid + Due (COD), 2-per-A4 bulk, advance on labels
- `e81ad64` feat(checkout): advance/COD split, live paid-due success page, order-status API
- `5fd0436` feat(payments): whole-taka advances, taka-entry fixed amount, cancelled status + notes

**Deployed to production (verified 2026-07-04):** BOTH storefront (Next) and
backend/admin (Laravel) are live with all of the above EXCEPT commit `dbe0612`
(the cus_state fix) — that still needs a backend deploy. Order-status route,
manual ledger, orders-table UI, invoice 2-per-A4 all confirmed live.

**Full Pest suite:** 477 tests green (2 skipped). Storefront + admin build green.

### Production E2E test done (playwright, sandbox SSLCommerz) — ALL PASS
- Fixed-amount advance fix verified (admin ৳1000 → stored 100000 paisa).
- Checkout math verified for: none/COD, full+product-wise (qty2), percentage
  (Inside + Outside = the owner's original bug, now FIXED).
- Sandbox gateway charged exactly the advance (৳1,000); return → success;
  order-status API → partial, pending (no auto-confirm), advance_paid ৳1,000.
- Manual ledger: credit ৳500 then debit ৳300 → advance_paid ৳1,200, original
  gateway row untouched, 3-row ledger.
- Invoice PDF + shipping label PDF reflect Advance Paid ৳1,200 / Due (COD) ৳1,880.

### Cleanup left for owner
- Temp product "ZZ TEST Amount Advance (delete me)" → moved to recycle bin.
- **Test order `FNB-20260704-6520`** (ZZ Test Buyer) is a real prod order that
  fired one test Purchase event (Meta/GA4/TikTok). Owner may cancel/delete it.

---

## 2. PENDING FIX #1 — success-page retry when advance unpaid (Case B)

**Status: draft applied to the working tree, NOT committed.** File:
`ecommerce-next-frontend/app/checkout/success/page.tsx` (lints clean).

**Problem:** If the gateway session fails to OPEN (backend→gateway init error, or
the browser never reaches SSLCommerz), the shopper lands on `/checkout/success`
with the order saved but the advance UNPAID. The Phase-A success page hid the pay
buttons whenever an advance was *required*, so it wrongly showed "Advance
received — ৳0 paid online" with no way to retry.

**Fix (already in the file):** distinguish *required* from *settled* using the
LIVE order-status (`advance_paid.minor > 0`), since the sessionStorage snapshot is
always pre-payment (advance_paid = 0):
- `advanceSettled` → green "Advance received …" (no pay button).
- `advanceRequired && !advanceSettled` → amber "Advance to pay now ৳X" + a
  **"Pay advance now — ৳X"** button calling `pay("partial")`, plus "Your order is
  saved. Complete the advance to confirm it."
- `!advanceRequired` (pure COD) → optional "Pay online — {total}".

**To ship:** `npm run build` storefront, deploy, verify. Then commit.

---

## 3. PENDING FIX #2 — reconciliation queue job (Case I)

**Not started.** A rare but real gap: the bank captured the payment but our
`validatePayment` call failed at that moment (or an IPN was missed) → the txn is
marked failed while money actually moved (false-negative).

**Plan:** a queued/scheduled reconciliation sweep:
- Find Payment rows still `pending` (or `failed` within a short window) older than
  N minutes that have a `val_id`/gateway reference.
- Re-call SSLCommerz `validatePayment`; if now VALID + amount/currency match →
  run `RecordPayment` (idempotent, so safe) → advance_paid updates.
- Log what it reconciled; never double-apply (unique tran_id + already-success
  guard already protect us).
- Schedule every few minutes via the Laravel scheduler; make the sweep a Job so
  it retries on transient errors.

**Why a queue here (and NOT for the core record):** transaction security comes
from DB transaction + `lockForUpdate` + idempotency + server-side validation —
all already in `RecordPayment`. The core money-record stays SYNCHRONOUS (we must
answer SSLCommerz immediately and keep state consistent). Queues are for
resilience/side-effects: reconciliation sweep, SMS/email receipts, analytics,
and (later) courier push.

---

## 4. Payment-failure corner cases (reference)

| # | Failure point | Behaviour | Safe? |
|---|---|---|---|
| A | Order create fails | DB rollback, no order, retry | ✅ |
| B | Order saved, gateway init fails | order pending/unpaid; success page must offer retry | ⚠️ Fix #1 |
| C | Shopper abandons at gateway | order stays pending/unpaid; follow-up | ✅ |
| D | Paid, browser callback lost | **IPN records it** (server-to-server, retried) | ✅ |
| E | Browser + IPN both delayed | IPN retries; else admin manual ledger credit | ✅ eventual |
| F | Duplicate callback/IPN | already-success guard + unique tran_id → idempotent | ✅ |
| G | Amount/currency tamper | validatePayment mismatch → failed + note | ✅ |
| H | Forged callback POST | verify_sign + server-side val_id validation required | ✅ |
| I | Bank paid but validation API down | may false-negative | ⚠️ Fix #2 (reconciliation) |

**IPN / webhook:** a webhook is a URL an external service POSTs to server-to-server
on an event (no browser). SSLCommerz's is called **IPN** (`ipn_url` →
`POST /api/v1/payment/ssl/ipn`). We use it — it's the main safety net for Case D/E.

---

## 5. SSLCommerz — how to go LIVE (owner action)

Code is correct for live (verified against SSLCommerz v4 docs via context7):
- Base URL switches on the `sslcommerz.sandbox` setting: sandbox
  `sandbox.sslcommerz.com` / live `securepay.sslcommerz.com`.
- Session `POST /gwprocess/v4/api.php`; validation `GET
  /validator/api/validationserverAPI.php`. Acceptance follows the docs' Security
  Check Points (tran_id in DB, amount match, currency BDT, status VALID/VALIDATED).
- `dbe0612` adds the mandatory `cus_state / cus_postcode / ship_state` (sandbox is
  lenient; live can reject/risk-flag without them).

**Credentials to add in admin** → `admin.furnib.com/settings/integrations`
(Settings → Integrations, SSLCommerz card). There are exactly two secrets plus a
mode toggle:
1. **Store ID** — the LIVE store id (not sandbox `testbox`).
2. **Store Password** — the LIVE store password (not sandbox `qwerty`).
3. **Mode** — switch the Sandbox/Live radio to **Live**, Save.

Then: deploy `dbe0612`, ensure `APP_URL` is the public https domain (callbacks
already reached us in sandbox → fine), and do ONE small real order to confirm
`/checkout/result?status=success` and admin `advance_paid`. Min live amount is
৳10 BDT — our advances are always higher. Keys stay encrypted in DB, never repo/client.

---

## 6. Steadfast courier — integration plan (dynamic + SOLID)

Official docs (packzy portal): base `https://portal.packzy.com/api/v1`; auth
headers `Api-Key`, `Secret-Key`, `Content-Type: application/json`.

**Endpoints:**
- `POST /create_order` — invoice(unique), recipient_name/phone/address,
  cod_amount, note, item_description, delivery_type (0=home,1=hub).
- `POST /create_order/bulk-order` — max 500, JSON array.
- `GET /status_by_cid/{id}` · `/status_by_invoice/{invoice}` ·
  `/status_by_trackingcode/{code}`.
- `GET /get_balance`.
- Returns: `POST /create_return_request`, `GET /get_return_request/{id}`,
  `/get_return_requests`.
- `GET /payments`, `/payments/{id}`, `/police_stations`.
- Create returns `consignment_id`, `tracking_code`, `status`.
- `delivery_status` enum: pending, in_review, delivered_approval_pending,
  partial_delivered_approval_pending, delivered, partial_delivered,
  cancelled_approval_pending, cancelled, hold, unknown.
- NO official webhook (must POLL), NO official fraud endpoint, no documented rate limit.

**Architecture — mirror the PaymentGateway pattern:**
```
CourierGateway (interface): createConsignment, bulkCreate, status, balance, returnRequest
  └── SteadfastCourier (impl)   ← now
  └── PathaoCourier / RedxCourier ← later, no caller change
```
- Provider + `Api-Key`/`Secret-Key` in **encrypted settings** (like SSLCommerz),
  never repo/client. Admin settings card under Integrations.
- `Consignment` model: order_id, provider, consignment_id, tracking_code,
  courier_status, cod_amount, timestamps.
- Trigger: order **confirmed** → event → `PushToCourier` action (queued, retryable)
  → `CourierGateway::createConsignment`. `invoice = order_no` (unique → idempotent);
  `cod_amount = due = total − advance_paid` (server-computed, matches our ledger).
- Admin: bulk push from the orders table (bulk toolbar already exists, max 500).
- Status sync: scheduled command polls pending consignments → map Steadfast status
  → our order status (shipped/delivered/cancelled/returned) → optional SMS.
- On delivered/partial_delivered → reconcile (COD collected).
- Storefront: show tracking code / track link.
- Balance widget on dashboard.

**Own fraud / return-ratio system (the "trick" — no official API):**
- **Trick A (build it ourselves, free, recommended):** we already poll final
  `delivery_status`. Accumulate per **customer phone**:
  `customer_courier_stats(phone): total_sent, delivered, cancelled, returned`,
  `fraud_score = (cancelled+returned)/total_sent`. On a new order show the phone's
  history to admin ("5 sent, 3 cancelled ⚠️"); high-risk phones → force advance
  payment / manual review. Over time this becomes our own fraud DB.
- **Trick B (optional):** third-party BD courier fraud-check aggregators
  (Pathao+Steadfast+RedX combined success/return ratio by phone). Add behind a
  `FraudChecker` interface as an adapter — provider-agnostic. Start with A, add B later.

**Security to enforce:** cod_amount server-side only (never client); keys encrypted;
status polling verified server-side; don't log PII (phone/address); idempotent create.

**Next step:** write OpenSpec under
`docs/feature-plan/openspec/changes/courier-steadfast/` (endpoint mapping, DB
schema, status state-machine, fraud-score design), then TDD build.

---

## 7. Test scenarios (SSLCommerz) — see also docs/SSLCOMMERZ-ORDER-TEST-PLAN.md

Advance/shipping matrix (whole-taka, half-up, no poysha):
- No advance (COD) → advance 0, due = total, button "Place order".
- Full → advance = total (subtotal + shipping), due 0.
- Partial percentage(p) → round(subtotal×p/100) whole taka.
- Partial fixed amount(a) → min(a, subtotal), a stored as whole-taka paisa.
- Partial shipping → advance = zone shipping (needs a zone).
- Product-wise shipping → zone.base + product.extra_per_unit × qty (per zone).
Edge: percentage .5 rounds up; partial-shipping with no zone blocks; full with no
zone → advance = subtotal. Failure/ledger cases: see §4 and the manual-ledger tests.

Gateway sandbox test card: VISA `4111 1111 1111 1111`, exp `12/26`, CVV `111`,
OTP `111111`, then click **Success**. NEVER complete a real payment on LIVE keys.

---

## 8. Immediate TODO after resume
1. Apply/deploy Fix #1 (success retry) — build storefront, verify, commit.
2. Deploy `dbe0612` to backend before going live.
3. Owner adds live SSLCommerz creds + flips to Live; do one small real order.
4. Build Fix #2 (reconciliation queue job) — TDD.
5. Steadfast: write OpenSpec + docs, then implement dynamically with fraud system.
