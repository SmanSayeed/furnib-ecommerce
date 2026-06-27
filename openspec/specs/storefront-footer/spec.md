# storefront-footer Specification

## Purpose
TBD - created by archiving change product-shipping-charges. Update Purpose after archive.
## Requirements
### Requirement: Admin-managed footer content
The system SHALL store footer content (social links, about/quick links, contact block) as site settings editable by a `settings.manage` admin and expose it to the storefront via the public settings endpoint.

#### Scenario: Admin saves footer settings
- **WHEN** an admin saves footer social links and contact details
- **THEN** the values persist and are returned by `GET /api/v1/settings`

### Requirement: Four-column footer layout
The storefront footer SHALL render its content as four columns on desktop and stacked (single column) on mobile, above the SSLCommerz payment badge.

#### Scenario: Responsive layout
- **WHEN** the footer renders on a desktop viewport
- **THEN** its content blocks appear in a four-column row, collapsing to stacked columns on mobile

### Requirement: Newsletter subscription capture
The system SHALL accept newsletter subscriptions via `POST /api/v1/newsletter` with a validated, unique email, persisted in `newsletter_subscribers`, and rate-limited.

#### Scenario: New subscriber
- **WHEN** a visitor submits a valid, not-yet-subscribed email
- **THEN** a `newsletter_subscribers` row is created and a success response returned

#### Scenario: Duplicate subscriber
- **WHEN** a visitor submits an email already subscribed
- **THEN** the request is rejected (or treated idempotently) without creating a duplicate row

#### Scenario: Invalid email
- **WHEN** a visitor submits a malformed email
- **THEN** the request fails validation with 422

