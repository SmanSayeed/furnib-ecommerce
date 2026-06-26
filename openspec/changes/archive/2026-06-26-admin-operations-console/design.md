# Design — Admin Operations Console

## Context

Phase 1 built a product admin list; Phase 3 built an orders list. Both hand-roll their query logic and table UI, and neither exposes sorting or date filtering. Customers and Invoices have backing data (the `customers` table, order-derived invoices) but no admin surface. The dashboard is all-time catalog counts. This change adds the missing operational surfaces on a single shared foundation so behaviour is consistent and testable.

## Goals / Non-goals

**Goals:** one filtering/sorting/date contract reused by every admin list; sortable tables; today/range/month reporting; Customers + Invoices lists; dashboard order/revenue analytics.

**Non-goals:** customer editing/merge, refunds, coupon engine, inventory adjustments, consignment list, staff/roles UI — tracked separately. No new payment or courier work.

## Key decisions

1. **Shared list contract over a query package.** Rather than add `spatie/laravel-query-builder`, add a small first-party `App\Support\Lists\ListQuery` that a repository applies to an Eloquent builder. Keeps the existing repository pattern, no new dependency, and lets us whitelist per resource.
   - `ListQuery::fromRequest($request, allowedSorts, searchColumns, defaultSort)` → value object.
   - `AppliesListFilters` trait: `applyList(Builder, ListQuery): Builder` applies search (OR `LIKE` over whitelisted columns, incl. `whereHas` relations), equality filters (status, payment_status, category_id), date range, sort, and returns the builder for `->paginate()`.

2. **Timezone-aware date presets, computed server-side.** A `DateRange` value object resolves a named preset (`today | yesterday | last_7 | this_month | last_month | custom`) plus explicit `from`/`to` into an inclusive `[startOfDay, endOfDay]` pair in `config('app.timezone')` (Asia/Dhaka). Filtering uses `whereBetween('created_at', [from, to])` with the resolved UTC instants — correct across the day boundary. Custom range falls back to `whereDate >= from` / `<= to`.

3. **Sort is always whitelisted.** Unknown/blank `sort` falls back to the resource default; `dir` is `asc|desc` only. Prevents SQL injection via column names and keeps URLs stable.

4. **URL query string is the single source of truth (frontend).** Sortable headers and the date-range control push params (`search`, `status`, `payment_status`, `range`, `from`, `to`, `sort`, `dir`, `page`) via Inertia `router.get(..., { preserveState, replace })`. Pagination/sort/filter all round-trip through the server, so refresh and share work.

5. **Aggregates without N+1.** Customers list uses `withCount('orders')` and `withSum(['orders as total_spent_minor' => paid], 'total')`. Total spent counts only `payment_status in (paid, partial)`. Money stays integer paisa end-to-end; formatted on the resource.

6. **Invoices = projection of orders.** No `Invoice` model. `InvoiceListController@index` reuses the order query (same `OrderRepository`) and renders a billing-focused page; each row links to the existing `orders/{order}/invoice` PDF route. Default filter shows all orders; payment-status filter narrows to paid/partial/unpaid.

7. **Dashboard window.** `DashboardController` accepts the same `range`/`from`/`to`. Order KPIs: count, revenue = sum of `total` for `payment_status = paid` in window, advance collected = sum `advance_paid`, new customers = `customers` created in window, AOV = revenue / paid orders. Time series = orders + revenue grouped by day across the window. Catalog KPIs stay all-time.

## Frontend building blocks

- `data-table.tsx` (existing) gains optional `sortKey` per column + a `sort`/`dir` prop and `onSort` callback → renders an asc/desc affordance on sortable headers.
- `date-range-filter.tsx` (new): preset dropdown + two date inputs (shown for "custom"), emits `{ range, from, to }`.
- `filter-bar.tsx` (existing) hosts search + status + payment-status + date-range controls per page.

## Risks / mitigations

- **Timezone misconfig** → wrong day/month buckets. Mitigation: a test asserting `app.timezone = Asia/Dhaka` and preset-boundary tests.
- **`LIKE %term%` on large tables** → slow. Mitigation: existing unique index on `mobile`; add indexes on `order_no`, `products.sku/title` if needed (noted, scale-dependent).
- **Aggregate sort on `total_spent`** → must sort on the aggregated alias, not a column. Covered by a scenario.
