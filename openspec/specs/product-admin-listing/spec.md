# product-admin-listing Specification

## Purpose
TBD - created by archiving change admin-operations-console. Update Purpose after archive.
## Requirements
### Requirement: Product list sortable columns
The system SHALL expose sortable columns (title, price, stock amount, created date) in the admin product list, wiring the sort/direction parameters already supported by the product repository, gated by `catalog.view`.

#### Scenario: Sort by price descending
- **WHEN** an authorized user sorts the product list by price, descending
- **THEN** products are returned ordered by price from highest to lowest

#### Scenario: Sort by newest
- **WHEN** an authorized user sorts the product list by created date, descending
- **THEN** the most recently created products appear first

### Requirement: Product list date filtering
The system SHALL allow filtering the admin product list by a date range and named presets (today / this month / custom) on the product's created date.

#### Scenario: Filter products created this month
- **WHEN** an authorized user applies the `this_month` date preset
- **THEN** only products created in the current month (Asia/Dhaka) are listed

