## ADDED Requirements

### Requirement: Manual payments record the channel
A manual payment (order-detail adjustment or create-order advance) SHALL record which channel the
money moved through — bKash, Nagad, Rocket, bank, cash or other — alongside the required note that
carries the transaction id / reference. The method SHALL be shown in the payment ledger.

#### Scenario: A manual credit records its method and reference
- **WHEN** an admin records a ৳1,000 payment received via bKash with the note "TrxID 9XY12ABZ"
- **THEN** the ledger entry stores method `bkash` and the note, and advance_paid reconciles up by ৳1,000

#### Scenario: A method is required
- **WHEN** an admin submits a manual payment without a method
- **THEN** the request is rejected

#### Scenario: An unknown method is rejected
- **WHEN** an admin submits a method outside the allowed set
- **THEN** the request is rejected

#### Scenario: The create-order advance records a method
- **WHEN** an admin creates an order with a ৳500 advance collected via bKash and a transaction id
- **THEN** the order's ledger has that ৳500 credit with method `bkash` and the given note

#### Scenario: The create-order advance requires a method
- **WHEN** an admin creates an order with an advance amount but no method
- **THEN** the request is rejected

### Requirement: Manual delivery-charge override on an order
An authorized admin (`orders.manage`) SHALL be able to set an existing order's delivery charge to
a specific amount. The total SHALL be recomputed as `subtotal − discount + shipping` and the
payment status reconciled. The override SHALL be rejected when it would change the total of a paid
order, when a courier consignment already exists, or when it would drop the total below what the
customer has already paid.

#### Scenario: Override recomputes the total
- **WHEN** an admin sets the delivery charge of an unpaid ৳10,100 order (subtotal ৳10,000) to ৳250
- **THEN** the shipping cost is ৳250 and the total is ৳10,250, and the pay link charges ৳10,250

#### Scenario: A discount is preserved
- **WHEN** an admin overrides shipping on an order that has a ৳500 order discount
- **THEN** the total is `subtotal − 500 + newShipping`

#### Scenario: Free delivery
- **WHEN** an admin sets the delivery charge to ৳0
- **THEN** the total equals the subtotal (minus any discount)

#### Scenario: A paid order is rejected
- **WHEN** an admin tries to override shipping on a paid order
- **THEN** the request is rejected and the shipping is unchanged

#### Scenario: A booked order is blocked
- **WHEN** an admin tries to override shipping on an order with a courier consignment
- **THEN** the request is rejected with a message to cancel and re-book

#### Scenario: Below-paid is rejected
- **WHEN** the override would drop the total below what the customer has already paid
- **THEN** the request is rejected with a message to record a refund first

#### Scenario: Only authorized staff
- **WHEN** a user without `orders.manage` posts the override
- **THEN** the request is forbidden
