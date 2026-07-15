## ADDED Requirements

### Requirement: WhatsApp inquiry produces a link preview
The WhatsApp inquiry deep link SHALL lead with the product page URL so WhatsApp renders a preview
card from the page's Open Graph tags. It SHALL NOT paste a raw image-file URL into the message.

#### Scenario: Inquiry carries the product page link
- **WHEN** a shopper taps "Inquire on WhatsApp" for a product
- **THEN** the message's first URL is the product page (`/product/{slug}`), not an image file

### Requirement: Pages expose resolvable Open Graph images
Product and category pages SHALL emit an Open Graph image with an absolute URL, a secure URL,
explicit dimensions and alt text, and the app SHALL set a `metadataBase` so relative OG/canonical
URLs resolve to the production origin.

#### Scenario: Product OG image is fully specified
- **WHEN** a crawler fetches a product page
- **THEN** it finds an og:image with an absolute HTTPS URL, width 1200, height 630 and alt text

### Requirement: Category "See more" never overlaps the caption
On a product caption the "See more" control SHALL appear on its own line below the clamped text,
not overlaid on it, so no clipped text bleeds through on any card background.

#### Scenario: See more sits below the text
- **WHEN** a caption overflows two lines on a narrow (mobile) viewport
- **THEN** "See more" renders as a separate block beneath the two clamped lines, with a gap

### Requirement: Category banner keeps a fixed aspect ratio
The category banner SHALL be an aspect-ratio box (portrait on mobile, landscape on desktop) so the
crop is identical for every visitor regardless of window height, with the aspect breakpoint aligned
to the responsive image source.

#### Scenario: Banner is not viewport-height dependent
- **WHEN** the category page is viewed at different window heights
- **THEN** the banner keeps the same aspect ratio (portrait below md, landscape at md and up) and does not re-crop
