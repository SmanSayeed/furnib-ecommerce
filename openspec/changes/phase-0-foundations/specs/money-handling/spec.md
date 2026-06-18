## ADDED Requirements

### Requirement: Money stored as integer minor units
The system SHALL represent and persist all monetary amounts as integer minor units (paisa) and SHALL NOT use floating-point types for money. A `Money` value object and an Eloquent cast SHALL provide conversion between minor units and display amounts.

#### Scenario: Cast round-trips without precision loss
- **WHEN** a money attribute is set to a display amount of `1234.56` and reloaded from the database
- **THEN** it is stored as `123456` minor units and returns exactly `1234.56`, with no floating-point drift

#### Scenario: Arithmetic stays in minor units
- **WHEN** two `Money` values of `100` and `250` minor units are added
- **THEN** the result is `350` minor units

#### Scenario: Negative or non-integer minor units are rejected
- **WHEN** a `Money` value is constructed from a non-integer or negative minor-unit input where not allowed
- **THEN** the system raises a validation error rather than coercing silently
