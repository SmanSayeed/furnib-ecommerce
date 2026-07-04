# SSLCommerz Payment Integration

How online payments work in furnib-ecommerce, how to configure sandbox/live, and
the security guarantees. SSLCommerz is the hosted checkout — card/mobile-wallet
details never touch our servers.

---

## 1. Players & files

| Player | Responsibility | Key files |
|---|---|---|
| Storefront (Next) | "Pay online / Pay advance" buttons; result page | `app/checkout/success/page.tsx`, `app/checkout/result/page.tsx`, `app/api/payment/init/route.ts` (proxy) |
| Backend (Laravel) | Open session, verify + record payment | `Api/Payment/SslController.php`, `Actions/Payments/RecordPayment.php` |
| Gateway abstraction | Talk to SSLCommerz; faked in tests | `Support/Payments/PaymentGateway.php` (interface), `SslCommerzGateway.php` (real), `FakePaymentGateway.php` (tests) |
| Credentials | Encrypted store_id / store_passwd + sandbox flag | `Settings/IntegrationSettingController.php`, admin `settings/integrations.tsx` |

Binding: `PaymentGateway => SslCommerzGateway` in `RepositoryServiceProvider`.

---

## 2. End-to-end flow

```
Shopper clicks "Pay online"  (checkout/success)
  → POST /api/payment/init  (Next proxy → Laravel /api/v1/payment/ssl/init)
      1. Create a pending Payment row (unique tran_id = FNBPAY-…)
      2. SslCommerzGateway::initSession() → SSLCommerz GatewayPageURL
      3. Browser redirects to the SSLCommerz hosted page
  → Shopper pays (card / bKash / Nagad …)
  → SSLCommerz calls our callbacks:
      • success_url / fail_url / cancel_url  (browser-facing, POST + redirect)
      • ipn_url                              (server-to-server WEBHOOK, no browser)
  → SslController::finalize()
      1. verifyCallback()  — verify_sign hash (cheap authenticity pre-check)
      2. validatePayment(val_id) — SERVER-SIDE call to SSLCommerz validation API
      3. RecordPayment — reconcile amount + currency + tran_id, idempotent
      4. Order → payment_status = paid/partial. The order stays **pending** —
         payment never auto-confirms it; an admin confirms manually.
  → Browser lands on storefront /checkout/result?status=…&order=…
```

**Webhook = IPN.** `ipn_url` is the reliable server-to-server notification: it
arrives even if the shopper closes the tab. `success_url` is the browser return
(nice UX) but must never be the only path — both run through the same validated
`finalize()`, and the idempotency guard means the double-fire counts once.

### Recovery net — reconciliation sweep (the "both callbacks lost" case)

Rare but real: the bank captured the money, but the **browser callback AND the
IPN were both lost** (or the validation API blipped at that exact moment). The
Payment row is stuck `pending`/`failed` while money actually moved — a
false-negative. A scheduled sweep closes this gap:

```
Schedule (routes/console.php): ReconcilePendingPayments  → every 5 min
  → PendingPaymentReconciler::sweep()
      for each sslcommerz Payment still `pending`, older than a 5-min grace
      and within 72h:
        SslCommerzGateway::queryTransaction(tran_id)   ← Transaction Query API
          • VALID/VALIDATED → RecordPayment (idempotent, re-validates amount+
            currency+tran_id) → order reconciled
          • FAILED/CANCELLED/EXPIRED → mark the row failed with a note
          • no record yet / still processing → leave pending for the next sweep
          • query API throws (transport error) → NEVER mark failed (that would
            be the very false-negative we guard against) — retry next sweep
```

- Uses SSLCommerz' **Transaction Query API**
  (`/validator/api/merchantTransIDvalidationAPI.php`), which looks a transaction
  up by *our* `tran_id` even when no callback ever arrived.
- Recording still goes through `RecordPayment`, so a late IPN + the sweep can
  both fire and the money is applied exactly once.
- The sweep is a queued, unique Job → needs the **queue worker + scheduler**
  running (see SERVER-OPS-GUIDE §"Background workers"). Without them, IPN still
  works; you just lose the automatic recovery of the double-lost case.

Full failure-mode table (A–I): see `PAYMENT-COURIER-HANDOVER.md` §4.

### Two payment entry points

| Product needs an advance? | Where payment starts | Payment is |
|---|---|---|
| **Yes** (`advance_amount > 0`) | **Checkout page** — on "Place order & pay advance", the order is created then the browser goes straight to SSLCommerz | **Mandatory** (charges `type=partial` = the order's `advance_amount`) |
| **No** | **Success page** — "Pay online" button | **Optional** (full amount; COD otherwise) |

The decision uses the server-computed `advance_amount` on the placed order
(`CheckoutForm.startAdvancePayment`). If the gateway can't open, the shopper is
sent to the success page to retry — the order is already saved.

---

## 3. Security guarantees

1. **Never trust the redirect.** Money is recorded only after `validatePayment()`
   re-checks the transaction server-side with the secret store credentials
   (`SslCommerzGateway::validatePayment`).
2. **Reconciliation.** A `VALID` transaction is still rejected unless `tran_id`,
   `amount` and `currency` (BDT) all match the order (`RecordPayment`).
3. **verify_sign.** Each callback carries an md5 signature over the `verify_key`
   fields + `md5(store_passwd)`; `verifyCallback()` rejects a tampered/forged POST
   before we make any outbound call. (Absent signature → fall back to the
   authoritative `validatePayment()`.)
4. **Idempotent.** A duplicate IPN/return applies the money once
   (`lockForUpdate` + status guard).
5. **Secrets stay server-side.** `store_passwd` is encrypted, write-only, never
   returned to the browser; the raw gateway payload is stored `encrypted:array`.
6. **No open redirect.** The result URL is built from the trusted
   `config('app.frontend_url')`, never from shopper input.
7. **Rate limited.** `POST /payment/ssl/init` is behind `throttle:orders`.
8. **Callbacks are stateless API routes** (CSRF-exempt) so SSLCommerz can POST.

Optional extra hardening (not enabled by default): IP allow-list the IPN route to
SSLCommerz IPs — sandbox `103.26.139.87`, live `103.26.139.81`, `103.132.153.81`.

---

## 4. Configuration

### Admin → Settings → Integrations → SSLCommerz
- **Store ID / Store Password** — from your SSLCommerz merchant/sandbox account.
- **Mode** — Sandbox (test, no real money) or Live.
- Password is write-only: leave blank to keep the saved one.

### Backend `.env`
```
APP_URL=https://api.your-domain.com        # callbacks are built from this — must be public HTTPS
FRONTEND_URL=https://your-storefront.com    # where the shopper is redirected after paying
```
`FRONTEND_URL` falls back to `APP_URL` if unset.

### Endpoints & base URLs (handled automatically)
| | Sandbox | Live |
|---|---|---|
| Base | `sandbox.sslcommerz.com` | `securepay.sslcommerz.com` |
| Session | `/gwprocess/v4/api.php` | same |
| Validation | `/validator/api/validationserverAPI.php` | same |

Selected by the **Mode** toggle (`SslCommerzGateway::baseUrl()`).

---

## 5. Session parameters we send

All SSLCommerz v4 **mandatory** fields are sent (`SslCommerzGateway::initSession`):
store_id, store_passwd, total_amount, currency (BDT), tran_id, success/fail/cancel/ipn
URLs, product_name/category/profile, cus_name/**cus_email**/cus_phone, and the
shipping block (`shipping_method=YES`, num_of_item, ship_name/add1/city/postcode/country).

- **cus_email** — orders are phone-first (no email), so it falls back to the
  store's `contact_email`, then `orders@<app-host>`. Never empty (gateway needs it).
- **value_a** — set to `order_no`, echoed back on every callback for a reliable handle.

---

## 6. Testing in sandbox (on the VPS)

SSLCommerz **cannot reach `localhost`** — callbacks need a public HTTPS URL, so
test on the deployed VPS (or an ngrok tunnel locally).

1. Set `APP_URL` (public backend HTTPS) and `FRONTEND_URL` (storefront). Rebuild.
2. Admin → Integrations: enter sandbox Store ID + Password, Mode = **Sandbox**.
3. Place an order, go to the success page, click **Pay online**.
4. On the SSLCommerz page use a test card:
   - VISA `4111111111111111`, exp `12/26`, CVV `111`, OTP `111111`.
5. Expect: redirect to `/checkout/result?status=success`, and the order shows
   `payment_status = paid` (Admin → Orders / Payments).
6. Try the **cancel** button on the gateway → lands on `status=cancelled`, order
   stays unpaid (COD still available).

### Validation status meanings
- `VALID` — successful transaction.
- `VALIDATED` — successful, already validated once (still accepted).
- `INVALID_TRANSACTION` / others — rejected; payment marked failed, order untouched.

---

## 7. Going live

1. Get **live** Store ID + Password from SSLCommerz (merchant account approved).
2. Admin → Integrations: enter them, set Mode = **Live**.
3. Confirm `APP_URL` + `FRONTEND_URL` point to the real public HTTPS domains.
4. (Recommended) Register the `ipn_url` in the SSLCommerz merchant panel too.
5. Do one small real transaction end-to-end before announcing.

---

## 8. Tests

`tests/Feature/Payments/SslCommerzPaymentTest.php` covers: session open, forged
success rejected, amount/currency mismatch rejected, verify_sign rejection,
genuine full/partial/shipping payments, idempotency, browser redirect on
success/fail/cancel, unknown transaction, and secret-leak protection.

`tests/Feature/Payments/PendingPaymentReconcilerTest.php` covers the recovery
sweep: recovers a genuinely-paid-but-lost transaction, stays idempotent against a
late IPN, rejects an amount mismatch, marks dead statuses failed, leaves
"no record yet" and in-grace rows pending, and never marks failed on a query API
transport error.

The real HTTP gateway is swapped for `FakePaymentGateway`, so tests never hit the
network.
