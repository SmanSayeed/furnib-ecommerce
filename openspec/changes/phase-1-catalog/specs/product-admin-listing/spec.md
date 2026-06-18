## ADDED Requirements

### Requirement: Server-side product listing
The system SHALL provide an admin product listing (requires `catalog.view`) with keyword search (title/SKU/slug), filters (status, category, stock status, date range), sortable columns, and pagination.

#### Scenario: Search by SKU
- **WHEN** an authorized user searches the product list by an exact SKU
- **THEN** only matching products are returned

#### Scenario: Filter by status
- **WHEN** an authorized user filters by status = published
- **THEN** only published products are returned

### Requirement: Soft delete and recycle bin
The system SHALL soft-delete products, list trashed products separately, restore them, and permanently delete (hard delete) on explicit action. Hard delete SHALL require `catalog.manage`.

#### Scenario: Soft delete then restore
- **WHEN** an authorized user deletes a product and then restores it
- **THEN** the product is excluded from the default listing while trashed and returns after restore

#### Scenario: Hard delete removes permanently
- **WHEN** an authorized user hard-deletes a trashed product
- **THEN** the product no longer exists in any listing

### Requirement: CSV export
The system SHALL export the current (filtered) product set as a CSV file.

#### Scenario: Export returns CSV
- **WHEN** an authorized user exports the product list
- **THEN** the system returns a CSV containing the filtered products
