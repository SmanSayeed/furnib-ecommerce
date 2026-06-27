## ADDED Requirements

### Requirement: Per-product per-zone extra shipping cost
The system SHALL allow an optional extra delivery cost (integer paisa, ≥ 0) to be attached to a product for a specific shipping zone, stored in `product_shipping_charges` with one row per (product, zone) pair. A product with no row for a zone SHALL contribute zero extra for that zone.

#### Scenario: Product has an extra for a zone
- **WHEN** a product has an extra cost of ৳20 configured for the "Inside Dhaka" zone
- **THEN** `Product::extraPerUnitMinorFor(insideDhakaId)` returns 2000 (paisa)

#### Scenario: Product has no extra for a zone
- **WHEN** a product has no shipping-charge row for the "Outside Dhaka" zone
- **THEN** `Product::extraPerUnitMinorFor(outsideDhakaId)` returns 0

### Requirement: Quantity-aware effective shipping on order placement
The system SHALL compute an order's shipping cost as the selected zone's base cost plus, for each line, the line product's per-unit extra for that zone multiplied by the line quantity. The advance for a shipping-charge product SHALL prepay this full effective shipping.

#### Scenario: Base plus per-unit extra times quantity
- **WHEN** "Inside Dhaka" base is ৳80, a table has an extra of ৳20 for that zone, and the order is 2 tables
- **THEN** the order's shipping cost is ৳120 (80 + 20×2)

#### Scenario: Multiple lines accumulate extras
- **WHEN** the order has 2 tables (extra ৳20 each) and 1 chair (extra ৳0) inside Dhaka with base ৳80
- **THEN** the shipping cost is ৳120 (80 + 20×2 + 0×1)

### Requirement: Active-zone guard
The system SHALL reject order placement that references a shipping zone which does not exist or is inactive.

#### Scenario: Inactive zone rejected
- **WHEN** an order is placed referencing an inactive shipping zone
- **THEN** placement fails and no order is created

### Requirement: Product-scoped shipping-zone endpoint
The system SHALL expose `GET /api/v1/products/{slug}/shipping-zones` returning each active zone (ordered) with its base cost and this product's per-unit extra cost, so the storefront can present quantity-aware shipping.

#### Scenario: Endpoint returns base and extra per zone
- **WHEN** the storefront requests shipping zones for a product slug
- **THEN** each active zone is returned with `base` (minor/display/formatted) and `extra_per_unit` (minor/display/formatted) for that product

#### Scenario: Unknown product slug
- **WHEN** the slug does not match a published product
- **THEN** the endpoint responds 404

### Requirement: Admin product shipping-charge editing
The system SHALL let an authorized admin (`catalog.manage`) set an optional extra cost per active zone on the product create/edit form, persisting only non-zero entries and replacing prior entries on save.

#### Scenario: Save extra charges
- **WHEN** an admin saves a product with an extra of ৳20 for "Inside Dhaka" and blank for others
- **THEN** exactly one `product_shipping_charges` row exists for that product (Inside Dhaka, 2000 paisa)

#### Scenario: Clearing an extra removes the row
- **WHEN** an admin edits a product and clears a previously set zone extra
- **THEN** that product/zone row is removed
