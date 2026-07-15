## ADDED Requirements

### Requirement: The product feed is not public
The product feed SHALL be served only from an unguessable, credential-protected URL. Access
requires the feed to be enabled, the path segment to match the stored slug, and HTTP Basic
credentials to match. The feed SHALL be rate-limited.

#### Scenario: An authenticated request returns the feed
- **WHEN** the feed is enabled and a request to `/feed/{slug}/products.csv` carries the correct Basic credentials
- **THEN** the CSV catalogue is returned

#### Scenario: A disabled feed is a 404
- **WHEN** the feed is switched off
- **THEN** a request to its URL returns 404

#### Scenario: A wrong slug is a 404
- **WHEN** a request uses a path segment that is not the stored slug
- **THEN** it returns 404 (indistinguishable from disabled)

#### Scenario: Missing credentials are challenged
- **WHEN** an enabled feed URL is requested without Basic credentials
- **THEN** it returns 401 with a `WWW-Authenticate: Basic` challenge

#### Scenario: Wrong credentials are rejected
- **WHEN** an enabled feed URL is requested with an incorrect password
- **THEN** it returns 401

### Requirement: The feed carries the extended Meta fields
The feed SHALL emit, per published product, the category breadcrumb as `product_type`, an
`item_group_id`, and `quantity_to_sell_on_facebook` reflecting real stock, in addition to the
existing id/title/availability/price/sale_price/link/image fields. `sale_price` SHALL be emitted
only when strictly below `price`.

#### Scenario: Extended fields are present
- **WHEN** a published product in the "Living Room" category with stock 7 is emitted
- **THEN** its row has product_type "Living Room", item_group_id equal to its id, and quantity_to_sell_on_facebook 7

### Requirement: Admin manages the feed and exports the catalogue
An authorized admin (`marketing.manage`) SHALL be able to enable the feed (minting an unguessable
slug + Basic-auth password shown once), view and copy the secured URL, regenerate the slug +
password, and download the catalogue CSV filtered to selected categories. The feed password SHALL
be stored encrypted.

#### Scenario: Enabling mints credentials shown once
- **WHEN** an admin enables the feed for the first time
- **THEN** a slug, username and password are created, the feed URL becomes available, and the plaintext password is shown once

#### Scenario: A second save does not rotate the password
- **WHEN** an admin saves again while the feed is already enabled
- **THEN** the existing password is kept and no new plaintext is shown

#### Scenario: Regenerate rotates the URL and password
- **WHEN** an admin regenerates
- **THEN** the slug and password both change (invalidating the old URL) and the new password is shown once

#### Scenario: The password is stored encrypted
- **WHEN** the feed password is persisted
- **THEN** the stored value is ciphertext, not the plaintext password

#### Scenario: Category-filtered export
- **WHEN** an admin downloads the CSV with selected categories
- **THEN** only products in those categories are included

#### Scenario: Only authorized staff can manage it
- **WHEN** a user without `marketing.manage` opens or posts to the Facebook Commerce page
- **THEN** the request is forbidden
