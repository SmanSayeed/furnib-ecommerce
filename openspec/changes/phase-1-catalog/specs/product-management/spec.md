## ADDED Requirements

### Requirement: Product core fields and pricing
The system SHALL store products with: title, unique slug, unique SKU, details, optional YouTube video URL, price and optional discount price (integer minor units), advance-payment configuration, featured/new flags, position order, product status (draft/published/disabled), SEO fields, and a single category assignment. Money SHALL be stored as integer minor units.

#### Scenario: Create a published product with a discount
- **WHEN** an authorized user creates a product with price 5000.00 and discount 4200.50
- **THEN** the product is stored with price 500000 and discount 420050 minor units and a unique slug + SKU

#### Scenario: Duplicate SKU is rejected
- **WHEN** a product is created with an existing SKU
- **THEN** the system rejects it with a validation error

### Requirement: Stock availability logic
The system SHALL treat a product as in stock only when its stock status is enabled AND stock amount is greater than zero. When stock status is disabled the product SHALL be shown as out of stock even if the amount is positive.

#### Scenario: Stock disabled overrides positive amount
- **WHEN** a product has stock amount 10 but stock status disabled
- **THEN** the product reports out of stock

#### Scenario: In stock requires positive amount and enabled status
- **WHEN** a product has stock amount 3 and stock status enabled
- **THEN** the product reports in stock

### Requirement: Product images (one main + up to six)
The system SHALL allow one main image and up to six gallery images per product. Attempting to add a seventh gallery image SHALL be rejected. The social thumbnail SHALL fall back to the main image when not set.

#### Scenario: Seventh gallery image rejected
- **WHEN** a product already has six gallery images and another is added
- **THEN** the system rejects the addition

#### Scenario: Social thumbnail falls back to main image
- **WHEN** a product has no social thumbnail set
- **THEN** the resolved social thumbnail is the main image
