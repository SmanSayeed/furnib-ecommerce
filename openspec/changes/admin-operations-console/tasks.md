# Tasks — Admin Operations Console

> Loop per module: RED (Pest/Vitest from scenarios) → GREEN (impl) → REFACTOR (Pint/Larastan/eslint) → DoD gate. Money = integer paisa. Dates in Asia/Dhaka.

## 0. Pre-flight
- [ ] 0.1 Confirm `config('app.timezone') === 'Asia/Dhaka'` (set + test asserting it); add `APP_TIMEZONE` to env docs.

## 1. Shared list foundation (admin-list-foundation)
- [ ] 1.1 RED: unit tests — `DateRange` resolves each preset to inclusive Asia/Dhaka bounds (today/yesterday/last_7/this_month/last_month/custom); boundary at 23:59:59; `ListQuery` rejects non-whitelisted sort → default; search builds OR-LIKE over given columns.
- [ ] 1.2 GREEN: `App\Support\Lists\DateRange` (preset → [from,to] in app tz) + `App\Support\Lists\ListQuery` (search cols, filters, sort whitelist, dir, page) + `AppliesListFilters` trait (`applyList(Builder,$q)`).
- [ ] 1.3 Frontend RED: Vitest/RTL — `<DataTable>` sortable header toggles `dir` and calls `onSort`; `<DateRangeFilter>` emits `{range,from,to}` and shows custom inputs only for `custom`.
- [ ] 1.4 GREEN: extend `resources/js/components/admin/data-table.tsx` (per-column `sortKey`, `sort`/`dir` props, sort affordance) + new `resources/js/components/admin/date-range-filter.tsx`; URL-query driven via Inertia `router.get(preserveState, replace)`.

## 2. Products list (product-admin-listing) + Orders list (order-admin-listing)
- [ ] 2.1 RED: ProductUiController — sort by price desc / created desc; `this_month` filter narrows set; authz 403 without `catalog.view`.
- [ ] 2.2 GREEN: expose `sort`,`dir`,`range`,`from`,`to` in `ProductUiController@index` (repo already supports); wire products `index.tsx` sortable headers + DateRangeFilter.
- [ ] 2.3 RED: Order list — search by mobile; filter payment_status=partial; `today` preset; sort by total desc; authz 403.
- [ ] 2.4 GREEN: `OrderRepository` (uses `AppliesListFilters`: search order_no/customer.name/customer.mobile, status, payment_status, date range, sort whitelist [created_at,total,status]); refactor `OrderController@index` to use it; wire orders `index.tsx` (payment-status select + DateRangeFilter + sortable headers).

## 3. Customers console (customer-admin-console)
- [ ] 3.1 RED: customers list returns aggregates (orders_count, total_spent paid+partial, no N+1); search by mobile; `this_month` join preset; sort by total_spent desc; 403 without `orders.view`.
- [ ] 3.2 GREEN: `CustomerRepository` (`withCount('orders')` + `withSum` paid/partial → `total_spent_minor`; search name/mobile/email; sort whitelist incl. aggregate alias) + `CustomerResource`.
- [ ] 3.3 GREEN: `Admin\CustomerController@index` (gated `orders.view`) + route `admin/customers` + `resources/js/pages/customers/index.tsx` (DataTable + FilterBar + DateRangeFilter) + sidebar link (un-`soon`).
- [ ] 3.4 (optional) Customer detail: `@show` + order history page.

## 4. Invoices list (invoice-admin-listing)
- [ ] 4.1 RED: invoice list filters by payment_status=paid; custom date range; row download returns the order's PDF; 403 without `orders.view`.
- [ ] 4.2 GREEN: `Admin\InvoiceListController@index` (reuses `OrderRepository`) + route `admin/invoices` + `resources/js/pages/invoices/index.tsx` (columns: order_no/customer/total/payment/date + PDF link to `orders/{order}/invoice`) + sidebar link (un-`soon`).

## 5. Dashboard analytics (admin-dashboard-analytics)
- [ ] 5.1 RED: `today` window order KPIs only count today's orders; revenue counts only `paid`; AOV = revenue/paid-count; daily series spans a 7-day window.
- [ ] 5.2 GREEN: extend `DashboardController@index` (accept `range`/`from`/`to`; order KPIs + new-customers + AOV + advance collected; daily orders/revenue series); keep catalog KPIs all-time.
- [ ] 5.3 GREEN: `resources/js/pages/dashboard.tsx` — DateRangeFilter header, KPI cards, orders/revenue chart (replace the analytics placeholder).

## 6. Verify & ship
- [ ] 6.1 Pest green (incl. new feature/unit), Larastan max 0, Pint clean.
- [ ] 6.2 Frontend: Vitest/RTL green, `eslint` + `tsc` clean.
- [ ] 6.3 DoD: authz on every list, validated filter input, no secret in client bundle, paginations preserve filters, tz-correct dates.
- [ ] 6.4 `openspec archive admin-operations-console`; commit on `feat/admin-operations-console`; merge `master`; push.
