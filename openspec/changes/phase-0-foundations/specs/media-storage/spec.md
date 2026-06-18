## ADDED Requirements

### Requirement: Pluggable storage abstraction
The system SHALL expose a `StorageRepository` interface for storing, retrieving URLs for, and deleting files, with interchangeable drivers. The active driver SHALL be selectable via application settings, defaulting to the local server disk. Calling code MUST depend only on the interface, never on a concrete driver.

#### Scenario: Default driver is server disk
- **WHEN** no storage driver is configured in settings
- **THEN** the resolved `StorageRepository` is the server-disk driver

#### Scenario: Driver switches via settings
- **WHEN** the storage driver setting is changed to `r2`
- **THEN** the resolved `StorageRepository` is the Cloudflare R2 driver, with no change to calling code

#### Scenario: Store then resolve public URL
- **WHEN** a file is stored through the repository
- **THEN** the repository returns a stable public URL that resolves to the stored file, and the file can be deleted through the same interface

### Requirement: R2 credentials are never client-exposed
The system SHALL read Cloudflare R2 credentials only from server-side encrypted settings or env, and SHALL NOT emit them to any client response or frontend bundle.

#### Scenario: Credentials absent from responses
- **WHEN** any API or page response is produced
- **THEN** no R2 access key or secret appears in the payload
