# Furnib — Admin Panel Restructure Plan (2026-06-28)

Owner-requested admin nav + pages overhaul. Built **phase by phase**, each phase
TDD'd (RED Pest → GREEN → Pint/Larastan), storefront/admin lint + build, then
committed & pushed. No new RBAC permission is introduced, so **no server reseed**
is needed — every page reuses an existing permission an admin/owner already holds.

Locked decisions (owner):
- **Staff & roles = role management only** for now — list existing users, change
  their role, activate/deactivate. **No new-user creation** (no admin-set
  passwords — avoids the plaintext-credential risk).
- **Footer settings = split into dedicated pages** — pull the footer social icons
  and footer details (links/contact) out of "Site & branding" into their own
  pages under a collapsible "Footer settings" sidebar group.

Backend already present (UI-only work): `Payment`, `Shipment`,
`NewsletterSubscriber`, `User` + spatie roles, activity-log (AuditLogController
returns JSON today). Sidebar (`nav-groups.tsx`) is currently **flat** — needs
collapsible sub-menu support for the Footer-settings dropdown.

---

## Target sidebar

```
Overview   → Dashboard
Catalog    → Products, Categories, Inventory(Soon)
Sales      → Orders, Invoices                       [removed: Inquiries]
Customers  → Customers
Payments   → Transactions                            [new]
Shipping   → Shipping charge (renamed), Consignments [new]
Marketing  → Tracking & Pixels                       [removed: Coupons]
Settings   → Profile, Security, Appearance,
             Site & branding,
             Footer settings ▾ {Footer pages, Footer social icons,
                                 Subscriptions, Footer details}   [new dropdown]
             Marketing & tracking, Storage (R2),
             Staff & roles,                            [new]
             Integrations
System     → Developer tools (moved here), Audit log [new UI], Maintenance [new UI]
```
The settings page's own left sub-nav (`layouts/settings/layout.tsx`) mirrors every
Settings destination so the two navs stay consistent.

---

## Phase A — Cleanup, renames, group moves (label-only, no backend)
1. `app-sidebar.tsx`: remove **Inquiries** (Sales) and **Coupons** (Marketing).
2. Rename nav label **Pages → Footer pages**; **Shipping zones → Shipping charge**
   (route paths unchanged: `/admin/pages`, `/admin/shipping/zones`).
3. Update the corresponding page headings: pages/index "Pages"→"Footer pages",
   shipping/zones heading "Shipping zones"→"Shipping charges".
4. Move **Developer tools** out of its own "Developer" group into the **System**
   group (keep `developer.access` gate).
- Verify: admin eslint + build. No tests (label-only).

## Phase B — Read-only list pages (from existing data)
Each = Inertia controller (read-only) + route + `pages/<area>/index.tsx` using the
shared `DataTable`/`PageHeader`/`EmptyState`, un-"Soon" the nav item, Pest feature
test (authz + renders), Pint/phpstan.

- **B1 Transactions** (`Payment`) — `Admin\PaymentController@index`, route
  `/admin/payments` (perm `payments.view`). Columns: order no, amount, gateway,
  status, txn/val id, date. Filter by status (reuse list trait if easy, else
  simple). Mask nothing secret (no card data stored).
- **B2 Consignments** (`Shipment`) — `Admin\ConsignmentController@index`, route
  `/admin/shipping/consignments` (perm `orders.view`). Columns: order no, courier,
  consignment id, status, tracking link, date.
- **B3 Subscriptions** (`NewsletterSubscriber`) — `Admin\SubscriberController@index`
  + `@export` (CSV), routes `/admin/subscribers`, `/admin/subscribers/export`
  (perm `settings.manage`). Columns: email, source, subscribed-at. CSV export
  streamed (no secrets).
- **B4 Audit log** — convert `AuditLogController` to render Inertia `pages/system/
  audit.tsx` (keep/keep-alongside JSON if used elsewhere). Columns: when, causer,
  event, subject, description. Read-only, redact nothing sensitive (activity log
  already stores safe fields); paginate latest N.

## Phase C — Maintenance page (System)
- `Admin\MaintenancePageController@edit` rendering `pages/system/maintenance.tsx`
  (perm `maintenance.manage`, owner-only). Shows current lock state + a toggle that
  posts to the existing `maintenance.update` route. Clear warning copy. Reuse the
  existing `MaintenanceController@update` backend.

## Phase D — Footer settings dropdown + split pages
1. **Sidebar nesting**: extend `nav-groups.tsx` + `AdminNavItem` to support
   `children?: AdminNavItem[]`, rendered with shadcn `Collapsible` +
   `SidebarMenuSub`/`SidebarMenuSubItem` (active-aware, permission-filtered).
2. **Footer settings** group/dropdown with children:
   - **Footer pages** → `/admin/pages` (existing).
   - **Footer social icons** → new `/settings/footer/social` — move the 7-platform
     "Follow us" form (url + show/hide toggle) out of Site & branding into its own
     settings page (`Settings\FooterSocialController` edit/update, reusing the same
     `branding` keys + validation already in `SiteSettingsUpdateRequest`; split that
     request or add a focused `FooterSocialUpdateRequest`).
   - **Subscriptions** → `/admin/subscribers` (from B3).
   - **Footer details** → new `/settings/footer/details` — footer quick-links
     (`about_links`) + contact block, moved out of Site & branding
     (`Settings\FooterDetailController`).
3. Trim **Site & branding** to logos/favicon/banners/site identity only (socials &
   links now live under Footer settings). Keep backward-compatible keys.
- Tests: each new settings controller persists + authz (perm `settings.manage`),
  XSS guards retained (http(s)/relative URL rules).

## Phase E — Staff & roles (role management only)
- Precheck: does `users` have an active flag? If not, migration
  `users.is_active boolean default true` (+ block login when false via existing
  account middleware or a check).
- `Admin\StaffController@index` (list users: name, email, role, active, last login)
  + `@updateRole` + `@toggleActive` (perm `users.manage`), route `/admin/staff`.
- `pages/staff/index.tsx`: table + role `<select>` + activate/deactivate switch.
- **Security guards (mandatory):**
  - Only the **owner** may grant/revoke the `owner` role; admins can manage
    non-owner roles only.
  - Cannot change your **own** role or deactivate **yourself** (lockout guard).
  - Cannot deactivate or demote the **owner** account.
  - Role set comes from `config('rbac.roles')` — no arbitrary strings.
- Tests: authz, owner-only owner-role assignment, self-lockout blocked,
  owner-protection, role change persists, deactivate blocks login.

## Phase F — Settings sub-nav consistency
- `layouts/settings/layout.tsx`: list **all** settings destinations —
  Profile, Security, Appearance, Site & branding, Footer pages, Footer social
  icons, Footer details, Subscriptions, Marketing & tracking, Storage (R2),
  Staff & roles, Integrations — grouped sensibly (Account / Store / Footer /
  System) so the in-page nav matches the main sidebar.

---

## Cross-cutting
- **Perms used (all already seeded):** payments.view, orders.view,
  settings.manage, audit.view, maintenance.manage, users.manage. No reseed.
- **Deploy:** Phase E may add a `users.is_active` migration → auto-runs on backend
  redeploy. Admin assets rebuild via Docker (pnpm-lock already synced). Storefront
  unaffected except Phase D leaves the public footer API unchanged.
- **Gate per phase:** Pest (changed/feature), Pint, phpstan lvl-7 (app files),
  admin `eslint` + `pnpm build`. Commit + push each phase.

## Status
- [ ] Phase A  — cleanup/rename/move
- [ ] Phase B  — Transactions, Consignments, Subscriptions, Audit log
- [ ] Phase C  — Maintenance page
- [ ] Phase D  — Footer settings dropdown + split pages
- [ ] Phase E  — Staff & roles (role manage)
- [ ] Phase F  — settings sub-nav consistency
