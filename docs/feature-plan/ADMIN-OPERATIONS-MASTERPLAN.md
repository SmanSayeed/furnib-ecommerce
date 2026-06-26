# Admin Operations Console — Master Plan

> Completes the admin dashboard's day-to-day operations: list **search / sort / status / date (today · range · month)** across Products, Orders, Invoices, Customers, plus **Dashboard analytics**. Built on one shared foundation, test-driven.
>
> **OpenSpec change:** `openspec/changes/admin-operations-console/` (proposal · design · specs · tasks) — `openspec show admin-operations-console`.
> **Companion:** `MASTER-PLAN.md`, `ROADMAP.md`, `MODULES.md`. Follows the repeatable loop: opsx propose → RED → GREEN → REFACTOR → DoD gate → archive.

## Where this sits

| Admin section | Before | After this change |
|---|---|---|
| Dashboard | all-time catalog counts | + windowed order/revenue KPIs + chart |
| Products | search/status/category | + sortable columns + date filters |
| Orders | search/status | + payment-status + date + sort |
| Invoices | per-order PDF only | **new** order-derived list + filters + PDF |
| Customers | none | **new** list with aggregates + filters |

Out of scope (separate changes): Inventory, Inquiries capture, Payments/Transactions list, Coupons, Consignments list, Staff & Roles UI, Integrations page, Audit-log UI.

## Decisions (locked)
- **Invoices** = projection of **all** orders, with a payment-status filter (no separate Invoice entity).
- **Customers** and **Invoices** reuse the **`orders.view`** permission — no new permission.
- **Money** stays integer paisa end-to-end; formatted at the resource boundary.
- **Dates** resolved in **Asia/Dhaka**; presets computed server-side; ranges inclusive.
- **Sort** always whitelisted per resource (injection-safe); unknown → default.
- **URL query string** is the single source of truth on the frontend (shareable, refresh-safe).

---

## Modules → Features → Sub-features → TDD

### M1. Shared List Foundation  `admin-list-foundation`
The reusable spine every list sits on.
- **F1.1 Filter contract** (`ListQuery` + `AppliesListFilters`)
  - keyword search (per-resource column/relation whitelist)
  - equality filters: status, payment_status, category_id
  - whitelisted sort (column + `asc|desc`) with safe fallback
  - pagination that preserves active params
  - *TDD:* non-whitelisted sort → default; filters persist across pages.
- **F1.2 Date presets** (`DateRange`)
  - `today · yesterday · last_7 · this_month · last_month · custom`
  - inclusive day bounds in Asia/Dhaka; filters `created_at`
  - *TDD:* today respects tz boundary; this_month within calendar month.
- **F1.3 Reusable UI**
  - `<DataTable>` sortable headers (asc/desc toggle)
  - `<DateRangeFilter>` (preset dropdown + custom from/to)
  - *TDD (Vitest/RTL):* header click toggles dir + calls onSort; preset emits range.

### M2. Products & Orders lists  `product-admin-listing` (mod) · `order-admin-listing`
- **F2.1 Products** — sortable (title/price/stock/created) + date range/preset. *(backend repo already supports sort/from/to — wire controller + UI.)*
- **F2.2 Orders** — search (order_no/name/mobile) · status · **payment-status** · date range/preset · sort (date/total/status); filtering moved into `OrderRepository`.
  - *TDD:* search by mobile; payment_status=partial; today preset; sort by total desc.

### M3. Customers console  `customer-admin-console`
- **F3.1 List** — search (name/mobile/email), join-date range, pagination; columns: name, mobile, email, #orders, total spent, joined.
- **F3.2 Aggregates** — `withCount('orders')` + `withSum` paid/partial → total spent (no N+1); sort by name/joined/orders/spent.
- **F3.3 Authz** — gated `orders.view`; sidebar link enabled.
- **F3.4 (optional)** Customer detail — order history.
  - *TDD:* aggregates correct; sort by total_spent desc; this_month new customers; 403 without perm.

### M4. Invoices list  `invoice-admin-listing`
- **F4.1 List** — order-derived: invoice no (order_no), customer, total, payment status, date; search · payment-status · date · sort.
- **F4.2 PDF download** — per-row, reuses `orders/{order}/invoice`.
  - *TDD:* filter paid; custom range; row download returns the order's PDF; 403 without perm.

### M5. Dashboard analytics  `admin-dashboard-analytics`
- **F5.1 Windowed KPIs** — orders, revenue (paid), advance collected, new customers, AOV; window today/this-month/last-7/custom; catalog KPIs stay all-time.
- **F5.2 Time series** — daily orders + revenue across the window → chart.
  - *TDD:* today window scopes order KPIs; revenue counts only paid; 7-day series spans range.

---

## Execution phases (each: RED → GREEN → REFACTOR → gate → commit)
1. **Foundation** (M1) — trait + DateRange + DataTable/DateRangeFilter.
2. **Products + Orders** (M2).
3. **Customers** (M3).
4. **Invoices** (M4).
5. **Dashboard** (M5).
6. **Ship** — Pest/Vitest green · Larastan max · Pint · eslint/tsc · DoD · `openspec archive`.

## Definition of Done (every list)
Authz enforced · filter input validated · pagination preserves filters · dates tz-correct · sort whitelisted · no secret in client bundle · tests green · static analysis clean.

## Corner cases tracked
Timezone boundaries (today/month) · inclusive date ranges · sort-whitelist injection guard · aggregate-alias sorting · `LIKE %term%` index cost at scale (mobile already indexed; add order_no/sku/title indexes if needed) · empty states · permission gates on new surfaces.
