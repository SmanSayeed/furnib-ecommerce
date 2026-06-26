## Why

The admin panel can create/edit catalog and view orders, but day-to-day operations are weak. Lists have no sortable columns, no date / today / month filtering, and orders have no payment-status filter. There is **no Customers list and no Invoices list at all**. The dashboard shows only all-time catalog counts — no order or revenue analytics. A shop owner needs to find, sort, and report on products, orders, invoices, and customers by status, name/SKU, mobile, and date range to actually run the business.

## What Changes

- Add a shared admin **list foundation**: reusable keyword search, status filters, timezone-aware date-range + named presets (today / yesterday / last 7 / this month / last month / custom), whitelisted sorting (column + direction), and pagination — a backend trait plus a reusable React table with sortable headers and a date-range control.
- **Products list**: expose sortable columns + date-range / preset filtering (the repository already supports sort/from/to; only the controller + UI need wiring).
- **Orders list**: add a payment-status filter, date-range + presets in the UI, sortable columns; move filtering into an `OrderRepository` for consistency.
- **Customers console (new)**: list with search (name / mobile / email), join-date range + presets, sortable columns including aggregated order count and total spent; optional customer detail with order history.
- **Invoices list (new)**: an order-derived billing view with search (order_no / customer), payment-status filter, date-range, sort, and per-row PDF download.
- **Dashboard analytics**: a selectable window (today / this month / last 7 / custom) with order, revenue (paid), advance-collected, new-customer, and AOV KPIs plus an orders-and-revenue time-series chart; catalog KPIs retained.

## Capabilities

### New Capabilities
- `admin-list-foundation`: shared, timezone-aware search / status / date-range / preset / sort / paginate mechanism + reusable sortable-table and date-range UI.
- `order-admin-listing`: orders list with status + payment-status + date-range + sort + search.
- `customer-admin-console`: customers list (and optional detail) with aggregates, search, date, sort.
- `invoice-admin-listing`: order-derived invoices list with filters + per-row PDF download.
- `admin-dashboard-analytics`: windowed order/revenue KPIs + time-series chart.

### Modified Capabilities
- `product-admin-listing`: add sortable-column UI + date-range / preset filtering (wire the existing backend support).

## Impact

- **Code**: `app/Support/Lists` (new `ListQuery` + `DateRange` + `AppliesListFilters`), `app/Repositories/Eloquent` (new `OrderRepository`, `CustomerRepository`; extend `ProductRepository`), `app/Http/Controllers/Admin` (`OrderController`, new `CustomerController`, new `InvoiceListController`, `DashboardController`), `app/Http/Requests/Admin` (list-filter requests), `routes/web.php`, `resources/js/components/admin` (`data-table` sortable, new `date-range-filter`), `resources/js/pages` (`catalog/products`, `orders`, new `customers`, new `invoices`, `dashboard`), `resources/js/components/app-sidebar.tsx`.
- **Depends on** Phase 0 RBAC (`catalog.view`, `orders.view`), `MoneyCast`, audit logging.
- **Decisions locked**: Invoices = projection of all orders (with payment-status filter); Customers + Invoices reuse the `orders.view` permission (no new permission).
- **Requires** `config('app.timezone') = Asia/Dhaka` for correct today/month boundaries (verified in tasks).
