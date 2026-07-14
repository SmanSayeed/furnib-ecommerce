## ADDED Requirements

### Requirement: One resolved credential source per courier
The system SHALL resolve a courier credential from its own encrypted `config` first, and — for the Steadfast driver only — fall back to the legacy `steadfast` settings group. Every consumer (the "configured" gate, the driver factory, the admin form's "is set" flags) SHALL use this one resolved value, so no two surfaces can disagree about whether a courier is configured.

#### Scenario: Keys held only in the legacy settings group
- **WHEN** a Steadfast courier has an empty `config` but the `steadfast` settings group holds an api_key and a secret_key
- **THEN** `Courier::isConfigured()` returns true, the order page enables Book, and booking uses those keys

#### Scenario: Keys held in the courier config
- **WHEN** a Steadfast courier has api_key and secret_key in its `config`
- **THEN** `Courier::isConfigured()` returns true and those values are used

#### Scenario: Keys held in both places
- **WHEN** a Steadfast courier has an api_key in its `config` and a different one in the legacy settings group
- **THEN** the `config` value wins

#### Scenario: Keys held nowhere
- **WHEN** a Steadfast courier has neither
- **THEN** `Courier::isConfigured()` returns false and booking is refused with a clear message

#### Scenario: Non-Steadfast driver has no legacy fallback
- **WHEN** a RedX courier has an empty `config`
- **THEN** `Courier::isConfigured()` returns false — the legacy settings group is Steadfast-only

### Requirement: An undecryptable config degrades, never 500s
The system SHALL treat a courier config that cannot be decrypted (an `APP_KEY` mismatch) as an empty config, report the exception, and continue — rather than throwing an unhandled error that breaks the admin page.

#### Scenario: Config encrypted with a different APP_KEY
- **WHEN** the couriers list is opened and a courier's `config` cannot be decrypted
- **THEN** the page renders, the courier shows as not configured, and the exception is reported to the error log

### Requirement: Provider failures reach the admin
The system SHALL check the HTTP status of every courier API call, apply a request timeout, and raise a `CourierException` carrying the provider's status code and a truncated response body. The admin SHALL see that message as an error toast instead of a 500 page.

#### Scenario: Provider rejects the credentials
- **WHEN** Steadfast responds 401 to a booking request
- **THEN** the admin sees an error toast naming the HTTP status, the exception is reported, and no 500 page is shown

#### Scenario: Provider rejects the request body
- **WHEN** Steadfast responds 422 (for example a duplicate invoice on a re-book)
- **THEN** the provider's own message reaches the admin

#### Scenario: Provider is unreachable
- **WHEN** the courier host cannot be reached (DNS, TLS or a blocked egress)
- **THEN** the call fails within the timeout with a "could not reach" message rather than hanging

#### Scenario: Location lookup failure is reported
- **WHEN** a RedX or Pathao location lookup throws
- **THEN** the order page still renders with an empty option list AND the exception is reported to the error log

### Requirement: Courier connection test
The system SHALL let an authorized admin (`couriers.manage`) test an API courier's credentials with a read-only call to the provider, and SHALL show the real outcome — a success line including any provider detail, or the provider's failure reason.

#### Scenario: Credentials are valid
- **WHEN** an admin tests a correctly configured Steadfast courier
- **THEN** a success message is shown containing the provider's reported balance

#### Scenario: Credentials are rejected
- **WHEN** the provider responds 401 to the test
- **THEN** an error message is shown naming the HTTP status

#### Scenario: Manual courier
- **WHEN** an admin tests a manual (non-API) courier
- **THEN** a message explains that this courier has no API to test

#### Scenario: Unauthorized staff
- **WHEN** a user without `couriers.manage` posts to the test endpoint
- **THEN** the request is forbidden

### Requirement: Changing Pathao credentials invalidates the cached token
The system SHALL forget a Pathao courier's cached OAuth token when its credentials are updated, so a corrected credential takes effect on the next call rather than after the cache expires.

#### Scenario: Password corrected
- **WHEN** an admin updates a Pathao courier's password
- **THEN** the cached access token for that courier is forgotten
