## ADDED Requirements

### Requirement: Category CRUD
The system SHALL let authorized staff (`catalog.manage`) create, update, and delete categories with: title, unique slug, details, header image, thumbnail image, status (active/inactive), position order, and SEO fields (meta title, meta description, OG image). Slugs SHALL be unique and auto-derived from the title when not supplied.

#### Scenario: Create a category with an auto slug
- **WHEN** an authorized user creates a category titled "Lovinna Chair" without a slug
- **THEN** the category is stored with a unique slug `lovinna-chair` and the given fields

#### Scenario: Duplicate slug is rejected
- **WHEN** a category is created with a slug that already exists
- **THEN** the system rejects it with a validation error and stores nothing

#### Scenario: Unauthorized user cannot manage categories
- **WHEN** a user without `catalog.manage` attempts to create a category
- **THEN** the system responds 403 and stores nothing

### Requirement: Category status and ordering
The system SHALL expose only active categories to the storefront and SHALL order categories by their position order then title.

#### Scenario: Inactive category hidden from storefront listing
- **WHEN** the storefront requests the category list and a category is inactive
- **THEN** that category is not included in the response

### Requirement: Category changes are audited
The system SHALL record an audit entry for category create/update/delete.

#### Scenario: Update logs an audit entry
- **WHEN** an authorized user updates a category
- **THEN** an audit entry is recorded for that change
