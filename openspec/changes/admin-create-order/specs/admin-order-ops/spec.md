## ADDED Requirements

### Requirement: Admin can create an order on a customer's behalf
An authorized admin (`orders.manage`) SHALL be able to create an order through the same placement
engine the storefront uses. The order SHALL be marked `source = admin` with the acting staff
member as `created_by`, resolve the customer find-or-create by mobile, check and decrement stock,
and compute totals server-side.

#### Scenario: A basic admin order is created at the effective price
- **WHEN** an admin submits one product (qty 2, regular ৳1,000) with a customer mobile and address
- **THEN** an order is created with `source = admin`, subtotal ৳2,000, total ৳2,000, stock reduced by 2, and it lands on the order detail page

#### Scenario: An existing customer is reused
- **WHEN** an admin creates two orders with the same mobile number
- **THEN** both orders belong to the same customer record

#### Scenario: No products is rejected
- **WHEN** an admin submits an order with no items
- **THEN** the request is rejected

#### Scenario: Only authorized staff can create
- **WHEN** a user without `orders.manage` posts to the create endpoint
- **THEN** the request is forbidden

### Requirement: Staff-only price/discount/shipping overrides, gated to admin orders
When an order's source is `admin`, the system SHALL honour a per-line unit price override, an
order-level discount, and a manual shipping figure. When the source is `storefront`, all three
SHALL be ignored — the customer is charged the effective (discount-aware) price and the computed
shipping, regardless of any override present in the payload.

#### Scenario: A unit price override applies for an admin order
- **WHEN** an admin sets a line's unit price to ৳800 for a product whose regular price is ৳1,000
- **THEN** the line is charged ৳800 and the ৳200/unit saving is snapshotted on the order item

#### Scenario: A storefront order ignores a smuggled price override
- **WHEN** a storefront placement payload carries a `price_override` of ৳0.01 for a ৳1,000 product
- **THEN** the line is charged the full ৳1,000 — the override is ignored because the source is not admin

#### Scenario: An order-level discount applies at creation
- **WHEN** an admin creates an order with a ৳300 discount and a note
- **THEN** the total is reduced by ৳300 and the discount + note + acting admin are recorded

#### Scenario: A manual shipping override applies
- **WHEN** an admin sets shipping to ৳150 on a zone whose base is ৳80
- **THEN** the order's shipping cost is ৳150

### Requirement: Admin order payment and confirmation are explicit
The system SHALL record any advance the admin collected as a manual ledger credit (deriving the
payment status), and SHALL optionally confirm the order and optionally send the pay-link SMS.

#### Scenario: An advance is recorded and drives the payment status
- **WHEN** an admin creates a ৳2,000 order and records a ৳500 advance
- **THEN** the order has a manual payment of ৳500, `advance_paid` ৳500 and payment status `partial`

#### Scenario: Immediate confirmation
- **WHEN** an admin creates an order with the confirm option
- **THEN** the order status is `confirmed`

### Requirement: Product picker lookup for the create page
The system SHALL provide an `orders.manage`-gated product search that returns each matching
published product with its effective (discount-aware) unit price, so the create form defaults
match what placement will charge.

#### Scenario: Search returns the effective price
- **WHEN** an admin searches for a product that has a discount price of ৳800 on a ৳1,000 regular price
- **THEN** the result's unit price is ৳800 and it is flagged as discounted
