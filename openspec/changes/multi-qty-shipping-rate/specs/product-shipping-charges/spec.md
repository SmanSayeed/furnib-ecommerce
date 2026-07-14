## MODIFIED Requirements

### Requirement: Quantity-aware per-product per-zone extra delivery cost
The system SHALL allow a product to charge a cheaper delivery rate for each unit **after the first**, per shipping zone. The rate applies only when the product's multi-quantity option is enabled AND a rate is configured for that zone; otherwise the existing per-unit behaviour is unchanged.

The extra for one order line SHALL be:

```
enabled AND multiRate is set  →  extra + multiRate × (qty − 1)
otherwise                     →  extra × qty
```

The zone's base cost SHALL still be charged **once per order**, not per product line.

#### Scenario: First unit pays the full extra, each additional unit pays the cheaper rate
- **WHEN** a chair has an Inside-Dhaka extra of ৳20 and an additional-unit rate of ৳10, the option is enabled, and 3 chairs are ordered inside Dhaka (zone base ৳80)
- **THEN** the order's shipping cost is ৳120 (80 + 20 + 10×2)

#### Scenario: A single unit is never affected by the option
- **WHEN** the same chair is ordered with quantity 1
- **THEN** the shipping cost is ৳100 (80 + 20) — identical to the option being off

#### Scenario: The option is off
- **WHEN** a chair has an Inside-Dhaka extra of ৳20, the multi-quantity option is off, and 3 are ordered inside Dhaka
- **THEN** the shipping cost is ৳140 (80 + 20×3) — today's behaviour, unchanged

#### Scenario: No additional-unit rate configured for the zone
- **WHEN** the option is enabled but the zone has no additional-unit rate, and 3 chairs are ordered
- **THEN** that zone falls back to the per-unit extra: ৳140 (80 + 20×3)

#### Scenario: An additional-unit rate of zero means later units ship free
- **WHEN** the option is enabled with an additional-unit rate of ৳0, and 3 chairs are ordered inside Dhaka
- **THEN** the shipping cost is ৳100 (80 + 20 + 0×2) — zero is a deliberate value, not "unset"

#### Scenario: Each line resolves independently and the zone base is charged once
- **WHEN** an order has 3 chairs (extra ৳20, additional ৳10, enabled) and 1 table (extra ৳40, not enabled) inside Dhaka
- **THEN** the shipping cost is ৳160 (80 base once + [20 + 10×2] + [40×1])

#### Scenario: A free-shipping product contributes nothing
- **WHEN** a product has delivery charging turned off
- **THEN** it adds neither an extra nor the additional-unit rate, whatever its quantity

#### Scenario: The additional-unit rate may exceed the first-unit extra
- **WHEN** an admin configures an additional-unit rate higher than the extra
- **THEN** it is accepted and applied as written — the admin sets the prices, the system does not second-guess them

### Requirement: Admin control of the additional-unit rate
The system SHALL let an authorized admin (`catalog.manage`) enable the option per product and set an additional-unit rate per active zone, alongside the existing extra. Clearing a rate SHALL remove it; disabling the option SHALL leave the per-unit extras intact.

#### Scenario: Save an additional-unit rate
- **WHEN** an admin enables the option and saves an extra of ৳20 and an additional-unit rate of ৳10 for Inside Dhaka
- **THEN** the product's Inside-Dhaka charge row records both, and the product is flagged as multi-quantity enabled

#### Scenario: Disabling the option keeps the extras
- **WHEN** an admin unticks the option and saves
- **THEN** the product falls back to the per-unit extra at every quantity, and the stored per-zone extras are unchanged

#### Scenario: Free shipping wipes both rates
- **WHEN** an admin turns off delivery charging for the product
- **THEN** the product's shipping-charge rows are removed, exactly as today

### Requirement: Product-scoped shipping-zone endpoint exposes the additional-unit rate
The system SHALL return, for each active zone, the zone's base cost, the product's per-unit extra AND its additional-unit rate, plus whether the multi-quantity option is enabled — so the storefront's live estimate matches what order placement will charge.

#### Scenario: Endpoint returns the additional-unit rate
- **WHEN** the storefront requests shipping zones for a multi-quantity-enabled product
- **THEN** each zone carries `base`, `extra_per_unit` and `multi_extra_per_unit`, and the payload reports `multi_qty_enabled: true`

#### Scenario: Endpoint reports the option as off
- **WHEN** the product does not have the option enabled
- **THEN** the payload reports `multi_qty_enabled: false` and the storefront charges the per-unit extra at every quantity

## MODIFIED Requirements — checkout-shipping

### Requirement: Checkout's delivery estimate is quantity-aware
The storefront checkout SHALL recompute the delivery charge whenever the quantity changes, using the same formula as order placement, so the amount shown before payment is the amount charged.

#### Scenario: Raising the quantity applies the additional-unit rate
- **WHEN** a shopper on the checkout page raises the quantity of a multi-quantity-enabled chair from 1 to 3 with Inside Dhaka selected (base ৳80, extra ৳20, additional ৳10)
- **THEN** the displayed delivery charge changes from ৳100 to ৳120, and the order total updates accordingly

#### Scenario: The placed order matches what was shown
- **WHEN** that order is submitted
- **THEN** the server-computed `shipping_cost` equals the ৳120 the shopper was shown
