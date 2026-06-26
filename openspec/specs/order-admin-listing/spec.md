# order-admin-listing Specification

## Purpose
TBD - created by archiving change admin-operations-console. Update Purpose after archive.
## Requirements
### Requirement: Order list filtering and search
The system SHALL provide an admin order list (gated `orders.view`) with keyword search over order number, customer name, and customer mobile; a status filter; a payment-status filter (unpaid / partial / paid); and a created-date range with presets (today / this month / custom).

#### Scenario: Search by customer mobile
- **WHEN** an authorized user searches the order list by a customer's mobile number
- **THEN** only orders for customers whose mobile matches are returned

#### Scenario: Filter by payment status
- **WHEN** an authorized user filters orders by payment status = partial
- **THEN** only orders with payment_status `partial` are returned

#### Scenario: Filter by today
- **WHEN** an authorized user applies the `today` date preset
- **THEN** only orders placed today (Asia/Dhaka) are returned

### Requirement: Order list sorting
The system SHALL allow sorting the admin order list by created date, total, and status (default: newest first), using the whitelisted sort mechanism.

#### Scenario: Sort by total descending
- **WHEN** an authorized user sorts the order list by total, descending
- **THEN** the highest-value orders appear first

