## ADDED Requirements

### Requirement: Effective product price
The system SHALL resolve a product's effective unit price as its `discount_price` when that value is non-null AND strictly less than `price`, and as `price` otherwise. A stored `discount_price` that is greater than or equal to `price` SHALL be ignored — the effective price SHALL NEVER exceed `price`.

#### Scenario: No discount set
- **WHEN** a product has `price` ৳1,000 and `discount_price` NULL
- **THEN** `Product::effectivePrice()` returns ৳1,000 and `Product::effectiveDiscount()` returns NULL

#### Scenario: Valid discount
- **WHEN** a product has `price` ৳1,000 and `discount_price` ৳800
- **THEN** `Product::effectivePrice()` returns ৳800 and `Product::effectiveDiscount()` returns ৳800

#### Scenario: Zero discount price means free
- **WHEN** a product has `price` ৳1,000 and `discount_price` ৳0
- **THEN** `Product::effectivePrice()` returns ৳0

#### Scenario: Discount equal to price is not a discount
- **WHEN** a product has `price` ৳1,000 and `discount_price` ৳1,000
- **THEN** `Product::effectivePrice()` returns ৳1,000 and `Product::effectiveDiscount()` returns NULL

#### Scenario: Discount above price is ignored
- **WHEN** a legacy product row has `price` ৳1,000 and `discount_price` ৳1,200
- **THEN** `Product::effectivePrice()` returns ৳1,000 — the price is never raised

### Requirement: Order placement charges the effective price
The system SHALL resolve each order line's unit price from `Product::effectivePrice()` at placement time, inside the existing locking transaction, and SHALL derive the line total, subtotal, order total, advance amount and gateway payable from it. The client SHALL NOT be able to supply a price.

#### Scenario: Discounted product is charged at the discounted price
- **WHEN** a product priced ৳10,000 with a ৳8,000 discount is ordered, qty 1, no shipping zone
- **THEN** `order_items.price` is ৳8,000, `orders.subtotal` is ৳8,000 and `orders.total` is ৳8,000

#### Scenario: Quantity multiplies the discounted price
- **WHEN** a product priced ৳10,000 with an ৳8,000 discount is ordered, qty 3
- **THEN** `order_items.line_total` is ৳24,000 and `orders.subtotal` is ৳24,000

#### Scenario: Undiscounted order is unchanged
- **WHEN** a product priced ৳1,000 with no discount is ordered, qty 2, in a ৳80 zone
- **THEN** `orders.subtotal` is ৳2,000, `orders.shipping_cost` is ৳80 and `orders.total` is ৳2,080 — identical to the behaviour before this change

#### Scenario: Mixed cart resolves each line independently
- **WHEN** an order contains one discounted product (৳10,000 → ৳8,000, qty 1) and one undiscounted product (৳500, qty 2)
- **THEN** `orders.subtotal` is ৳9,000

#### Scenario: Shipping is unaffected by a discount
- **WHEN** a discounted product with a ৳20 per-unit zone extra is ordered, qty 2, in a ৳80-base zone
- **THEN** `orders.shipping_cost` is ৳120 — the discount changes only the product price

#### Scenario: Gateway is charged the discounted total
- **WHEN** a full payment is initiated for an order whose only line is a ৳10,000 product discounted to ৳8,000
- **THEN** `PaymentAmount::for($order, 'full')` returns ৳8,000

#### Scenario: Price snapshot survives a later discount change
- **WHEN** an order is placed for a product discounted to ৳8,000, and the discount is afterwards removed
- **THEN** the order line still records ৳8,000

### Requirement: Advance payment follows the discounted line total
The system SHALL compute a product's advance amount from its **discounted** line total.

#### Scenario: Percentage advance on a discounted line
- **WHEN** a ৳10,000 product discounted to ৳8,000 requires a 30% partial advance, qty 1
- **THEN** `orders.advance_amount` is ৳2,400

#### Scenario: Fixed advance is capped at the discounted line total
- **WHEN** a ৳10,000 product discounted to ৳8,000 requires a fixed advance of ৳9,000, qty 1
- **THEN** `orders.advance_amount` is ৳8,000 — capped at the line total, never above it

#### Scenario: Full advance on a discounted order includes shipping
- **WHEN** a ৳10,000 product discounted to ৳8,000 requires a full advance and the selected zone costs ৳80
- **THEN** `orders.advance_amount` is ৳8,080 and equals `orders.total`

### Requirement: Order lines snapshot the discount
The system SHALL record, on each order line, the original (regular) unit price and the total amount saved whenever a discount was applied, and SHALL leave both empty when no discount was applied.

#### Scenario: Discounted line records the saving
- **WHEN** a ৳10,000 product discounted to ৳8,000 is ordered, qty 2
- **THEN** the order line has `price` ৳8,000, `original_price` ৳10,000, `discount_amount` ৳4,000 and `line_total` ৳16,000

#### Scenario: Undiscounted line records no saving
- **WHEN** a ৳1,000 product with no discount is ordered, qty 1
- **THEN** the order line has `original_price` NULL and `discount_amount` ৳0

### Requirement: The API never advertises a discount the server will not honour
The system SHALL expose `discount_price` in the public product API only when it is an effective discount, so that a client computing `discount_price ?? price` always arrives at the same amount the server will charge.

#### Scenario: Ineffective discount is not exposed
- **WHEN** a product has `price` ৳1,000 and `discount_price` ৳1,200
- **THEN** `GET /api/v1/products/{slug}` returns `discount_price: null`

#### Scenario: Effective discount is exposed
- **WHEN** a product has `price` ৳1,000 and `discount_price` ৳800
- **THEN** `GET /api/v1/products/{slug}` returns `discount_price` with minor 80000

### Requirement: Product feed reflects the effective discount
The system SHALL emit `sale_price` in the Meta/Google product feed only when the product has an effective discount, since a `sale_price` not strictly below `price` is rejected by Meta.

#### Scenario: Feed omits an ineffective sale price
- **WHEN** a product has `discount_price` equal to `price`
- **THEN** its feed row has an empty `sale_price`

## MODIFIED Requirements

### Requirement: Discount price must be below the regular price on every write path
The system SHALL reject a `discount_price` that is not strictly less than `price` on **all** product write endpoints — the admin Inertia form, the API create endpoint and the API update endpoint.

#### Scenario: API update rejects a discount at or above the price
- **WHEN** a product is updated via the catalog API with `price` ৳1,000 and `discount_price` ৳1,000
- **THEN** the request fails validation on `discount_price`

#### Scenario: API update accepts a valid discount
- **WHEN** a product is updated via the catalog API with `price` ৳1,000 and `discount_price` ৳800
- **THEN** the update succeeds
