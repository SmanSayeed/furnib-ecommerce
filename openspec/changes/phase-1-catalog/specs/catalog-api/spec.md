## ADDED Requirements

### Requirement: Storefront category endpoints
The system SHALL expose read-only storefront endpoints: list active categories, and fetch a single active category by slug with its published, in-stock-aware products paginated.

#### Scenario: List active categories
- **WHEN** a client requests `GET /api/v1/categories`
- **THEN** the response contains only active categories ordered by position then title

#### Scenario: Fetch category with paginated products
- **WHEN** a client requests `GET /api/v1/categories/{slug}` for an active category
- **THEN** the response includes the category and a paginated list of its published products

#### Scenario: Inactive category returns 404
- **WHEN** a client requests an inactive or missing category slug
- **THEN** the system responds 404 with the uniform error envelope

### Requirement: Storefront product endpoint
The system SHALL expose a read-only endpoint to fetch a single published product by slug, including images, pricing (with discount), and resolved availability.

#### Scenario: Fetch a published product
- **WHEN** a client requests `GET /api/v1/products/{slug}` for a published product
- **THEN** the response includes the product fields, images, price/discount, and availability

#### Scenario: Draft product is not publicly fetchable
- **WHEN** a client requests a draft or disabled product slug
- **THEN** the system responds 404
