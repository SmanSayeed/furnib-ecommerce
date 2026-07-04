# SMS Integration Plan — Automas (asms.automas.com.bd)

Transactional order-status SMS to customers (confirmed / shipped / delivered /
cancelled / returned), provider-agnostic and BTRC-compliant. This is the
**implementation spec**; code follows once approved (TDD).

> **Provider docs:** `docs/sms-gateway/code-examples.txt` (API + status codes) and
> the panel at <https://asms.automas.com.bd/developer>. context7 is only for public
> library docs, so it does not cover this vendor — the local API doc is the source
> of truth.

---

## 0. ⚠️ Security first — the exposed key

`docs/sms-gateway/code-examples.txt` contains a literal `apikey=…` value.
Treat it as a secret:
- If that is **your real account key**, regenerate it in the Automas panel now.
- **Never commit the real key.** It lives only in **encrypted DB settings**
  (`sms.api_key`), like the SSLCommerz/Steadfast keys — never in repo/env-in-repo/
  client bundle/logs.
- The `docs/sms-gateway/` folder is currently untracked; scrub any real key before
  committing, or keep it gitignored.

---

## 0b. One SMS per order + self-service pay link (2026-07-04)

To avoid double SMS charges, an order now sends **exactly one** SMS by default:
- The legacy English "order received" SMS (`SendOrderConfirmation`) was removed —
  that action now sends **email only**.
- Order placement dispatches `OrderNotificationEvent::Placed` (the only event ON by
  default; confirmed/shipped/delivered/cancelled/returned default OFF). Its Bangla
  template carries a **`{pay_url}`** — a signed, self-service payment link.
- `PayLink` builds `{frontend}/pay/{order_no}?t={HMAC token}`. The token is an
  HMAC of the order_no keyed by `APP_KEY`, so the link is unguessable and can't be
  enumerated (no IDOR/PII leak).
- Storefront **`/pay/{order_no}`** page reads the summary via
  `GET /api/v1/pay/{order_no}/summary?t=…` (token-gated) and offers two SSLCommerz
  buttons: **Pay delivery charge** (`type=shipping`) and **Pay full amount**
  (`type=full`). Nothing due → "fully paid".
- Placeholders now include `{pay_url}`. Tests: `PayPageTest`, and
  `OrderConfirmationTest` asserts a single placement SMS + the email.

---

## 1. BTRC compliance (mandatory)

Per the provider's BTRC notice (`docs/sms-gateway/notes.txt`):
- **All non-OTP SMS must be Bangla (Unicode).** Order-status messages are sent in
  **Bangla Unicode** (`smsformat=8`). OTP stays English (machine-generated, exempt).
- **Content must be BTRC-vetted before use.** → Templates are **admin-editable**
  (Settings), so the owner pastes the vetted Bangla text. Sensible Bangla defaults
  ship, but the owner is responsible for vetting.
- Bangla Unicode = **70 chars per SMS segment** — keep templates short (cost).
- Violations can suspend the SMS account (status code `107`).

---

## 2. Automas API (from code-examples.txt)

| Purpose | Method / URL | Params |
|---|---|---|
| Single/Bulk SMS (v3) | `GET/POST https://api.automas.com.bd/smsapiv3` | `apikey`, `sender`, `msisdn`, `smstext`, `smsformat=8` (unicode), `type=long` (multi-segment) |
| Single/Bulk SMS (v4 JSON) | `POST https://api.automas.com.bd/smsapiv4` | body `{api_key, senderid, type, msg, contacts}` |
| Dynamic (per-recipient) | `POST https://api.automas.com.bd/smsapimany` | `{apikey, sender, messages:[{id,msisdn,smstext}]}` |
| Balance | `GET https://api.automas.com.bd/getbalancev3?apikey=` | → `{"response":"500.00"}` |

**Success response:** `{"response":[{"status":0,"id":296334,"msisdn":"01722656280"}]}`
— `status:0` = accepted, `id` = provider message id (store it).

**Status codes:** `0` success · `101` bad length · `102` sender invalid · `103`
auth failed · `104` invalid user · `105` invalid msisdn · `106` wrong api key ·
`107` account suspended · `108` IP not allowed · `109` API not allowed · `110` DND
· `111` spam word · `1000` insufficient balance · `2300/2400` route issue ·
`3300/2000/3000/4000` provider/system error.

**Chosen transport:** **v3** — its Unicode path is unambiguous (`smsformat=8`), and
the flat `apikey/sender/msisdn/smstext` params map cleanly. `msisdn` in `880…`
form (strip the `+` from our E.164). Message must be HTTP-encoded.

---

## 3. Architecture — DRY + SOLID, channel-agnostic (SMS now, Email later)

**One event → many channels.** The trigger, the "which order events notify the
customer" policy, and the per-event content live once. **SMS is the first
channel; adding Email later is a new channel + a `toMail()` method — no new
observer, job, or trigger.** Built on Laravel's **Notification** system, which
exists exactly for "same notification, multiple channels via `via()`".

```
 OrderObserver (thin) ─► SendOrderStatusNotification (job, queued)
                             └► $notifiable->notify(new OrderStatusNotification(event))
                                     │
                          OrderStatusNotification::via($notifiable)
                          reads settings → ['sms']  now   →  ['sms','mail'] later
                                     │
                 ┌───────────────────┴────────────────────┐
                 ▼ toSms()                                 ▼ toMail()  (added later)
        SmsChannel (custom)                        Laravel mail channel
          → SmsService (dedup + log)                 (existing MailConfigurator)
          → SmsGateway (interface)                   → Blade email template
             ├ AutomasSmsGateway (real)
             ├ LogSmsGateway (dev/fallback)
             └ FakeSmsGateway (tests)
```

### Top layer — channel-agnostic (this is what makes email "free" later)

- **`OrderStatusNotification`** *(Laravel Notification)* — ONE class per… no, one
  class total, parameterised by `OrderSmsEvent`. `via()` returns the channels
  enabled in settings for that event; `toSms()` builds the SMS; `toMail()` (added
  later) builds the email. Adding email = implement `toMail()` + flip a setting.
  **Open/Closed:** new channel doesn't touch the trigger.
- **`SmsChannel`** *(custom notification channel)* — the bridge: takes the
  notification's `toSms()` output and hands it to `SmsService`. Registered so
  `via()` can list `'sms'`. Email uses Laravel's built-in `mail` channel — no
  custom code.
- **`Notifiable` customer** — `Customer` uses the `Notifiable` trait;
  `routeNotificationForSms()` = mobile, `routeNotificationForMail()` = email. So
  each channel just asks the customer where to deliver.
- **`NotifiableEvent` enum** *(single source of truth)* — `Confirmed/Shipped/
  Delivered/Cancelled/Returned` → maps to the triggering order statuses + the
  settings keys (per-channel toggle + template). Add an event = one enum case.

### SMS transport layer — reusable on its own (also for OTP)

- **`SmsGateway`** *(interface, exists)* — transport only:
  `send(string $mobile, SmsMessage): SmsResult`. Returns a value object (not a bare
  `bool`) so every provider reports the same shape. **Dependency Inversion.**
  Optional segregated caps (ISP): `SupportsBalance`, `SupportsBulkSms` — a provider
  implements only what it offers.
- **`AutomasSmsGateway`** *(new)* — the only class that knows Automas (v3 request,
  auto `smsformat=8` for Bangla, status-code → `SmsResult`). New provider later =
  new class, **zero caller change**.
- **`SmsMessage`** / **`SmsResult`** *(value objects)* — reused everywhere; no
  duplicated unicode/length or error-shape logic (**DRY**).
- **`SmsService`** *(reusable core)* — dedup + `SmsGateway::send` + persist the log.
  Called by `SmsChannel` **and** directly for OTP/manual resend. Policy/logging in
  one place.
- **`LogSmsGateway`/`FakeSmsGateway`** *(exist)* — Liskov-interchangeable drivers.

### Shared layer — used by every channel

- **`MessageTemplate`** *(renderer)* — `render(string $tpl, array $vars): string`
  with `{name}{order_no}{total}{due}{tracking}`. Reused by SMS, email, OTP, promos.
- **`NotificationLog`** *(model)* — audit + idempotency, with a **`channel`** column
  (`sms`/`email`) so the same table covers SMS now and email later. Unique
  `(order_id, event, channel)`.

### Flow
```
Order status → confirmed/shipped/delivered/cancelled/returned
  → OrderObserver::updated()  (maps status → NotifiableEvent)
      → SendOrderStatusNotification::dispatch(orderId, event)   (queued)
          → customer->notify(OrderStatusNotification(event))
              via() = channels enabled for this event  (['sms'] → later ['sms','mail'])
              per channel: guard (enabled? toggle? has route? already sent?)
                → render template → deliver → NotificationLog::create(channel, ...)
```
Queued → never blocks the admin action; the queue worker already runs
(SERVER-OPS-GUIDE §Background workers).

### Idempotency
Unique `(order_id, event, channel)` in `notification_logs`, checked before each
send — a re-confirm or duplicate fire never double-notifies, per channel. The
guard lives once in the delivery path, not per caller.

> **Why not a bespoke `Notifier`?** Laravel Notifications already give us
> `via()`-based multi-channel fan-out, queueing, and per-notifiable routing for
> free — reinventing it would be less DRY. We only add the thin `SmsChannel`
> bridge; `mail` is built in.

---

## 4. Data model

### `sms_logs` (new table)
| Column | Notes |
|---|---|
| `id` | pk |
| `order_id` | nullable FK (OTP has none) |
| `mobile` | E.164 stored; `880…` sent |
| `event` | `confirmed`/`shipped`/`delivered`/`cancelled`/`returned`/`otp` |
| `message` | rendered text (Bangla) |
| `provider` | `automas` |
| `provider_message_id` | `id`/`sid` from response |
| `status` | `queued`/`sent`/`failed`/`delivered` (delivered set later via DLR) |
| `status_code` | Automas numeric code (0, 110, 1000, …) |
| `error` | short reason on failure |
| `timestamps` | |
| unique | `(order_id, event)` |

### Settings (encrypted where secret) — Admin → Settings → Integrations → SMS
| Key | Secret | Meaning |
|---|---|---|
| `sms.enabled` | no | master on/off |
| `sms.provider` | no | `automas` |
| `sms.api_key` | **yes** | Automas API key |
| `sms.sender_id` | no | approved sender/mask |
| `sms.event_confirmed` … `sms.event_returned` | no | per-event on/off |
| `sms.tpl_confirmed` … `sms.tpl_returned` | no | Bangla templates (editable) |

### Template placeholders
`{name}` `{order_no}` `{total}` `{due}` `{tracking}` — rendered by a small
`SmsTemplate::render($tpl, $order)` helper. Defaults (owner must BTRC-vet):

| Event | Default Bangla template |
|---|---|
| confirmed | `প্রিয় {name}, আপনার অর্ডার #{order_no} নিশ্চিত হয়েছে। ধন্যবাদ - Furnib।` |
| shipped | `আপনার অর্ডার #{order_no} পাঠানো হয়েছে। ট্র্যাকিং: {tracking}। - Furnib` |
| delivered | `আপনার অর্ডার #{order_no} ডেলিভারি সম্পন্ন। কেনার জন্য ধন্যবাদ - Furnib।` |
| cancelled | `আপনার অর্ডার #{order_no} বাতিল করা হয়েছে। প্রশ্ন থাকলে কল করুন - Furnib।` |
| returned | `আপনার অর্ডার #{order_no} ফেরত প্রক্রিয়াধীন। - Furnib` |

---

## 5. Files to add / change

| File | Purpose |
|---|---|
| `app/Support/Sms/AutomasSmsGateway.php` | real driver (v3, unicode auto, encrypted creds, status-code mapping) |
| `app/Providers/RepositoryServiceProvider.php` | bind Automas when configured, else Log |
| `app/Support/Sms/SmsTemplate.php` | placeholder renderer |
| `app/Jobs/SendOrderStatusSms.php` | queued send + sms_logs write |
| `app/Observers/OrderObserver.php` | dispatch SMS job on status change (extend) |
| `app/Models/SmsLog.php` + migration | audit/idempotency |
| `Settings/IntegrationSettingController.php` + admin `integrations.tsx` | SMS card (key/sender/toggles/templates) |
| `tests/Feature/Sms/OrderSmsTest.php` | see §7 |

Balance/DLR (later): `app/Http/Controllers/Api/Sms/DlrController.php` +
`POST /api/v1/sms/dlr` to update `sms_logs.status` to delivered/failed; a small
balance widget reading `getbalancev3`.

---

## 6. Phone normalization

Stored canonical `+8801XXXXXXXXX` (via `MobileNumber`). Automas wants `880…` (or
`01…`). Send `substr(e164, 1)` → `8801XXXXXXXXX`. Invalid/empty → skip + log.

---

## 7. Tests (Pest, FakeSmsGateway — no network)

- Confirm → one Bangla SMS queued to the customer; sms_log row `status=sent`.
- Shipped includes `{tracking}` from the shipment.
- Cancelled / returned send; unrelated changes (e.g. processing) do **not**.
- Event toggle off → no send. `sms.enabled=false` → no send.
- Idempotent: duplicate observer fire → one sms_log, one send.
- Unicode: message flagged `smsformat=8`; message HTTP-encoded.
- Graceful failure: gateway returns `110`(DND)/`1000`(balance) → sms_log
  `status=failed` with code, order flow unaffected (no throw).
- Secret never leaves server (settings mask test, like SSLCommerz).

---

## 8. Rollout

1. Owner adds Automas **API key + approved Sender ID** in Admin → Integrations,
   pastes **BTRC-vetted Bangla** templates, enables the events wanted, `sms.enabled=on`.
2. Ensure balance > 0 (panel shows ৳; `getbalancev3` for a dashboard widget later).
3. Test order → confirm → phone receives the Bangla SMS; check `sms_logs`.
4. **DLR (delivery reports) — implemented.** After the first SMS save, the SMS
   card shows a **Success URL** and **Fail URL** (each carrying a secret token).
   Paste them into the Automas panel → Developer Options → DLR Push Configuration.
   Automas then POSTs/GETs each message's final status back, and we advance the
   matching `notification_logs` row to `delivered` / `undelivered`.

### DLR design (delivered)

> **DLR Push** = Automas's delivery-report webhook (like SSLCommerz IPN, but for
> SMS): a Success URL + Fail URL that Automas calls with each message's outcome.

- **Match key:** the provider message id. `AutomasSmsGateway` implements the
  optional `ProvidesMessageId` capability, so `SmsOrderChannel` records the id in
  `notification_logs.provider_message_id`; the DLR looks the row up by it.
- **Auth:** the URL carries a secret `sms.dlr_token` (generated on first save,
  stored encrypted). `DlrController` compares it with `hash_equals`; a wrong/absent
  token is a generic 404, so the endpoint isn't probeable or spoofable.
- **Routes:** `GET|POST /api/v1/sms/dlr/{token}/{outcome}` (`outcome` = `success`
  |`failed`), rate-limited. Reads the id under any of Automas' likely param names
  (`id`/`sid`/`messageid`/…). Only advances an EXISTING row; never creates one;
  always returns `200 {ok:true}` (no existence leak). Adds `delivered_at`.
- **Tests:** `tests/Feature/Sms/SmsDlrTest.php` — delivered/undelivered by id, bad
  token → 404, unknown id → 200 no-op, invalid outcome → 404.
