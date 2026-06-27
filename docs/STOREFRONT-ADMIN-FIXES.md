# Furnib — Storefront & Admin Fix Pack (2026-06-27)

A batch of UI/UX + feature fixes requested by the owner. Each item: **what →
approach → files → decisions/risks**. Built one-by-one, local-test + push, then
owner redeploys (backend + frontend). Storefront = `ecommerce-next-frontend/`,
admin = `laravel-backend/` (Inertia React).

Legend: 🟢 quick CSS/text · 🟡 moderate · 🔴 feature (DB + admin + storefront).

---

## ✅ Completion log (all batches built, verified, pushed to master)

- **Batch 1** `f3be64f` — A5/A6 caption alignment, A7 "Order now", A8 white/semibold
  buttons, A9 drawer tightening, C1 admin favicon, C4 checkout "Shipping method".
- **Batch 2** `fdf4070` — A1 `Container` (max-w-1600) on header/footer/main, A2 2-col
  feed full-width, **square 1:1 product image (`object-contain`, 1080-style, no crop)**,
  A3 category card 2px inset frame + radius, A4 bigger header logo. Admin upload
  guidance → 1080×1080.
- **Batch 3** `9b02924` — B1 footer recolored to brand orange, white text, recolored
  buttons (white WhatsApp pill, white newsletter field + dark Subscribe, SSLCommerz on
  white card).
- **Batch 4** `4b426f8` — C2 `logo_footer` + `logo_invoice` branding keys (admin upload,
  validation, public API, footer logo), C3 invoice PDF logo (dompdf isRemoteEnabled only
  when a logo is embedded; admin-controlled URL only). Tests for upload/api/template.
- **Batch 5** `3320fdd` — B3 "Follow us": socials 4→7 (X, Pinterest, TikTok) with a
  show/hide toggle each; API emits only enabled+filled; admin toggle UI. Tests.
- **Batch 6** `bb240f5` — B2 Pages CMS: `pages` table + Page model + Admin CRUD gated by
  `settings.manage` + **TipTap editor** + **HTMLPurifier sanitize-on-save (XSS boundary)**
  + public API + storefront `/p/[slug]` + footer "Company" column. 7 tests.

**Gate:** full backend suite 368 pass / 0 fail (2 skipped); Pint + phpstan (lvl 7) +
storefront tsc/eslint + admin Vite build all clean. New deps: `mews/purifier`
(composer), `@tiptap/react @tiptap/starter-kit @tiptap/pm` (admin npm).

### Owner deploy steps (morning)
1. **Redeploy backend** (EasyPanel) — entrypoint auto-runs `migrate --force`, which
   creates the `pages` table. `composer install` picks up `mews/purifier`.
2. **Redeploy frontend** (separate image) — required for all storefront changes
   (square image, footer recolor, `/p/[slug]`, Company column).
3. **No reseed needed** — Pages reuse `settings.manage` (admin already has it).
4. In admin: upload footer/invoice logos, set "Follow us" links + toggles, create
   Pages (e.g. Privacy Policy) and add a footer quick link to `/p/<slug>`, set per-
   product shipping charges. (Developer console still needs the one-time
   `php artisan db:seed --class=PermissionRoleSeeder --force` for `developer.access`.)

---

## GROUP A — Storefront layout & product cards

### A1. 🟡 Max wrapper 1600px + everything aligned to it
- **What:** One standard container, **max-width 1600px**, side padding that
  "auto-adjusts with image width". Header/footer/feed all share it so the left
  edge sits under the logo and the right edge under the theme toggle.
- **Approach:** New `components/Container.tsx` = `mx-auto w-full max-w-[1600px]
  px-4 sm:px-6 lg:px-8`. Use it in `Header`, `Footer`, `layout.tsx` main, home
  sections, category, product pages. Replace the mixed `max-w-2xl/3xl/5xl/6xl`.
- **Files:** `components/Container.tsx` (new), `Header.tsx`, `Footer.tsx`,
  `app/layout.tsx`, `app/page.tsx`, `Hero.tsx`, `BannerCarousel.tsx`,
  `FeaturedCollections.tsx`, `app/category/[slug]/page.tsx`,
  `app/product/[slug]/page.tsx`.

### A2. 🟢 2-column product grid with BIG image (desktop)
- **What:** Desktop feed = 2 columns, each with a large product image (matches
  the screenshot). Mobile = 1 column.
- **Approach:** `InfiniteProducts` grid `grid-cols-1 lg:grid-cols-2` (already
  partly there); ensure the category feed wrapper is the 1600px container (not
  `max-w-2xl`). Keep card media large (`aspect-[4/3]`).
- **Files:** `components/InfiniteProducts.tsx`, `app/category/[slug]/page.tsx`.

### A3. 🟢 Category image: 2px padding + matching border radius
- **What:** Category card image gets `padding: 2px` and the **same border-radius
  as the outer card** (`rounded-card`), so the image sits inset with rounded
  corners. (Note: product-card preview stays square per the earlier request —
  this is the **category** image.)
- **Files:** category card component (in `FeaturedCollections` / category list).
- **Decision:** Confirm this is the **category** thumbnail (not the product
  preview, which we deliberately made square in A-prev).

### A4. 🟢 Bigger logo + text
- **What:** Increase logo size in header/drawer and base text sizes a notch.
- **Approach:** `Logo` `h-7 sm:h-8` → `h-9 sm:h-10`; bump key headings/feed text.
- **Files:** `Header.tsx`, `Logo.tsx`, `CategoryDrawer.tsx`, card text classes.

### A5. 🟢 Product caption "See more" — start from 2nd line, ≤ 2 lines
- **What:** Description clamps to **2 lines max**; "See more" appears inline
  after the clamped text (from the 2nd line), last line trimmed cleanly.
- **Approach:** `ProductCaption` already `line-clamp-2`; refine so the toggle
  reads naturally and never shows >2 lines collapsed; tighten spacing.
- **Files:** `components/ProductCaption.tsx`.

### A6. 🟡 Card image height stable regardless of caption length
- **What:** If the caption is 1 vs 2 lines, the image must **not** shift card
  height inconsistently — cards in a row align. (Screenshot: left card 1-line
  desc, right card multi-line → images misaligned.)
- **Approach:** Reserve a **fixed caption height** (2 lines) so the media always
  starts at the same Y; media keeps `aspect-[4/3]`. Card is `flex flex-col` with
  caption block `min-h-[Nrem]`.
- **Files:** `components/ProductRow.tsx`, `ProductCaption.tsx`.

### A7. 🟢 Category-details card: "Order" → "Order now"
- **Files:** `components/ProductActions.tsx` (label).

### A8. 🟢 "Order now" + "Inquiry" — semibold, white text, adjusted
- **What:** Both buttons `font-semibold`, **white text**, consistent size/shape.
  (Desktop Inquiry soft-pill currently green-on-tint → make solid/white too, or
  keep but ensure contrast.)
- **Files:** `components/ProductActions.tsx`.

### A9. 🟢 Category menu font size + button size + gaps
- **What:** Drawer links: better font, slightly smaller/!tighter, good spacing.
- **Approach:** `CategoryDrawer` `p-6 → p-5`, `gap-1.5 → gap-1`, link
  `text-lg py-3.5 → text-base py-2.5`, section heading spacing.
- **Files:** `components/CategoryDrawer.tsx` (+ category list font/buttons on the
  category page).

---

## GROUP B — Footer redesign + CMS

### B1. 🟡 Footer = primary background, white text, adjusted buttons
- **What:** Footer background = **primary/brand color** (`--brand` orange); all
  footer text **white**; the WhatsApp/Subscribe buttons recolored for contrast
  on the orange bg (e.g. white button + brand text, or darker shade).
- **Approach:** `Footer.tsx` root `bg-[var(--brand)] text-white`; links
  `text-white/90 hover:text-white`; Subscribe button `bg-white text-brand`;
  social icons white. SSLCommerz badge area on white card so the logos read.
- **Files:** `components/Footer.tsx`, `components/NewsletterForm.tsx`.
- **Decision:** Keep SSLCommerz strip on a white rounded card (the logos are
  multicolor on white).

### B2. 🔴 "About Us" footer = dynamic, fixed CMS pages (show/hide + rich text)
- **What:** Footer "About Us" column lists fixed pages: Company Profile, Blog,
  Privacy Policy, Terms & Conditions, Delivery & Return, Careers (screenshot).
  Each is an **admin-managed page**: title + rich-text body + **show/hide**
  toggle. Privacy Policy etc. use a **rich text editor**.
- **Approach (backend):** new `pages` table (`id, slug, title, body_html,
  is_published, position, timestamps`). `Page` model. Admin CRUD
  (`Admin\PageController`, `settings.manage` or new `content.manage`) with list
  + create/edit (title, slug auto, rich body, published toggle, order).
  Storefront: `GET /api/v1/pages` (published, ordered) for the footer; `GET
  /api/v1/pages/{slug}` for the page; storefront route `app/p/[slug]/page.tsx`.
- **Rich text editor (admin):** **TipTap** (`@tiptap/react` + StarterKit) — free
  (MIT), React, simple toolbar (bold/italic/heading/list/link). ⚠️ **New npm
  dependency — needs owner OK before install.**
- **🔒 SECURITY (critical):** rich-text HTML is an **XSS vector**. Mitigations:
  1. **Sanitize on save** server-side with **HTMLPurifier** (`mews/purifier`,
     composer) — strip scripts/event handlers/`javascript:`; allow a safe tag
     whitelist only.
  2. Storefront renders sanitized HTML; still set a strict allowlist.
  3. Editor link inputs validated (http/https/relative only).
- **Files:** migration + `Page` model + factory; `Admin\PageController` +
  request; `resources/js/pages/content/pages/{index,form}.tsx` + a
  `RichTextEditor` component; `Api\PageController`; storefront `lib/api.ts`,
  `app/p/[slug]/page.tsx`, `Footer.tsx` (link to `/p/{slug}`); sidebar item.

### B3. 🔴 "Follow Us" admin — show/hide social buttons + edit links
- **What:** Admin page to toggle each social icon on/off and edit its URL
  (screenshot: Facebook, X, Instagram, LinkedIn, YouTube, Pinterest, TikTok).
- **Approach:** Extend branding socials from 4 → 7 platforms, each with a URL +
  an `enabled` flag. Store as `branding.socials` JSON `[{platform, url,
  enabled}]` OR per-key. Admin "Follow Us" section (in Site & branding or its
  own page). Footer shows only enabled+filled ones.
- **Files:** `SiteSettingsUpdateRequest`, `SiteSettingController`,
  `Api\SettingController`, admin `site.tsx` (or new page), `Footer.tsx`,
  storefront `lib/types.ts`.

---

## GROUP C — Branding logos & admin polish

### C1. 🟢 Admin: replace Laravel favicon with Furnib logo
- **What:** Admin browser-tab favicon = Furnib favicon (not Laravel default).
- **Approach:** Set `<link rel=icon>` in the admin root blade
  (`resources/views/app.blade.php`) to the Furnib favicon
  (`/logo/furnib-favicon.png` or the DB branding favicon).
- **Files:** `laravel-backend/resources/views/app.blade.php` (+ public favicon).

### C2. 🔴 Multi-logo settings: header / footer / invoice / favicon — and they work
- **What:** Admin can upload/replace **4 distinct images**: header logo, footer
  logo, invoice logo, favicon — and each is actually used where intended.
- **Approach:** Branding already has `logo_light`, `logo_dark`, `favicon`. Add
  `logo_footer` + `logo_invoice` (or reuse). Wire:
  - Header → `logo_light/dark` (done) — verify works.
  - Footer → `logo_footer` (fallback to logo_light).
  - Invoice → `logo_invoice` (fallback to logo_light) in the PDF blade.
  - Favicon → admin blade `<link rel=icon>` + storefront metadata (already
    uses `settings.favicon`).
  - Storefront footer logo via `GET /api/v1/settings`.
- **Files:** `SiteSettingsUpdateRequest`, `SiteSettingController` (FILE_KEYS),
  `Api\SettingController`, admin `site.tsx`, invoice blade, `Footer.tsx`,
  `app.blade.php`.
- **Note:** "make sure it works" → end-to-end test each: upload → stored (R2) →
  rendered in header/footer/invoice/favicon.

### C3. 🟢 Invoice: add Furnib logo
- **What:** Invoice PDF shows the brand logo (the `logo_invoice` from C2).
- **Files:** `resources/views/invoices/order.blade.php` (dompdf — use an
  absolute URL or base64; R2 URL must be reachable by dompdf).
- **Risk:** dompdf fetching a remote R2 image — may need `isRemoteEnabled` or
  embedding base64. Verify rendering.

### C4. 🟢 Order page: "Shipping zone" → "Shipping method"
- **What:** Rename the label on the order/checkout page.
- **Decision (confirm):** Earlier we renamed checkout "Delivery area" →
  "Shipping zone"; now it should read **"Shipping method"**. Apply on checkout
  selector + admin order detail where the zone shows.
- **Files:** `components/CheckoutForm.tsx`, admin `orders/show.tsx`.

---

## Execution order (build one-by-one)

**Batch 1 — quick storefront wins (🟢, no deps):** A7, A8, A9, A5, C4, C1
**Batch 2 — layout (🟡):** A1 (Container 1600) → A2 (2-col) → A6 (image height) → A3, A4
**Batch 3 — footer visual (🟡):** B1
**Batch 4 — branding logos (🟡/🔴):** C2 → C3 (multi-logo + invoice)
**Batch 5 — Follow Us admin (🔴):** B3
**Batch 6 — Pages CMS + rich editor (🔴, needs dep OK + sanitizer):** B2

Each batch: TDD where backend is involved, tsc+eslint+build for storefront,
local browser DOM-check, commit + push. Owner redeploys (backend + frontend).

## Open confirmations
1. **Rich text editor** = TipTap (new npm dep) + **HTMLPurifier** (composer) for
   XSS-safe storage — OK to add these two deps?
2. **A3** padding+radius is the **category** image (product preview stays square)?
3. **C4** label is really **"Shipping method"** (replacing the "Shipping zone" we
   just set)?
4. Pages list (B2) default set = Company Profile, Blog, Privacy Policy, Terms,
   Delivery & Return, Careers — seed these as drafts?
