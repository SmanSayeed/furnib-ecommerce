# Tasks — Admin Operations Console

> Loop per module: RED (Pest/Vitest from scenarios) → GREEN (impl) → REFACTOR (Pint/Larastan/eslint) → DoD gate. Money = integer paisa. Dates in Asia/Dhaka.

## Deviations (decided during build)
- **0.1 timezone:** kept `config('app.timezone') = 'UTC'` (DB stores UTC) instead of flipping it to Asia/Dhaka — flipping would shift existing rows. `App\Support\Lists\DateRange` computes Asia/Dhaka day/month boundaries and converts to UTC for `created_at` queries. Same correctness, no data risk.
- **Frontend tests:** repo has no Vitest/RTL harness. Rather than bootstrap one mid-change, the UI is verified with `tsc` + `eslint` + `vite build` + browser. Backend logic (where security lives) stays fully Pest-TDD. Vitest can be added later as its own change.
- **3.4 Customer detail:** optional, deferred.

## 0. Pre-flight
- [x] 0.1 Timezone strategy confirmed (UTC storage + Asia/Dhaka boundaries in DateRange). See deviation above.

## 1. Shared list foundation (admin-list-foundation)
- [x] 1.1 RED: unit tests — DateRange presets (today/yesterday/last_7/this_month/last_month/custom) inclusive Asia/Dhaka bounds; ListQuery rejects non-whitelisted sort → default; search OR-LIKE; filter param→column map.
- [x] 1.2 GREEN: `App\Support\Lists\DateRange` + `App\Support\Lists\ListQuery` + `App\Concerns\AppliesListFilters` (scopeApplyList).
- [x] 1.3 Frontend: `<DataTable>` sortable headers (sortKey/sort/dir/onSort) + new `<DateRangeFilter>` (preset + custom inputs). [tsc/eslint/build-verified]
- [x] 1.4 GREEN: URL-query driven via Inertia `router.get(preserveState, replace)`.

## 2. Products list (product-admin-listing) + Orders list (order-admin-listing)
- [x] 2.1 RED: ProductUiController — sort by price desc; this_month filter; status+search alongside sort; authz 403.
- [x] 2.2 GREEN: `ProductRepository::adminList(ListQuery)` + listConfig; ProductUiController@index wired; products index.tsx sortable + DateRangeFilter.
- [x] 2.3 RED: Order list — search by mobile; payment_status=partial; today preset; sort total desc; non-whitelisted sort safe; authz 403.
- [x] 2.4 GREEN: `OrderRepository` (AppliesListFilters: search order_no/customer.name/customer.mobile, status, payment_status, date, sort [created_at,total,status]); OrderController@index refactored; orders index.tsx payment-status select + DateRangeFilter + sortable headers.

## 3. Customers console (customer-admin-console)
- [x] 3.1 RED: aggregates (orders_count, total_spent paid+partial, no N+1); search by mobile; this_month join preset; sort total_spent desc; 403 without orders.view.
- [x] 3.2 GREEN: `CustomerRepository` (withCount + withSum → total_spent_minor; sort map incl. aggregate aliases).
- [x] 3.3 GREEN: `Admin\CustomerController@index` (gated orders.view) + route admin/customers + customers/index.tsx + sidebar un-`soon` (regated orders.view).
- [ ] 3.4 (optional) Customer detail — deferred.

## 4. Invoices list (invoice-admin-listing)
- [x] 4.1 RED: filter payment_status=paid; custom date range; row download returns the order's PDF; 403 without orders.view.
- [x] 4.2 GREEN: `Admin\InvoiceListController@index` (reuses OrderRepository) + route admin/invoices + invoices/index.tsx (PDF link to orders/{order}/invoice) + sidebar un-`soon`.

## 5. Dashboard analytics (admin-dashboard-analytics)
- [x] 5.1 RED: today window order KPIs only count today; revenue counts only paid; AOV = revenue/paid-count; 7-day daily series.
- [x] 5.2 GREEN: `DashboardMetrics` service + DashboardController accepts range/from/to (default this_month); order KPIs + new-customers + AOV + advance collected; daily orders/revenue series; catalog KPIs all-time.
- [x] 5.3 GREEN: dashboard.tsx — DateRangeFilter header, order KPI cards, orders/revenue ComposedChart (replaces placeholder).

## 6. Verify & ship
- [x] 6.1 Pest green (full suite 318 pass / 2 skip), Larastan max 0, Pint clean.
- [x] 6.2 Frontend: `tsc` + `eslint` clean, `vite build` ok. (Vitest deferred — see deviation.)
- [x] 6.3 DoD: authz on every list, validated/whitelisted filter input, no secret in client bundle, paginations preserve filters (withQueryString), tz-correct dates.
- [ ] 6.4 **GATED ON LOCAL TEST:** `openspec archive admin-operations-console`; ff-merge `feat/admin-operations-console` → `master`; push. Do NOT run until the owner verifies locally and approves.
