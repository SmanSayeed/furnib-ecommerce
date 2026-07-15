# Furnib — Marketing & Tracking Master Plan (Web GTM + Pixel + Laravel CAPI)

> Final decisions (from owner): **GUI-managed Web GTM** for browser tags;
> **Laravel itself is the server-side tagging server** for the Conversions API
> (incl. COD). **No stape.io, no Docker, no extra subdomain.** Secure by default.
> Knowledge current to early 2026 — verify live specifics (Meta Graph API version,
> ad-policy, Consent Mode) on the vendors' docs before launch.

---

## 0. The picture in one paragraph

Customer browses Furnib → the page writes plain facts into a **dataLayer** notebook →
**Web GTM** (free, GUI-managed by Google, no infra) reads it and fires browser tags
(**Meta Pixel**, **GA4**). At the same time, the storefront calls **our own Laravel
endpoint**, which acts as our **server-side tagging server**: it enriches the event
(hashes PII, adds IP/UA/cookies) and posts to **Meta Conversions API (CAPI)** —
reliable, ad-block-proof, and the only way to capture **COD** orders. Both halves share
one `event_id` so Facebook counts each event **once** (de-duplication).

```
                         ┌──────────────  Web GTM (GUI, free)  ──────────────┐
   Storefront page ──►  dataLayer  ──►  Meta Pixel, GA4 (browser tags)        │
        │                                                                     │
        │  POST /api/v1/collect {event, sku, value, event_id, fbp, fbc}       │ same event_id
        ▼                                                                     │ → dedup
   Laravel = our server-side tagging server                                   │
        • hash email/phone (SHA-256), add IP/UA, read fbp/fbc                  │
        • post to Meta CAPI  ──►  graph.facebook.com   ◄─────────────────────┘
        • COD Purchase fires here (browser can't)
```

**No stape, no Docker, no subdomain.** GUI = Google's free Web GTM. Server-side = Laravel.

---

## 1. What's already in the repo ✅ / gaps ⏳

**Built:** `Api/MarketingController` (public IDs: gtm_id, ga4_id, fb_pixel_id, clarity_id),
`Settings/MarketingSettingController` (+ encrypted `fb_capi_token`), `Support/Capi/MetaConversionApi`
+ `PurchasePayload` + `Actions/Marketing/SendPurchaseEvent` (server Purchase, shared event_id),
`Services/Marketing/ProductFeed` (CSV feed), `Api/TrackingController` (visitor table).

**Gaps — ALL CLOSED (implemented, awaiting only live IDs/token):** ✅ GTM + dataLayer + consent on
storefront · ✅ Purchase now also fires at COD placement (was SSLCommerz-only) · ✅ rich CAPI payload
(SHA-256 user_data + fbp/fbc + contents/content_ids) · ✅ ViewContent/InitiateCheckout/Lead/Purchase ·
✅ `POST /api/v1/collect` browser→server beacon (same-origin proxy) · ✅ feed `link` → `/product/<slug>`
(real page) · ✅ content_ids === feed id === sku everywhere · ✅ cookie consent banner.

> **Status (2026-06-22): code-complete.** Nothing tracks until the owner pastes a GTM Container ID,
> Meta Pixel ID + CAPI token (and optionally GA4/Clarity) into **Admin → Marketing → Tracking & Pixels**
> (`/settings/marketing`). With those blank the storefront behaves exactly as before — no banner, no tags,
> no network calls. See §9–§11.

---

## 2. Event funnel (single-product, COD/WhatsApp market)

| Step | Meta (Pixel + CAPI) | GA4 | content/value |
|---|---|---|---|
| page open | PageView | page_view | — |
| view product | ViewContent | view_item | content_ids=[sku], value=price |
| Order Now / checkout | InitiateCheckout | begin_checkout | content_ids, value=total |
| WhatsApp click | Lead | generate_lead | content_ids |
| **order placed (COD too)** | **Purchase** | purchase | content_ids, value=total, order_id |

`event_id` rules: `view.<sku>.<ts>`, `checkout.<sku>.<ts>`, `lead.<sku>.<ts>`,
**`purchase.<order_no>`** (already used). Browser + server send the same id → dedup.

---

## 3. Phased plan (each phase independently shippable + tested)

### Phase 1 — Browser foundation: Web GTM + dataLayer + Pixel/GA4 (GUI-managed)
**Storefront (Next.js):**
- `components/analytics/GtmScript.tsx` — inject GTM via `next/script` (`afterInteractive`),
  using `gtm_id` from `getSettings()`/marketing endpoint (public ID only). Mount in `app/layout.tsx`.
- `lib/dataLayer.ts` — `pushEvent(name, params)` helper (guards SSR, fires once).
- Wire pushes: product view (`view_item`/ViewContent), Order-Now & checkout mount
  (`begin_checkout`/InitiateCheckout), WhatsApp buttons (`generate_lead`/Lead), success page
  (`purchase`/Purchase with the server's `event_id`).
- **Tags themselves (Pixel, GA4) are configured in the Web GTM GUI**, not hardcoded — owner
  manages campaigns/new pixels by clicking, no redeploy.
- Consent gate (Phase 5) before non-essential tags.

**Secure:** only public IDs in the browser; NEVER a token/secret in dataLayer or GTM (the
container is publicly downloadable). Fire each event once (no React effect loops).

### Phase 2 — Laravel = our server-side tagging server (the reliable + COD half)
- New `POST /api/v1/collect` (throttled, validated) — receives `{event, sku?, value?, qty?,
  event_id, event_source_url, fbp?, fbc?}` from the storefront. **Never trusts client money
  for Purchase** (server owns order totals).
- Generalize `ConversionApi`: `send(string $event, array $payload): bool`; add payload builders
  `ViewContentPayload`, `InitiateCheckoutPayload`, `LeadPayload`; enrich `PurchasePayload`.
- **Enrich every payload** (raises Meta Event Match Quality): `event_time`, `event_source_url`,
  `action_source`, `user_data` = SHA-256(email), SHA-256(phone) [normalized first] +
  `client_ip_address` + `client_user_agent` + `fbp` + `fbc`; `custom_data` = currency, value,
  `contents:[{id:sku,quantity,item_price}]`, `content_ids:[sku]`, `content_type:'product'`,
  `order_id` (Purchase).
- **Fire Purchase for COD at order placement** (in `PlaceOrder`/`CheckoutController`) using
  `event_id = purchase.<order_no>`; keep the existing online-pay Purchase. Same id → no double count.
- First-party `fbp`/`fbc`: Laravel reads them from the request (set by Pixel) and may refresh
  them as first-party cookies for longevity (our own domain — this is the "self-built sGTM" bit).

**Secure (owner's #1 rule):** CAPI token stays in encrypted settings, server-only, never logged;
hash all PII before sending; HTTPS only; validate + rate-limit `/collect`.

### Phase 3 — Catalog ads (high-value for furniture)
- **Fix feed `link`** to a real landing page (build a `/product/<slug>` page OR point to
  `/checkout/<slug>` / `/category/<slug>`). Catalog ads must not 404.
- Enrich feed to full Meta spec: id(=sku), title, description, availability, condition, price,
  **sale_price** (discount), link, image_link, additional_image_link, brand, product category,
  item_group_id (variants).
- **content_ids === feed id === sku** everywhere (Pixel + CAPI) — the join key for dynamic ads.
- Connect feed in Meta Commerce Manager (scheduled refresh) + Google Merchant Center.

### Phase 4 — GA4 + Microsoft Clarity
- GA4 ecommerce events via the GTM GUI (same dataLayer). Clarity via its ID for heatmaps/recordings.
- Optional later: GA4 server-side via Measurement Protocol from `/collect` (same pattern as CAPI).

### Phase 5 — Consent + hygiene
- Cookie consent banner; default-deny non-essential; gate browser tags on consent.
- Separate Pixel/GTM/GA4 IDs for dev vs prod; Meta Test Events + GA4 DebugView before launch;
  exclude internal IPs.

---

## 4. Common mistakes we explicitly avoid
Browser-only (no CAPI) · double counting (no event_id) · **COD not tracked** · content_ids ≠ feed id ·
feed links 404 · raw (unhashed) PII to CAPI · secrets in browser/GTM · missing value/currency ·
duplicate events from effect loops · no consent · stale feed · same Pixel for dev+prod.

## 5. Security checklist
Token: encrypted DB, server-only, never in responses/logs/GTM/`NEXT_PUBLIC_*` · hash email/phone
(SHA-256, normalized) before CAPI · HTTPS only · `fbp/fbc` are non-secret first-party cookies (OK) ·
consent-gate browser tags · `/collect` validated + rate-limited, server owns Purchase value ·
separate dev/prod IDs.

## 6. Marketing tips
Optimize ViewContent/Lead early → Purchase after ~50 conv/wk · catalog retargeting (14-day
view-no-buy) is gold for furniture · WhatsApp click = Lead → optimize cheap leads, close on WhatsApp ·
value-based optimization (send real value) · lookalikes from Purchase audience · exclude buyers from
prospecting · UTM (already captured) → campaign→sale attribution.

## 7. Build order
Phase 1 (GTM+dataLayer+Pixel, GUI) → Phase 2 (Laravel CAPI: COD Purchase + all events) →
Phase 3 (feed fix + content_ids) → Phase 4 (GA4/Clarity) → Phase 5 (consent/hygiene).
Backend TDD with a faked CAPI (no real network); storefront tsc/eslint-clean.

## 8. What the owner must provide / create
- **GTM Container ID** (`GTM-XXXXXXX`) — from tagmanager.google.com (free).
- **Meta Pixel ID** + **CAPI access token** — from Facebook Events Manager (token → encrypted settings only).
- **GA4 Measurement ID** (`G-XXXXXXX`) — optional, from Google Analytics.
- A Meta **Commerce Manager** catalog + **Google Merchant Center** account for Phase 3.
(If none exist yet, we'll create them step by step.)

---

## 9. What was built (file map)

**Backend (Laravel)**
- `app/Support/Capi/CapiUserData.php` — normalizes + SHA-256 hashes email/phone; passes ip/ua/fbp/fbc; drops blanks.
- `app/Support/Capi/CapiEvent.php` — one Meta event (`event_id`, `event_time`, `action_source`, user_data, custom_data).
- `app/Support/Capi/CapiEvents.php` — factories: `purchase` / `viewContent` / `initiateCheckout` / `lead`; `content_ids` = sku.
- `app/Support/Capi/ConversionApi.php` — now `send(CapiEvent): bool` (was `purchase()` only).
- `app/Support/Capi/MetaConversionApi.php` — posts to Graph v19.0; **token in body, never URL**; optional `test_event_code`; 5s timeout.
- `app/Support/Capi/FakeConversionApi.php` — records `$events`; `ofType()` helper for tests.
- `app/Actions/Marketing/SendPurchaseEvent.php` — builds the Purchase event; non-fatal; default user_data from the order.
- `app/Http/Controllers/Api/CollectController.php` + `Requests/Api/CollectEventRequest.php` — `POST /api/v1/collect`; **Purchase forbidden** here; value derived from the published product (client value never trusted).
- `app/Http/Controllers/Api/CheckoutController.php` — fires Purchase **at COD placement** with the same `purchase.<order_no>` id.
- `app/Services/Marketing/ProductFeed.php` — `link → /product/<slug>`; adds `sale_price`, `additional_image_link`; price=regular, sale_price=discount.
- `app/Http/Controllers/Settings/MarketingSettingController.php` (+ Request) — adds non-secret `fb_test_event_code`.
- `routes/api.php` — `POST v1/collect` (throttle:tracking).
- Tests: `MetaCapiTest`, `CollectEventTest`, `CodPurchaseEventTest`, `ProductFeedTest`, `Unit/CapiUserDataTest` — green.

**Storefront (Next.js)**
- `lib/dataLayer.ts` — `pushEvent()`.
- `lib/consent.ts` — cookie consent as an external store (nothing loads until "Accept").
- `lib/track.ts` — `trackViewContent / trackInitiateCheckout / trackLead / trackPurchase`; dataLayer + `/api/collect` with a shared `event_id`.
- `lib/marketing.ts` — fetches the public IDs (no secrets).
- `components/analytics/Analytics.tsx` — loads GTM (consent-gated). `ConsentBanner.tsx` — accept/decline. `ProductView.tsx` — fires ViewContent.
- `app/api/collect/route.ts` — same-origin proxy → Laravel (forwards XFF + `_fbp`/`_fbc`).
- `app/api/checkout/route.ts` — now forwards `_fbp`/`_fbc`/referer too.
- `app/product/[slug]/page.tsx` — **real product landing page** (feed target) + Product JSON-LD.
- Wired: ViewContent (product page), InitiateCheckout (checkout mount), Lead (Inquiry click), Purchase (success page).

---

## 10. GO-LIVE runbook (do this once you have the IDs, after deploy)

1. **Create accounts** → GTM container (`GTM-XXXX`), Meta Pixel + a **CAPI System-User token**, (optional) GA4 `G-XXXX`, Clarity.
2. **Paste them** in Admin → **Marketing → Tracking & Pixels**. Token is write-only/encrypted; the rest are public IDs.
3. **In the GTM GUI** (this is the only place tags live — no redeploy needed afterwards):
   - **Variables** (Data Layer Variable): `event_id`, `ecommerce`, `content_ids`, `content_type`, `meta_event`.
   - **Triggers** (Custom Event, exact match) — the storefront pushes GA4-canonical
     event names: `view_item`, `begin_checkout`, `generate_lead`, `purchase`.
   - **GA4**: a Google Tag (config) on All Pages + GA4 Event tags on each trigger; the
     `ecommerce` object is already GA4-spec (the previous one is cleared before each push),
     so "Use Data Layer ecommerce" works out of the box. Event name = the trigger name.
   - **Meta Pixel** base tag on All Pages + event tags on the same triggers; map the Meta
     event name from `{{meta_event}}` (view_item→ViewContent, begin_checkout→InitiateCheckout,
     generate_lead→Lead, purchase→Purchase) and set each tag's **Event ID = {{event_id}}** →
     this is what de-dupes against our server CAPI. Map value/currency/content_ids from the dataLayer.
   - (Optional) **Clarity** tag on All Pages.
4. **Test** with Meta **Test Events** (paste the code into the Test event code field for QA) + GA4 **DebugView**. Confirm each event shows **once** (server + browser deduped).
5. **Catalog ads (Phase 3):** enable the feed at Marketing → Facebook Commerce, then in Commerce Manager add a **scheduled data feed** = the secured `https://<api-domain>/feed/{slug}/products.csv` with the Basic-auth username/password (1-hour interval). Same feed → Google Merchant Center. `content_ids` (sku) already match.

## 11. Deploy notes / caveats
- **Trusted proxies:** `client_ip_address` in CAPI is only correct if Laravel trusts the storefront/edge proxy (`X-Forwarded-For`). Set `App\Http\Middleware\TrustProxies` before launch — otherwise the proxy IP is sent.
- **HTTPS only** in prod; keep dev/prod **Pixel + GTM IDs separate**; exclude internal IPs in GTM.
- **Consent:** browser tags + `/collect` only fire after "Accept". For COD/online Purchase the server still sends CAPI (legitimate-interest order receipt) — review against your privacy policy/region before launch.
- Token/secret never appears in the browser, dataLayer, GTM, logs, `NEXT_PUBLIC_*`, or the feed (verified by tests).
