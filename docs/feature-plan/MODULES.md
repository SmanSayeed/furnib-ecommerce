# Furnib.com — Modules & Feature Breakdown

> The complete module map. Each module lists its features, key data, and the phase it ships in.
> Read with `MASTER-PLAN.md` (architecture/decisions) and `ROADMAP.md` (ordered tasks).
> Legend — **[A]** Admin (Laravel+Inertia) · **[S]** Storefront (Next.js) · **[API]** shared JSON API · **[Sec]** security-critical.

---

# PART A — BACKEND / ADMIN MODULES

## M0. Foundation & Architecture  · Phase 0  · [A][Sec]
- SOLID layering: Controller → Service → Repository (interface+impl) → DTO → Action.
- `StorageRepository` abstraction (server disk default, Cloudflare R2 switchable).
- `Money` value object + Eloquent cast (integer paisa, no floats).
- Encrypted grouped settings service (secrets encrypted at rest).
- Versioned `/api/v1` (Sanctum, JSON resources, uniform error envelope, rate limiters).
- Audit logging baseline (actor + IP on sensitive writes).

## M1. Auth, Roles & Permissions (RBAC)  · Phase 0  · [A][Sec]
- Roles: `owner, admin, manager, sub-admin, marketer, editor` — permission-driven (not role-name checks).
- Secure **owner** bootstrap from env; forced password change + **mandatory 2FA** on first login (Fortify).
- Admin login/logout, password reset, profile (change email/password later).
- Permission matrix (MASTER-PLAN §6); architecture supports UI role management later (not built now).
- **No backdoor**; reversible owner Maintenance Lock only (M19).

## M2. Category Management  · Phase 1  · [A][API]
- CRUD: title, slug (auto, unique), details (SEO rich text), **header_image**, **thumbnail_image** (OG/social), status (active/inactive), `position_order`.
- Per-category SEO: meta_title, meta_description, og_image.
- Drag/positional ordering for storefront display.
- Image upload → WebP optimization (via M20).
- Validation, Policy (`categories.*`), audit-logged.

## M3. Product Management  · Phase 1  · [A][API]
- Core: title, slug (unique), **SKU** (unique), details (optional, SEO), `product_video` (YouTube embed).
- **One main image + up to 6 gallery images**, auto-optimized to responsive WebP; alt text per image; max-6 enforced.
- Pricing: `price`, `discount_price` (optional); display struck-through original when discounted.
- Advance payment rules: `is_advance_payment`, type `full|partial`, partial `percentage|amount`, `partial_amount`.
- Flags: `is_featured`, `is_new`, `position_order`, `product_status` (draft/published/disabled).
- Stock: `stock_amount`, `stock_status` (if false → show "no stock" even if amount > 0).
- `social_thumbnail_image` (optional → falls back to main image).
- One category per product (assignment).
- Validation, Policy, audit-logged.

## M4. Product Listing & Lifecycle  · Phase 1  · [A]
- Server-side table: search (keyword / SKU / slug), filters (status, category, stock status, date range), multi-column sort, pagination.
- Row actions: view, edit, soft-delete.
- **Recycle bin**: list soft-deleted, restore, hard-delete.
- **CSV export** of products (filtered set).

## M5. Customer Management  · Phase 3–4  · [A][Sec]
- Customers auto-created on order by mobile (+88, 11-digit BD); OTP-based account.
- Admin view: list/search customers, order history per customer.
- Privacy: store IP/UA with consent note; no plaintext sensitive data.

## M6. Shipping Zones  · Phase 3  · [A][API]
- CRUD: zone name, shipping `cost`, status.
- Used in checkout for live total = subtotal + shipping.

## M7. Order Management  · Phase 3  · [A][API][Sec]
- Order entity: order_no, customer, items (price snapshot), subtotal, shipping, total, status, payment_status, advance_paid, shipping_zone, address, **customer IP/UA**, notes.
- Admin list: filter/sort/search by status, mobile, customer name, address, date range.
- Order detail page: full info, **status change** (pending→confirmed→processing→shipped→delivered / cancelled / returned).
- **Invoice PDF** download (dompdf).
- Audit-logged status changes.

## M8. Payments — SSLCommerz  · Phase 4  · [A][API][Sec]
- Dynamic credentials (store/sandbox) from encrypted settings.
- Flows: full payment, partial/advance, shipping-charge-only.
- **Server-side `val_id` verification** (never trust redirect); idempotent payment records; IPN handling.
- Receipt on success; success/fail/cancel return pages (storefront M30).

## M9. SMS Gateway  · Phase 4  · [A][Sec]
- Provider-agnostic `SmsGateway` interface (concrete BD adapter later).
- Order-confirmation SMS; OTP delivery (M28).
- Dynamic credentials in encrypted settings; test-send.

## M10. Courier — SteadFast  · Phase 4  · [A][API][Sec]
- Dynamic API credentials.
- Create consignment from an order; fetch tracking status; store consignment_id/tracking_code.

## M11. Email / SMTP  · Phase 4  · [A][Sec]
- Dynamic SMTP settings (host/port/user/pass/encryption) + from-name/address; test-send.
- Transactional emails (order, optional receipts) when email provided.

## M12. Site Settings  · Phase 6 (some Phase 0)  · [A]
- Contact: WhatsApp number, mobile, address, email.
- Home page: header banner(s), hero copy/CTAs.
- Footer: short description, contacts, links.
- Storage driver selection (server/R2) + R2 creds (encrypted).
- Floating WhatsApp button config (number + prefilled message).

## M13. SEO Management  · Phase 5  · [A][API]
- Global SEO defaults (site title, description, default OG image).
- Per-entity SEO (category/product) overrides: meta title/description/canonical/OG.
- **Dynamic OG thumbnails** (home → header banner fallback; product/category → own image fallback).
- sitemap.xml, robots.txt generation; JSON-LD (Product, Breadcrumb) data source.

## M14. Marketing & Analytics  · Phase 5  · [A][API][Sec]
- Dynamic IDs: **GTM** container, **GA4** measurement ID, **Meta Pixel** ID, **Microsoft Clarity** ID (public IDs only → storefront).
- **Meta CAPI / Conversion API** (token server-side only): server-side events with dedup (ViewContent, AddToCart-equiv, InitiateCheckout, Purchase).
- Click/event tracking config; UTM capture.
- **Hard rule:** secret tokens never reach the client bundle.

## M15. Product Feed (Commerce Catalogs)  · Phase 5  · [A][API]
- Scheduled **CSV/feed export** for Meta Commerce Manager + Google Merchant Center.
- Product feed endpoint (price, availability, image, GTIN/SKU, link) auto-built from catalog.

## M16. Visitor Tracking  · Phase 5  · [A][API][Sec]
- Track sessions: IP, UA, path, referrer, UTM, timestamps.
- Link visitor → order where possible (IP captured on order).
- Privacy-disclosed; aggregate views in admin.

## M17. Audit Log Viewer  · Phase 0 (data) / Phase 6 (UI)  · [A][Sec]
- Read-only audit trail of sensitive actions (actor, subject, action, IP, time).
- Gated by `audit.view`; no edit/delete path.

## M18. Storage / Media Service  · Phase 0  · [A][Sec]
- `StorageRepository` (server disk / R2), public URL resolution, deletion.
- Image optimization pipeline: large uploads → high-quality responsive **WebP**.

## M19. Owner Maintenance Lock  · Phase 6  · [A][Sec]
- Reversible: storefront → maintenance mode; global session revocation; key rotation.
- Audit-logged; **never** deletes/locks files. Owner-only.

## M20. License Module (FUTURE)  · Phase 7  · [A][Sec]
- Signed heartbeat + reversible **Suspended** state + grace period (disable, never destroy).

---

# PART B — STOREFRONT MODULES (Next.js 16)

## S1. Layout, Theme & SEO Base  · Phase 2  · [S]
- Dark, minimal theme (Lovinna-inspired), shadcn + Tailwind 4, **mobile-first**.
- No header nav, no cart (catalog/inquiry model).
- Global SEO/OG, canonical, JSON-LD injection; ISR config.

## S2. Home Page  · Phase 2  · [S]
- Full-width top banner (~100% width, ~80vh).
- Hero: title, subtitle, CTAs (WhatsApp Us / Browse Collection / Company Profile).
- **Featured Collections**: category cards — 2-per-row desktop, 1-per-row mobile, big images + "View Series".
- "Price & dimensions in photos" info section + Contact-on-WhatsApp CTA.
- Footer: short about, address, tel, mobile, WhatsApp, email.

## S3. Floating Actions  · Phase 2  · [S]
- **WhatsApp** floating button (bottom-right), prefilled message.
- **Menu** floating button (bottom-left) → left **drawer** with full category list.

## S4. Category Page  · Phase 2  · [S]
- Header image ~80vh full width; category name + details.
- Product list: **1 product per row** (desktop & mobile).
- **Infinite scroll** (auto-load on scroll); skeleton loaders, Suspense.
- At the very end: categories grid (like home Featured Collections).

## S5. Product Display  · Phase 2  · [S]
- Full-width product block; **image slider** (left/right arrows + thumbnail strip below big preview).
- 3 action buttons:
  - **Price** — total; if discounted, original struck-through + discount price.
  - **Inquiry** — WhatsApp deep link with image preview + title + SKU + details (formatted inquiry message).
  - **Order Now** — opens order modal (S6).

## S6. Order Modal  · Phase 2–3  · [S]
- Mobile-responsive floating modal: product image, SKU, amount with **+ / −** quantity.
- Two buttons: **Order on WhatsApp** (sends order + product preview/details) and **Order on Web** → checkout (S7).

## S7. Web Checkout  · Phase 3  · [S][Sec]
- Qty +/−; name; **mobile** (readonly `+88`, 11-digit BD validation); full address (textarea); **shipping zone** select; optional email.
- Live: product price + shipping = **total**.
- Advance-payment notices per product rule (must pay full / partial / shipping-only).
- Zod validation, optimistic UI, loaders.
- On submit → places order (auto-registers customer by mobile via OTP), captures IP.

## S8. Order Success / Celebration  · Phase 3  · [S]
- Celebration UI + order details; **invoice PDF** download.
- Optional pay buttons: **Full payment** / **Shipping charge** via SSLCommerz (even if advance not required).
- Order-confirmation **SMS** sent to customer.

## S9. Payment Return Pages  · Phase 4  · [S][Sec]
- SSLCommerz **success / cancel / failed** pages; on success show **receipt**; SMS on success.

## S10. Customer Auth (OTP)  · Phase 4  · [S][Sec]
- Registration/login with name + mobile + **OTP** (hashed, rate-limited).
- Auto-registration on first order; Sanctum token (server-side use).

## S11. Analytics Injection  · Phase 5  · [S]
- Dynamic GTM/GA4/Pixel/Clarity from settings; client + server-side (CAPI) events; consent-aware.

---

# Cross-cutting rules (every module)
- **Security:** authz on every `:id` (no IDOR), validation (FormRequest/Zod), rate limits, secrets server-side only, server-verified payments, audit on sensitive writes.
- **Quality:** OpenSpec spec → Pest TDD (RED→GREEN→REFACTOR) → Larastan max + Pint → ship.
- **Money:** integer paisa everywhere.
- **Performance:** ISR/SSR where right, WebP images, debounced search, pagination/infinite-scroll.
