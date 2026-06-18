## ADDED Requirements

### Requirement: Uploaded images optimized to WebP
The system SHALL convert uploaded catalog images to optimized WebP and persist them through the storage abstraction, returning the stored path. Oversized images SHALL be downscaled to a sensible maximum width while preserving aspect ratio and high quality.

#### Scenario: Upload is converted and stored as WebP
- **WHEN** an authorized user uploads a JPEG/PNG product image
- **THEN** an optimized `.webp` derivative is stored via the storage repository and its path returned

#### Scenario: Large image is downscaled
- **WHEN** an uploaded image exceeds the maximum width
- **THEN** the stored WebP is downscaled to the maximum width, keeping aspect ratio
