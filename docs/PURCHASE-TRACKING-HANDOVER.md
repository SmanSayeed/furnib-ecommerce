# Purchase Tracking — Code Hand-over Notes

> Read this in VS Code with the repo open. It maps the **whole `purchase`
> code path**: which file runs, in what order, which URLs get hit, and the exact
> request payloads. Open the files listed in the **File map** (§7) as you read.
>
> **সহজ কথায় (owner):** order confirm করলে backend ৩টা company-তে (Meta/TikTok/GA4)
> সরাসরি purchase পাঠায়, আর admin browser-এর dataLayer-এও দেয় (GTM-এর জন্য)। নিচে
> কোন কোড কী করে সব ধরে ধরে লেখা।

---

## 0. One-glance flow

```
Admin clicks "Confirm" (admin.furnib.com)
   │  PUT /admin/orders/{order}/status   (status = confirmed)
   ▼
Admin\OrderController::updateStatus()                    ← §1
   │  status becomes "confirmed"
   ▼
ConfirmOrderPurchase::handle($order)   (idempotent, fires ONCE)   ← §2
   ├── SendPurchaseEvent   → Meta CAPI     → graph.facebook.com   ← §3a
   ├── SendTiktokPurchase  → TikTok Events → business-api.tiktok  ← §3b
   ├── SendGa4Purchase     → GA4 MP        → google-analytics.com ← §3c
   └── sets orders.marketing_purchase_sent_at = now()  (never refire)
   │
   ▼  (back in updateStatus) Inertia::flash('purchase', <payload>)
   ▼
Admin browser: use-flash-datalayer.ts pushes to window.dataLayer   ← §4
   ▼
GTM (web container GTM-T373ND86) fires the browser Purchase tags
```

**Two independent paths, one shared `event_id` (`purchase.<order_no>`):**
- **Server path** (§3) — backend → **directly** to Meta/TikTok/GA4. Ad-block proof.
- **Browser path** (§4) — dataLayer → GTM → pixels (and optionally the marketer's
  server-GTM / Stape). Managed by the marketer.

Because both use the same `event_id`, the platforms **de-duplicate → count once**.

> ⚠️ **Important:** the backend does **NOT** hit the marketer's sGTM (Stape)
> server. sGTM belongs to the *browser* path and is configured inside GTM. Our
> code talks to the platform APIs directly (the URLs in §3).

---

## 1. Order-confirm controller

**File:** `laravel-backend/app/Http/Controllers/Admin/OrderController.php` → `updateStatus()`
**Route:** `PUT /admin/orders/{order}/status` (name `orders.status`, middleware `permission:orders.manage`)

```php
public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
{
    $status = (string) $request->validated()['status'];

    if (! $order->canTransitionTo($status)) {           // guard illegal transitions
        throw ValidationException::withMessages([...]);
    }

    $order->update(['status' => $status]);

    // Conversion point: only when it BECOMES "confirmed", and only if the
    // idempotent guard says this is the first time (handle() returns true).
    if ($status === 'confirmed' && $this->confirmPurchase->handle($order)) {
        Inertia::flash('purchase', [                    // → browser dataLayer (§4)
            'event' => 'purchase',
            'event_id' => 'purchase.'.$order->order_no,
            ...OrderTrackingPayload::for($order),       // ecommerce + user_data + order_info
        ]);
    }

    Inertia::flash('toast', [...]);
    return back();
}
```

`$this->confirmPurchase` is injected via the constructor
(`private readonly ConfirmOrderPurchase $confirmPurchase`).

---

## 2. The orchestrator (idempotent, fires all 3 platforms)

**File:** `laravel-backend/app/Actions/Marketing/ConfirmOrderPurchase.php`

```php
public function handle(Order $order): bool
{
    if ($order->marketing_purchase_sent_at !== null) {
        return false;                                    // already sent → never refire
    }

    $this->metaPurchase->handle($order);                 // §3a  (each swallows its own errors)
    $this->tiktokPurchase->handle($order);               // §3b
    $this->ga4Purchase->handle($order);                  // §3c

    $order->forceFill(['marketing_purchase_sent_at' => now()])->save();
    return true;                                         // true = fired on THIS call
}
```

Also called from `RecordPayment::reconcileOrder()` when an **online payment**
auto-confirms the order — same idempotent guard, so Meta counts once even if a
payment callback and a manual confirm both happen.

---

## 3. The three senders — URL hit + request payload

Each `Send*Purchase` action builds a `*UserData` (hashes PII) + an event via a
`*Events` builder, then calls the HTTP client. All are **non-fatal** (wrapped in
`try/catch`, log a warning) and **no-op** (return `false`) if creds are missing.

### 3a. Meta Conversions API
- Action: `app/Actions/Marketing/SendPurchaseEvent.php`
- Event: `app/Support/Capi/CapiEvents.php` → `purchase()`
- HTTP: `app/Support/Capi/MetaConversionApi.php` → `send()`
- **URL hit:** `POST https://graph.facebook.com/v19.0/{fb_pixel_id}/events`
- **Payload:**
```json
{
  "access_token": "<fb_capi_token>",
  "test_event_code": "<optional>",
  "data": [{
    "event_name": "Purchase",
    "event_id": "purchase.FNB-20260630-6943",
    "event_time": 1782830839,
    "action_source": "website",
    "event_source_url": "<referer or frontend_url>",
    "user_data": {
      "em": "<sha256 email>", "ph": "<sha256 phone, digits e.g. 8801...>",
      "client_ip_address": "103.96.71.120", "client_user_agent": "...",
      "fbp": "<_fbp>", "fbc": "<_fbc>"
    },
    "custom_data": {
      "currency": "BDT", "value": "6817.00", "content_type": "product",
      "content_ids": ["<sku>"], "contents": [{"id":"<sku>","quantity":1,"item_price":"6667.00"}],
      "num_items": 1, "order_id": "FNB-20260630-6943"
    }
  }]
}
```
- Needs settings: `fb_pixel_id` **and** `fb_capi_token` (both, else no-op).

### 3b. TikTok Events API
- Action: `app/Actions/Marketing/SendTiktokPurchase.php`
- Event: `app/Support/Tiktok/TiktokEvents.php` → `purchase()`
- HTTP: `app/Support/Tiktok/HttpEventsApi.php` → `send()`
- **URL hit:** `POST https://business-api.tiktok.com/open_api/v1.3/event/track/`
  (header `Access-Token: <tiktok_access_token>`)
- **Payload:**
```json
{
  "event_source": "web",
  "event_source_id": "<tiktok_pixel_id>",
  "test_event_code": "<optional>",
  "data": [{
    "event": "CompletePayment",
    "event_id": "purchase.FNB-20260630-6943",
    "event_time": 1782830839,
    "user": {
      "email": "<sha256>", "phone": "<sha256 of +8801...>",
      "external_id": "<sha256 customer_id>", "ip": "103.96.71.120",
      "user_agent": "...", "ttp": "<_ttp>", "ttclid": "<ttclid>"
    },
    "page": { "url": "<url>" },
    "properties": {
      "currency": "BDT", "value": 6817, "content_type": "product",
      "contents": [{"content_id":"<sku>","content_type":"product","content_name":"...","price":6667,"quantity":1}]
    }
  }]
}
```
- Phone hashing differs from Meta: TikTok keeps the leading `+` (E.164).
- Needs settings: `tiktok_pixel_id` **and** `tiktok_access_token`.

### 3c. GA4 Measurement Protocol
- Action: `app/Actions/Marketing/SendGa4Purchase.php`
- Event: `app/Support/Ga4/Ga4Events.php` → `purchase()`
- HTTP: `app/Support/Ga4/HttpMeasurementProtocol.php` → `send()`
- **URL hit:** `POST https://www.google-analytics.com/mp/collect?measurement_id=<ga4_id>&api_secret=<ga4_api_secret>`
- **Payload:**
```json
{
  "client_id": "<customer's _ga client id, or srv.FNB-20260630-6943>",
  "events": [{
    "name": "purchase",
    "params": {
      "transaction_id": "FNB-20260630-6943", "currency": "BDT",
      "value": 6817, "shipping": 150,
      "items": [{"item_id":"<sku>","item_name":"...","item_category":"...","price":6667,"quantity":1}]
    }
  }]
}
```
- `client_id` = the `_ga` cookie's client id captured at checkout → GA4 joins the
  purchase to the customer's furnib.com session. Falls back to `srv.<order_no>`.
- Needs settings: `ga4_id` **and** `ga4_api_secret`.

---

## 4. Browser dataLayer purchase (admin side)

- Builder: `app/Support/Marketing/OrderTrackingPayload.php` → `for($order)`
- Flash → push: `laravel-backend/resources/js/hooks/use-flash-datalayer.ts`
  (mounted globally in `resources/js/components/ui/sonner.tsx`)

`use-flash-datalayer.ts` listens for the Inertia `flash` event and does:
```ts
window.dataLayer.push({ ecommerce: null });          // GA4 requires clearing first
window.dataLayer.push({ event: "purchase", ...rest }); // rest = event_id + ecommerce + user_data + order_info
```

**dataLayer payload the GTM tags read** (what you saw in `window.dataLayer`):
```json
{
  "event": "purchase",
  "event_id": "purchase.FNB-20260630-6943",
  "ecommerce": { "transaction_id", "value", "tax", "shipping", "currency",
                 "coupon", "payment_method", "items": [{item_id,item_name,price,quantity,item_category}] },
  "user_data": { "customer_id","name","phone","address","area",
                 "hashed_name","hashed_phone","hashed_email","fbp","fbc","client_ip" },
  "order_info": { "invoice_id","order_id","payment_method","payment_status",
                  "grand_total","shipping","discount","coupon","item_count" }
}
```
GTM (web container `GTM-T373ND86`, loaded on admin via `resources/views/app.blade.php`)
fires the Purchase tags off this. Marketer maps their variables to these paths.

---

## 5. Where the attribution data comes from (captured at checkout)

- `app/Http/Controllers/Api/CheckoutController.php` reads cookies / `X-*` headers:
  `_fbp/_fbc` (Meta), `_ttp/ttclid` (TikTok), `_ga`→client id (GA4), real IP.
- Forwarded by the Next proxy: `ecommerce-next-frontend/app/api/checkout/route.ts`.
- Persisted onto the order in `app/Actions/Orders/PlaceOrder.php`
  (columns: `fbp, fbc, ttp, ttclid, ga_client_id, customer_ip`).
- So when the admin confirms **later**, the senders in §3 use the **customer's**
  identifiers (not the admin's browser).

---

## 6. Settings / credentials (admin → Marketing)

Stored in the `marketing` settings group. Secrets are encrypted + write-only.

| Key | For | Public? |
|---|---|---|
| `gtm_id` | loads GTM (both sites) | yes |
| `fb_pixel_id` | Meta CAPI (with token) | yes |
| `fb_capi_token` | Meta CAPI | **secret** |
| `tiktok_pixel_id` | TikTok Events API | yes |
| `tiktok_access_token` | TikTok Events API | **secret** |
| `ga4_id` | GA4 MP measurement id | yes |
| `ga4_api_secret` | GA4 MP | **secret** |

Check what's configured:
```bash
php artisan tinker --execute="\$s=app(\App\Services\Settings\SettingsService::class); foreach(['fb_pixel_id','fb_capi_token','tiktok_pixel_id','tiktok_access_token','ga4_id','ga4_api_secret'] as \$k){ echo \$k.': '.(filled(\$s->get('marketing',\$k))?'SET':'EMPTY').\"\n\"; }"
```

---

## 7. File map (open these in VS Code)

| Step | File |
|---|---|
| Confirm entry | `app/Http/Controllers/Admin/OrderController.php` (`updateStatus`) |
| Orchestrator (idempotent) | `app/Actions/Marketing/ConfirmOrderPurchase.php` |
| Meta sender / event / HTTP | `app/Actions/Marketing/SendPurchaseEvent.php` · `app/Support/Capi/CapiEvents.php` · `app/Support/Capi/MetaConversionApi.php` |
| TikTok sender / event / HTTP | `app/Actions/Marketing/SendTiktokPurchase.php` · `app/Support/Tiktok/TiktokEvents.php` · `app/Support/Tiktok/HttpEventsApi.php` |
| GA4 sender / event / HTTP | `app/Actions/Marketing/SendGa4Purchase.php` · `app/Support/Ga4/Ga4Events.php` · `app/Support/Ga4/HttpMeasurementProtocol.php` |
| PII hashing | `app/Support/Capi/CapiUserData.php` · `app/Support/Tiktok/TiktokUserData.php` |
| dataLayer payload | `app/Support/Marketing/OrderTrackingPayload.php` |
| Browser push (admin) | `resources/js/hooks/use-flash-datalayer.ts` · `resources/js/components/ui/sonner.tsx` |
| GTM loader (admin) | `resources/views/app.blade.php` |
| Capture at checkout | `app/Http/Controllers/Api/CheckoutController.php` · `app/Actions/Orders/PlaceOrder.php` |
| Interface → impl bindings | `app/Providers/RepositoryServiceProvider.php` |
| Funnel (view/lead/checkout) | `app/Http/Controllers/Api/CollectController.php` (Meta + TikTok) |
| Storefront event emitter | `ecommerce-next-frontend/lib/track.ts` |
| Full spec / decisions | `docs/GTM-TRACKING-PLAN.md` |

---

## 8. How to trace / debug

- **Did the server fire?** After confirm, `orders.marketing_purchase_sent_at`
  is set:
  ```bash
  php artisan tinker --execute="dd(\App\Models\Order::latest()->first()->only(['order_no','status','marketing_purchase_sent_at','customer_ip','fbp','ttp','ga_client_id']));"
  ```
- **Did a send fail?** The senders `Log::warning('... failed', ...)`. Failures are
  non-fatal; a warning ≠ order broke.
- **Grep the flow:** search `ConfirmOrderPurchase`, `SendPurchaseEvent`,
  `SendTiktokPurchase`, `SendGa4Purchase`, `marketing_purchase_sent_at`.
- **Tests (living examples):**
  `tests/Feature/Marketing/ServerSideConversionsTest.php` (multi-platform fire,
  idempotency, funnel, capture), `AdminConfirmPurchaseTest.php`,
  `OrderTrackingTest.php`. Run:
  ```bash
  vendor/bin/pest tests/Feature/Marketing
  ```
- **Swap real HTTP for fakes in tests:** bind `FakeConversionApi` /
  `FakeEventsApi` / `FakeMeasurementProtocol` (see the test `beforeEach`).

---

## 9. Key facts to remember

1. `purchase` fires **only** on the pending→confirmed transition, **once**
   (guard = `marketing_purchase_sent_at`). Re-confirm never refires.
2. Server path (§3) hits **platforms directly**, not the marketer's sGTM.
3. Same `event_id = purchase.<order_no>` on server + browser → **dedup**.
4. All integrations **no-op safely** until their creds are set — nothing breaks
   if TikTok/GA4 are empty.
5. Storefront never fires `purchase` (it fires `place_order`); purchase is an
   admin-confirm event by design (order ≠ sale).
