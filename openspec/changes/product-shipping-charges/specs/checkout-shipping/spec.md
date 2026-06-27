## ADDED Requirements

### Requirement: Shipping zone selection labelling
The storefront checkout SHALL label the zone selector "Shipping zone" (not "Delivery area"), and the advance line for a shipping-charge product SHALL read "(shipping charge)".

#### Scenario: Zone selector label
- **WHEN** the checkout page renders with available zones
- **THEN** the zone group is labelled "Shipping zone"

### Requirement: Quantity-aware shipping in checkout
The storefront checkout SHALL display each zone's cost as the product's effective cost for that zone (`base + extra_per_unit × quantity`) and recompute the shipping line, total, and advance preview when the quantity changes.

#### Scenario: Cost reflects quantity
- **WHEN** a product has base ৳80 and extra ৳20 inside Dhaka and the quantity is 2
- **THEN** the Inside Dhaka option and the shipping summary show ৳120

#### Scenario: Advance preview matches effective shipping
- **WHEN** the product's advance is the shipping charge and effective shipping is ৳120
- **THEN** the advance-payable-now line shows ৳120
