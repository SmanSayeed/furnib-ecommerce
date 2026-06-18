## ADDED Requirements

### Requirement: Grouped key-value settings
The system SHALL store application settings as grouped key-value entries (groups such as `contact`, `home`, `footer`, `seo`, `marketing`, `storage`, `payment`, `sms`, `smtp`, `courier`) readable and writable through a settings service with typed casting.

#### Scenario: Read setting with default
- **WHEN** a setting key is requested that has not been set
- **THEN** the service returns the provided default without error

#### Scenario: Write then read round-trips with type
- **WHEN** a boolean setting is written and later read
- **THEN** the service returns it as a boolean, not a string

### Requirement: Secret settings are encrypted at rest
The system SHALL store secret-flagged settings (e.g. gateway keys, tokens) encrypted at rest and decrypt them only server-side on read. Secret values MUST NOT be returned in any client-facing settings response.

#### Scenario: Secret stored encrypted
- **WHEN** a secret setting value is saved
- **THEN** the persisted database value is ciphertext, not the plaintext

#### Scenario: Secret excluded from public settings payload
- **WHEN** settings are exposed to a non-privileged or client context
- **THEN** secret-flagged values are omitted or masked
