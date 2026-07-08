# Furnib — GTM / Marketing Tracking Upgrade (Plan & Spec)

> Source of truth for the marketing-tracking work. Built with the marketer's
> dataLayer spec **plus** our server-side CAPI (dual tracking). Owner has
> explicitly accepted raw PII in the browser dataLayer (see Security note).

## 0. Locked decisions

- **Dual tracking:** every conversion goes BOTH via browser dataLayer (GTM, marketer-controlled) AND server-side Meta CAPI. Same `event_id` → Meta de-duplicates → counted once.
- **Order ≠ sale:** storefront "Place order" fires `place_order` (order created). The real **`purchase`** fires **only when admin sets status = `confirmed`** — for **all** orders (COD *and* online-paid).
- **PII in browser:** marketer wants the full `user_data` block (raw name/phone/address/area + `fbp`/`fbc`/`client_ip`) in the dataLayer, **plus** extra hashed fields (`hashed_name`, `hashed_phone`, `hashed_email`). Owner accepts the residual exposure risk (any page script/extension can read raw PII in dataLayer). Server CAPI still sends raw PII hashed, securely.
- **Admin GTM:** admin app loads GTM using the GTM id already settable in admin (marketing settings `gtm_id`). Same container as storefront unless marketer asks for a separate one.

## 1. The triggers

| event | trigger (button / action) | file | status / action |
|---|---|---|---|
| `page_view` | every page load + SPA nav | — | **GTM/GA4 handles it** (GA4 config tag). No app code unless owner wants explicit push. *(PENDING decision A)* |
| `search` | header search (desktop input + mobile panel), fired once per settled debounced query | `components/HeaderSearch.tsx` → `lib/track.ts` (`trackSearch`) | **done.** dataLayer-only signal (GA4 recommended `search` event, param `search_term`); no CAPI (search isn't a product funnel action) |
| `view_category` | category card click (image **and** "View Series" — both inside one `<Link>`) | `components/CategoryGrid.tsx` | make client, add `onClick` → push `view_category` with `{ category_id, category_name, category_slug }` |
| `view_item` | **"See more"** on product caption | `components/ProductCaption.tsx` (data from `ProductRow.tsx`) | thread product info into ProductCaption; fire on "See more" `onClick` |
| `generate_lead` | Inquiry (WhatsApp) button | `components/ProductActions.tsx` | **already done** (`trackLead`) — keep |
| `begin_checkout` | **"Order now"** on product card | `components/ProductActions.tsx` | **MOVE here.** Currently fires on checkout-page mount — remove from `CheckoutForm` |
| `place_order` | **"Place order"** submit on checkout, after order is created (HTTP 201) | `components/CheckoutForm.tsx` | replace the old success-page `purchase`; build the rich payload (§3) |
| `purchase` | admin **Update status → confirmed** | backend `OrderController@updateStatus` + admin order page | new (Phase 4); rich payload (§3); dual (browser dataLayer + server CAPI); fire once |

### Reconciliation notes (changes to existing behaviour)
1. `begin_checkout` moves from `CheckoutForm` mount → product "Order now" click. Remove the `useEffect` `trackInitiateCheckout` in `CheckoutForm` (lines ~48–58).
2. `place_order` replaces the success-page `trackPurchase` (`app/checkout/success/page.tsx:41`). Remove that call.
3. Old `/product/[slug]` page fires `view_item` on load via `components/analytics/ProductView.tsx` (`app/product/[slug]/page.tsx:75`). **PENDING decision B** — recommended: **remove** it so `view_item` only means a "See more" click (one clear definition for the marketer).
4. `purchase` is removed from order placement (`CheckoutController`) and from online-payment success (`RecordPayment`) — it now fires **only** at admin confirm.

### PENDING micro-decisions (defaults if no answer)
- **A — page_view:** default = let GTM handle it (no code). Alternative = explicit `page_view` push on each route change.
- **B — old product-page view_item:** default = **remove** `ProductView` page-load fire.

## 2. Existing code facts (so we don't re-discover post-compact)

- **Storefront events live in** `lib/track.ts`:
  - `trackViewContent` → `view_item`, `trackInitiateCheckout` → `begin_checkout`, `trackLead` → `generate_lead`, `trackPurchase` → `purchase`.
  - `trackSearch` → `search` (dataLayer-only, `{ search_term }`); wired in `HeaderSearch.tsx` after each settled debounced query.
  - `lib/dataLayer.ts`: `pushEvent(event, params)`, `clearEcommerce()` (GA4 needs `ecommerce:null` cleared before each ecommerce event).
  - Consent gate already removed (BD); GTM loads unconditionally — `components/analytics/Analytics.tsx`.
- `ProductActions.tsx`: Inquiry `onClick={onInquiry}` (trackLead ✓). "Order now" = `<Link href="/checkout/{slug}?qty=1">` — **no event yet** → add `begin_checkout`.
- `CheckoutForm.tsx`: `trackInitiateCheckout` in a mount `useEffect`; `placeOrder()` POSTs `/api/checkout`; on **201** stores `json.data` in `sessionStorage["furnib:order"]` and `router.push("/checkout/success")`. Submit button label = "Place order".
- `checkout/success/page.tsx`: reads the stored order, calls `trackPurchase` (remove).
- `ProductCaption.tsx`: props are `{ text }` only; rendered by `ProductRow.tsx:72` as `<ProductCaption text={product.details ?? ""} />` — `ProductRow` has the full `product` (sku, title, price/discount_price, category).
- `CategoryGrid.tsx`: server component; each card is `<Link href="/category/{c.slug}">` wrapping the image **and** the "View Series" pill (`c.id`, `c.title`, `c.slug`, `c.details`).
- **Backend (Laravel):**
  - `Api/CheckoutController` captures `ip` (`$request->ip()`), `fbp` (`_fbp` cookie / `X-Fbp` header), `fbc` (`_fbc` / `X-Fbc`); calls `SendPurchaseEvent`.
  - `Actions/Payments/RecordPayment` also calls `SendPurchaseEvent` (online payment success).
  - `Actions/Marketing/SendPurchaseEvent`, `Support/Capi/CapiUserData` (normalize + SHA-256), `Support/Capi/MetaConversionApi`.
  - Admin: `Admin/OrderController@updateStatus`, route `PUT /admin/orders/{order}/status` (name `orders.status`, middleware `permission:orders.manage`).
  - Settings: `marketing` group holds `gtm_id`, `ga4_id`, `fb_pixel_id`, `clarity_id`, `fb_capi_token` (secret).

## 3. Rich payload shape (`place_order` & `purchase`)

Built from the marketer's demo. `gtm:{uniqueEventId,start}` is added by GTM itself — we do **not** send it.

```js
{
  event: "place_order",            // or "purchase" from admin
  ecommerce: {
    transaction_id, value, tax, shipping, currency: "BDT",
    coupon, payment_method,        // "cod" | "online"
    items: [
      { item_id, item_name, price, quantity, item_category }
    ]
  },
  user_data: {
    customer_id,
    name, phone, address, area,            // raw (marketer's request)
    hashed_name, hashed_phone, hashed_email, // SHA-256, Meta-normalized
    fbp, fbc, client_ip                    // client_ip injected by Next.js server
  },
  order_info: {
    invoice_id, order_id, payment_method, payment_status,
    grand_total, shipping, discount, coupon, item_count
  }
}
```

- **Hashing (Meta rules):** trim + lowercase email; phone = digits only with country code (E.164 sans `+`); names trimmed + lowercased; then SHA-256 hex. Mirror `CapiUserData` normalization for consistency. Hash on the Next.js **server** (so it's reliable and not only client-computed).
- **`client_ip`** is server-only data — Next.js server component reads it from the request (`x-forwarded-for`) and passes it into the dataLayer payload.
- **`event_id`** (for dedup): `purchase.{order_no}` (purchase) / `place_order.{order_no}` (place_order). Browser Pixel, GTM-CAPI, and our server CAPI all use the same id.

## 4. Backend work (Phase 2 + 4)

1. **Migration** — `orders` add: `fbp` (string, null), `fbc` (string, null), `client_ip` (string, null), `marketing_purchase_sent_at` (timestamp, null).
2. **Checkout** — persist captured `fbp`/`fbc`/`client_ip` onto the order row.
3. **Order API** for the success page — return everything §3 needs (customer name/phone/address/area, items with `item_category`, totals, shipping, discount, coupon, payment_method, payment_status, invoice_id, order_id, customer_id).
4. **Admin `updateStatus`** — when new status == `confirmed` AND `marketing_purchase_sent_at` is null:
   - send server-side CAPI `purchase` (stored fbp/fbc/ip + hashed PII, `event_id=purchase.{order_no}`);
   - return the rich `purchase` payload to the admin page (Inertia flash/prop) for the dataLayer push;
   - set `marketing_purchase_sent_at = now()` (idempotent — never refire).
5. **Remove** the `purchase` CAPI from `CheckoutController` (placement) and `RecordPayment` (online success).

## 5. Admin panel work (Phase 4)

- Load **GTM** in the admin app root (read `gtm_id` from settings; same id the admin can set). Format-check `^GTM-[A-Z0-9]+$` before injecting (same hardening as storefront `Analytics.tsx`).
- On the order page, when `updateStatus` returns the `purchase` payload, push it to `window.dataLayer`. PII (customer raw phone/address/ip) appears in the **owner's** authenticated browser only.

## 6. Phases (TDD; show owner after each)

1. **Phase 1 (storefront, low-risk):** `view_category` (CategoryGrid) + `view_item` on "See more" (ProductCaption/ProductRow) + move `begin_checkout` to "Order now" (ProductActions) + apply decision B. Verify: `npx tsc --noEmit`, `npx eslint`.
2. **Phase 2 (backend base):** migration + save tokens + expand order API. Pest RED→GREEN, Pint, phpstan L7.
3. **Phase 3 (storefront `place_order`):** rich payload + server-side hashing + `client_ip` injection; remove success-page `trackPurchase`.
4. **Phase 4 (admin `purchase`):** GTM in admin + `updateStatus` hook (CAPI + payload + idempotent flag) + remove old placement/payment purchase.
5. **Phase 5:** full gate (tsc/eslint + Pint/phpstan/Pest), then commit + push (furnib standing git permission; still confirm).

## 7. Verify commands

- PHP 8.3: `/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64/php.exe`.
- Backend (cwd `laravel-backend`): `"$PHP" vendor/bin/pest [path]`, `"$PHP" vendor/bin/pint <files>`, `"$PHP" vendor/bin/phpstan analyse <files> --level=7 --no-progress | cat -v`.
- Admin build (new Inertia/JS): `cd laravel-backend && export PATH="/l/laragon/bin/php/php-8.3.16-Win32-vs16-x64:$PATH" && CI=1 npx pnpm@9 run build`. Add admin JS deps via pnpm + commit `pnpm-lock.yaml` (Docker uses `--frozen-lockfile`).
- Storefront (cwd `ecommerce-next-frontend`): `npx tsc --noEmit && npx eslint <files>` (no playwright).

## 9. Implementation notes (as built)

Refinements made during build (all consistent with the locked decisions):

- **Payload built server-side in Laravel, not JS.** `App\Support\Marketing\OrderTrackingPayload::for($order)` produces the full `ecommerce` + `user_data` (raw **and** SHA-256 hashed) + `order_info` block. The storefront and admin push it **verbatim** — no hashing or PII handling in the browser. Single tested source, reused by both `place_order` and `purchase`.
- **`client_ip` reuses the existing `orders.customer_ip`** column (captured at checkout via `X-Forwarded-For`), so the migration added only `fbp`, `fbc`, `marketing_purchase_sent_at`.
- **Hashing** uses new public helpers on `CapiUserData` (`hashName/hashPhone/hashEmail`) so the Meta normalization is identical to the server CAPI path.
- **`purchase` fires on the `→ confirmed` transition**, via `App\Actions\Marketing\ConfirmOrderPurchase` (idempotent on `marketing_purchase_sent_at`). Called from BOTH the admin `updateStatus` (manual COD confirm) AND `RecordPayment` (online payment auto-confirms the order). Same `event_id = purchase.<order_no>` → Meta de-dupes → counted once.
- **Admin dataLayer push:** `updateStatus` flashes a `purchase` payload (Inertia v2 flash); `resources/js/hooks/use-flash-datalayer.ts` (wired in `components/ui/sonner.tsx`) pushes it to `window.dataLayer`.
- **Admin GTM** loads from `resources/views/app.blade.php`, reading `marketing.gtm_id` and format-checking `^GTM-[A-Z0-9]+$` before interpolation.
- **`_fbp`/`_fbc` added to the cookie-encryption `except` list** (`bootstrap/app.php`) — they are Meta's own non-secret first-party cookies and would otherwise fail Laravel's decrypt and read as null. The Next checkout proxy already forwards them as `X-Fbp`/`X-Fbc` headers too.
- **`place_order`** is dataLayer-only (no CAPI); the authoritative Meta `Purchase` is the server-side CAPI at confirm.
- Decision **A** = GTM handles `page_view` (no app code). Decision **B** = old `/product/[slug]` page-load `view_item` removed (`ProductView` deleted); `view_item` now means a "See more" click only.

### Verification (all green)
- Storefront: `tsc --noEmit` + `eslint` clean.
- Backend: `phpstan` L7 = 0 errors; full `pest` suite 409 passed / 2 skipped / 0 failed (incl. new `OrderTrackingTest`, `AdminConfirmPurchaseTest`, updated `CodPurchaseEventTest`). All touched files Pint-clean.
- Admin: `vite build` succeeds.

## 10. Multi-platform server-side conversions (TikTok + GA4) & real client IP

Added after launch, because the marketer's GTM `purchase` trigger is locked to
hostname `furnib.com` and so the browser tag never fires on `admin.furnib.com`.
The fix is to make every purchase a **server-side** conversion (GTM/hostname-
independent), mirroring the existing Meta CAPI.

- **TikTok Events API** (`app/Support/Tiktok/`): `EventsApi` (bound to
  `HttpEventsApi`, faked by `FakeEventsApi`), `TiktokUserData` (SHA-256 email +
  phone — TikTok keeps the leading `+`, unlike Meta), `TiktokEvent`,
  `TiktokEvents`. Full funnel parity: `/collect` now also sends TikTok
  `ViewContent` / `InitiateCheckout` / `Contact` (Lead→Contact); confirm sends
  `CompletePayment`. Same `event_id` as the pixel → TikTok de-duplicates.
- **GA4 Measurement Protocol** (`app/Support/Ga4/`): `MeasurementProtocol`
  (bound to `HttpMeasurementProtocol`, faked by `FakeMeasurementProtocol`),
  `Ga4Event`, `Ga4Events`. Confirm sends the GA4 `purchase` using the `_ga`
  client id captured at checkout (falls back to `srv.<order_no>`).
- **Fire point:** `ConfirmOrderPurchase` now fires Meta + TikTok + GA4 together
  (each non-fatal, idempotent via `marketing_purchase_sent_at`). So the purchase
  conversion lands on all three platforms regardless of the GTM hostname filter.
- **New captured fields:** migration adds `orders.ttp`, `ttclid`, `ga_client_id`
  (alongside `fbp`/`fbc`). The Next checkout/collect proxies forward
  `_ttp`/`ttclid`/`_ga`(→client id) as `X-Ttp`/`X-Ttclid`/`X-Ga-Client-Id`.
- **Settings (admin → Marketing):** `tiktok_pixel_id` (public),
  `tiktok_access_token` + `ga4_api_secret` (write-only secrets, encrypted),
  `tiktok_test_event_code`. Public `/marketing` API now also returns
  `tiktok_pixel_id`. All three integrations no-op safely until configured.
- **Real client IP:** `trustProxies` now includes Cloudflare's published IPv4/
  IPv6 ranges (specific ranges, never `*`), and the Next proxies prefer
  `CF-Connecting-IP` — so `orders.customer_ip` is the real visitor, not a
  Cloudflare edge IP (was `172.69.x`).

### Owner action items (no code)
1. **Marketer:** make the Meta Pixel base tag fire on **all** storefront pages
   (currently `_fbp`/`_fbc` cookies are never set → fbp/fbc null on orders).
2. **Marketer:** add the **TikTok Pixel** base tag in GTM (sets `_ttp`/`ttclid`).
3. **Admin → Marketing settings:** paste the TikTok pixel code + access token,
   and the GA4 API secret, to switch the server-side TikTok/GA4 sends on.
4. Verify Meta/TikTok/GA4 "Server" events arrive in each platform's events tool.

## 8. Security note (recorded)

Raw name/phone/address/IP in the browser dataLayer is readable by any third-party script or browser extension on the page; Meta itself only needs hashed values for matching. Recommended secure path (hashed-only in browser, raw via server CAPI) was offered and the owner chose the marketer's raw+hashed format. Server CAPI continues to send raw PII hashed. Admin-side `purchase` dataLayer exposes customer PII only inside the authenticated owner's browser.
