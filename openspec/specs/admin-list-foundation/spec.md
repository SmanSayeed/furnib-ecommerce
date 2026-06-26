# admin-list-foundation Specification

## Purpose
TBD - created by archiving change admin-operations-console. Update Purpose after archive.
## Requirements
### Requirement: Shared list-filter contract
The system SHALL provide a reusable mechanism (`ListQuery` + `AppliesListFilters`) that applies, to any admin Eloquent listing: keyword search across a per-resource whitelist of columns/relations, equality filters (e.g. status, payment_status, category_id), an inclusive date range, a whitelisted sort (column + `asc|desc`), and pagination — preserving all active parameters in the response so they survive paging and refresh.

#### Scenario: Whitelisted sort with safe fallback
- **WHEN** a list request supplies a `sort` column that is not in the resource whitelist
- **THEN** the result falls back to the resource's default sort and no error or raw column injection occurs

#### Scenario: Filters persist across pagination
- **WHEN** an authorized user applies a status filter and moves to page 2
- **THEN** the status filter remains applied and the page-2 results respect it

### Requirement: Timezone-aware date presets
The system SHALL resolve named date presets (`today`, `yesterday`, `last_7`, `this_month`, `last_month`, `custom`) and explicit `from`/`to` into an inclusive day-bounded range computed in the application timezone (Asia/Dhaka), and filter `created_at` by that range.

#### Scenario: Today preset respects timezone boundary
- **WHEN** an authorized user selects the `today` preset
- **THEN** only rows created between 00:00 and 23:59:59 of the current day in Asia/Dhaka are returned

#### Scenario: This-month preset
- **WHEN** an authorized user selects the `this_month` preset
- **THEN** only rows created within the current calendar month (Asia/Dhaka) are returned

### Requirement: Reusable sortable table and date-range UI
The system SHALL provide reusable admin UI — a data table with sortable column headers (toggling `asc`/`desc`) and a date-range control (preset dropdown + custom from/to) — that drives the list via the URL query string so state is shareable and refresh-safe.

#### Scenario: Clicking a sortable header
- **WHEN** an admin clicks a sortable column header
- **THEN** the list reloads sorted by that column and the sort direction toggles on repeated clicks

#### Scenario: Selecting a date preset
- **WHEN** an admin selects a date preset in the date-range control
- **THEN** the list reloads filtered to that range and the chosen preset is reflected in the URL

