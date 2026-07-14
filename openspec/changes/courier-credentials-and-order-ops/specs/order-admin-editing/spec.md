## ADDED Requirements

### Requirement: Admin note on an order
The system SHALL let an authorized admin (`orders.manage`) write a free-text note on any order, in any status. The note SHALL persist across status changes and SHALL be shown on the order detail and in the orders list. It is distinct from the customer's checkout note (`notes`) and from the pending note (`pending_note`, which is cleared on a forward transition).

#### Scenario: Note survives a status change
- **WHEN** an admin saves an admin note on a pending order and then confirms the order
- **THEN** the admin note is still present

#### Scenario: Note is visible in the list
- **WHEN** an order has an admin note
- **THEN** it is shown in the orders list Notes column

#### Scenario: Unauthorized staff
- **WHEN** a user without `orders.manage` submits an admin note
- **THEN** the request is forbidden

### Requirement: Editing the customer and delivery address of an order
The system SHALL let an authorized admin (`orders.manage`) correct the customer's name, mobile and email, and the order's delivery address and shipping zone.

#### Scenario: Address corrected
- **WHEN** an admin saves a new delivery address
- **THEN** only that order's address changes

#### Scenario: Customer details corrected
- **WHEN** an admin corrects the customer's name and mobile
- **THEN** the customer record is updated, and the change is visible on every order belonging to that customer

#### Scenario: Mobile collides with another customer
- **WHEN** an admin changes the mobile to one already registered to a different customer
- **THEN** the request fails validation on `mobile`

#### Scenario: Mobile is normalised before the uniqueness check
- **WHEN** an admin enters `01712345678` and another customer holds `+8801712345678`
- **THEN** the request fails validation — the two are the same number

#### Scenario: Consignment already booked
- **WHEN** an admin edits the address of an order whose consignment is already booked
- **THEN** the edit is saved AND a warning states that the courier still holds the old address

### Requirement: Changing the shipping zone recomputes the order total
The system SHALL recompute the order's shipping cost and total when its shipping zone changes, using the same server-side formula as order placement, and SHALL re-derive the payment status. The pay link and the invoice follow from the order row and therefore need no separate update.

#### Scenario: Zone changed on an unpaid order
- **WHEN** an unpaid order's zone changes from a ৳80 zone to a ৳150 zone
- **THEN** `shipping_cost` becomes ৳150 and `total` becomes `subtotal + ৳150`

#### Scenario: Zone cleared
- **WHEN** an order's shipping zone is cleared
- **THEN** `shipping_cost` becomes ৳0 and `total` equals the subtotal

#### Scenario: Per-product extras follow the new zone
- **WHEN** a product carries a per-unit extra for the new zone
- **THEN** the recomputed shipping includes that extra × quantity, exactly as at placement

#### Scenario: Zone change on a paid order is rejected
- **WHEN** an admin tries to change the zone of an order whose payment status is `paid`
- **THEN** the request fails validation — the total of a settled order cannot be altered here

#### Scenario: Zone change that would drop the total below what was paid is rejected
- **WHEN** the recomputed total would be less than the amount already paid
- **THEN** the request fails validation

#### Scenario: Zone unchanged leaves the totals alone
- **WHEN** an admin edits only the address
- **THEN** `shipping_cost` and `total` are not recomputed

## MODIFIED Requirements

### Requirement: Orders list filtering
The system SHALL let an admin filter the orders list by pending reason, in addition to status and payment status, and SHALL apply that filter to a "select all matching" bulk action. The admin SHALL also be able to set a pending order's reason inline from the list, without opening the order.

#### Scenario: Filter by pending reason
- **WHEN** an admin filters the orders list by the "call waiting" pending reason
- **THEN** only pending orders with that reason are listed

#### Scenario: Bulk action respects the filter
- **WHEN** an admin filters by a pending reason and uses "select all matching" to change status
- **THEN** only the orders matching that filter are affected

#### Scenario: Inline reason set
- **WHEN** an admin picks a new pending reason on a pending row and saves
- **THEN** that order's pending reason is updated

#### Scenario: Inline reason "other" requires a note
- **WHEN** an admin picks "other" inline without a note
- **THEN** the request fails validation on `pending_note`

#### Scenario: Unknown filter value is ignored
- **WHEN** a request supplies a pending-reason value outside the allowed set
- **THEN** it is rejected by the filter whitelist and does not reach the query
