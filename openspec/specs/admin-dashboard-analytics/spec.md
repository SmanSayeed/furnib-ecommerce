# admin-dashboard-analytics Specification

## Purpose
TBD - created by archiving change admin-operations-console. Update Purpose after archive.
## Requirements
### Requirement: Windowed dashboard KPIs
The system SHALL compute dashboard order/revenue KPIs over a selectable window (today / this month / last 7 days / custom range, Asia/Dhaka): orders count, revenue (sum of order totals where payment_status is paid), advance collected (sum of advance_paid), new customers (created in window), and average order value. Catalog KPIs (products, published, categories, low stock) SHALL remain all-time.

#### Scenario: Today window recomputes order KPIs
- **WHEN** an authorized user selects the `today` window
- **THEN** the order/revenue KPIs reflect only orders placed today (Asia/Dhaka)

#### Scenario: Revenue counts only paid orders
- **WHEN** the dashboard computes revenue for a window containing both unpaid and paid orders
- **THEN** revenue includes only orders with payment_status `paid`

### Requirement: Orders and revenue time series
The system SHALL provide an orders-and-revenue time series grouped by day across the selected window for charting on the dashboard.

#### Scenario: Series spans the selected range
- **WHEN** an authorized user selects a 7-day window
- **THEN** the series contains a per-day orders count and revenue total across those days

