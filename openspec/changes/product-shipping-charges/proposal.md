## Why

Shipping is currently a single flat cost per zone — every product in a zone ships for the same price. But bulky items (a dining table, a wardrobe) genuinely cost more to deliver than a chair. The shop owner needs to attach an **optional extra delivery charge per product, per zone**, on top of the zone's base cost, scaled by quantity. The storefront checkout must reflect this live, and its label should read "Shipping zone" (not "Delivery area"). Separately, the storefront footer is a single centered column with hardcoded contact text and a dead newsletter box; the owner wants an admin-managed, four-column footer with working newsletter capture.

## What Changes

- **Per-product, per-zone shipping surcharge**: a new `product_shipping_charges` table linking a product + shipping zone to an optional extra cost (paisa). Effective shipping for an order becomes `zone.base + Σ_line(product.extra_for(zone) × line.qty)`. The extra is **per unit** (× quantity).
- **Order placement** computes effective shipping server-side and rejects inactive/unknown zones; the shipping-charge advance now prepays the full effective shipping.
- **New storefront endpoint** `GET products/{slug}/shipping-zones` returns each active zone's base plus this product's per-unit extra, so checkout can show the real, qty-aware cost.
- **Admin product form** gains a "Shipping charges" section: one optional extra-cost input per active zone, validated and synced (only non-zero rows persisted).
- **Checkout** renames "Delivery area" → "Shipping zone", and its zone list + summary + advance preview use `base + extra × qty`, recomputed as quantity changes.
- **Footer** becomes admin-managed (social links, about/quick links, contact block) rendered as four columns on desktop and stacked on mobile, with a working newsletter subscribe form backed by a new `newsletter_subscribers` table + `POST /api/v1/newsletter` (validated, unique, rate-limited).

## Capabilities

### New Capabilities
- `product-shipping-charges`: per-product per-zone optional extra delivery cost (× qty), surfaced via a product-scoped zone endpoint and computed into order totals.
- `storefront-footer`: admin-managed four-column footer content + newsletter subscription capture.

### Modified Capabilities
- `checkout-shipping`: zone selection renamed to "Shipping zone" and made quantity-aware using each product's effective (base + extra) cost.

## Impact

- **Backend**: new `product_shipping_charges` migration + `ProductShippingCharge` model + `Product::shippingCharges()` / `extraPerUnitMinorFor()`; `PlaceOrder` shipping calc + active-zone guard; new `ProductShippingZoneController` (`products/{slug}/shipping-zones`); `Admin\ProductFormRequest` rules + `ProductUiController` sync; new `newsletter_subscribers` migration + `NewsletterSubscriber` model + `NewsletterController` + `StoreNewsletterRequest`; site-settings `footer`/`social` group + `SiteSettingsUpdateRequest`; `routes/api.php`, `routes/web.php`.
- **Storefront**: `lib/api.ts` (`getProductShippingZones`), `lib/types.ts`, `checkout/[slug]/page.tsx`, `components/CheckoutForm.tsx`, `components/Footer.tsx`, footer settings consumption in `lib/config`/layout.
- **Admin UI**: `catalog/products/form.tsx` (shipping-charge section), `settings/site` (footer/social fields).
- **Depends on**: existing `ShippingZone` + `active` scope, `MoneyCast`, `SettingsService`, `AdvancePayment`, Phase-0 RBAC (`catalog.manage`, `settings.manage`).
- **Decisions locked**: extra is per-unit (× qty); footer content is admin-managed via site settings; desktop four-column / mobile stacked; newsletter stored in a dedicated table (admin list deferred).
