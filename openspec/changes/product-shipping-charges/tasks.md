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
- [ ] 3.1 `Admin\ProductFormRequest` rules for `shipping_charges.*`
- [ ] 3.2 `ProductUiController` sync (delete + upsert non-zero) on store/update; expose existing charges in formData
- [ ] 3.3 `catalog/products/form.tsx` Shipping charges section
- [ ] 3.4 Feature test: save + clear

## Phase 4 — storefront checkout
- [ ] 4.1 `lib/api.ts` `getProductShippingZones(slug)` + `lib/types.ts`
- [ ] 4.2 `checkout/[slug]/page.tsx` consume product-scoped zones
- [ ] 4.3 `CheckoutForm.tsx` rename + qty-aware effective cost + advance preview
- [ ] 4.4 tsc + eslint + build

## Phase 5 — footer settings + newsletter backend
- [ ] 5.1 Site settings footer/social fields + `SiteSettingsUpdateRequest` + public settings output
- [ ] 5.2 `newsletter_subscribers` migration + `NewsletterSubscriber` model + factory
- [ ] 5.3 `StoreNewsletterRequest` + `NewsletterController` + `POST /api/v1/newsletter` (throttle)
- [ ] 5.4 Admin `settings/site` footer fields
- [ ] 5.5 RED→GREEN tests (settings save, subscribe, duplicate, invalid)

## Phase 6 — footer UI
- [ ] 6.1 `components/Footer.tsx` four-column desktop / stacked mobile, settings-driven, working subscribe form
- [ ] 6.2 tsc + eslint + build

## Phase 7 — gate + archive + ship
- [ ] 7.1 Full Pest suite (PHP 8.3) + Pint + Larastan on changed files
- [ ] 7.2 Storefront tsc + eslint + build
- [ ] 7.3 OpenSpec archive
- [ ] 7.4 ff-merge master + push
