# customer-admin-console Specification

## Purpose
TBD - created by archiving change admin-operations-console. Update Purpose after archive.
## Requirements
### Requirement: Customer list
The system SHALL provide an admin customer list (gated `orders.view`) with keyword search over name, mobile, and email; a join-date range with presets; and pagination. Each row SHALL show name, mobile, email, order count, total spent, and joined date.

#### Scenario: Search by mobile
- **WHEN** an authorized user searches customers by mobile
- **THEN** only customers whose mobile matches are returned

#### Scenario: New customers this month
- **WHEN** an authorized user applies the `this_month` join-date preset
- **THEN** only customers created in the current month (Asia/Dhaka) are listed

### Requirement: Customer aggregates and sorting
The system SHALL compute each customer's order count and total spent (sum of order totals where payment_status is paid or partial, in integer paisa) without N+1 queries, and SHALL allow sorting the list by name, joined date, order count, and total spent.

#### Scenario: Sort by total spent
- **WHEN** an authorized user sorts the customer list by total spent, descending
- **THEN** the highest-spending customers appear first

#### Scenario: Order count reflects the customer's orders
- **WHEN** a customer has three orders
- **THEN** that customer's row shows an order count of 3

### Requirement: Customer list authorization
The system SHALL deny the customer list to users without the `orders.view` permission.

#### Scenario: Unauthorized access blocked
- **WHEN** a user without `orders.view` requests the customer list
- **THEN** the system responds with 403 Forbidden

