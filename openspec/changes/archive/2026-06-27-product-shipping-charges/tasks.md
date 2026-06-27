# Tasks — product-shipping-charges

## Phase 1 — DB + model
- [x] 1.1 Migration `product_shipping_charges` (product_id FK cascade, shipping_zone_id FK cascade, extra_cost paisa, unique(product_id, shipping_zone_id))
- [x] 1.2 `ProductShippingCharge` model (MoneyCast on extra_cost) + factory
- [x] 1.3 `Product::shippingCharges()` relation + `extraPerUnitMinorFor(int $zoneId): int`
- [x] 1.4 RED→GREEN unit test for relation + helper

## Phase 2 — backend calc + endpoint
- [x] 2.1 `PlaceOrder`: shipping = zone.base + Σ(extra_for(zone) × qty); advance uses effective shipping
- [x] 2.2 Active-zone guard (inactive/unknown zone rejected)
- [x] 2.3 `ProductShippingZoneController` + route `GET products/{slug}/shipping-zones`
- [x] 2.4 RED→GREEN feature tests (calc, guard, endpoint shape, 404)

## Phase 3 — admin product form
- [x] 3.1 `Admin\ProductFormRequest` rules for `shipping_charges.*`
- [x] 3.2 `ProductUiController` sync (delete + upsert non-zero) on store/update; expose existing charges in formData
- [x] 3.3 `catalog/products/form.tsx` Shipping charges section
- [x] 3.4 Feature test: save + clear

## Phase 4 — storefront checkout
- [x] 4.1 `lib/api.ts` `getProductShippingZones(slug)` + `lib/types.ts`
- [x] 4.2 `checkout/[slug]/page.tsx` consume product-scoped zones
- [x] 4.3 `CheckoutForm.tsx` rename + qty-aware effective cost + advance preview
- [x] 4.4 tsc + eslint (full build in Phase 7 gate)

## Phase 5 — footer settings + newsletter backend
- [x] 5.1 Site settings footer/social fields + `SiteSettingsUpdateRequest` + public settings output
- [x] 5.2 `newsletter_subscribers` migration + `NewsletterSubscriber` model + factory
- [x] 5.3 `StoreNewsletterRequest` + `NewsletterController` + `POST /api/v1/newsletter` (throttle)
- [x] 5.4 Admin `settings/site` footer fields
- [x] 5.5 RED→GREEN tests (settings save, subscribe, duplicate, invalid)

## Phase 6 — footer UI
- [x] 6.1 `components/Footer.tsx` four-column desktop / stacked mobile, settings-driven, working subscribe form
- [x] 6.2 tsc + eslint + build (storefront build green)

## Phase 7 — gate + archive + ship
- [x] 7.1 Full Pest suite (PHP 8.3, 344 pass / 2 pre-existing skip) + Pint + Larastan (changed files, 0 errors)
- [x] 7.2 Storefront tsc + eslint + build (green)
- [x] 7.3 OpenSpec archive
- [x] 7.4 ff-merge master + push
