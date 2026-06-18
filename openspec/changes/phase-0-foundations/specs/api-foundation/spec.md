## ADDED Requirements

### Requirement: Versioned API surface
The system SHALL expose storefront-facing endpoints under a `/api/v1` route group returning JSON, isolated from the Inertia admin routes.

#### Scenario: Health endpoint responds under v1
- **WHEN** a client requests `GET /api/v1/health`
- **THEN** the system responds 200 with a JSON body indicating service health

### Requirement: Sanctum token authentication
The system SHALL authenticate protected `/api/v1` endpoints using Laravel Sanctum tokens. Requests without a valid token to protected endpoints SHALL be rejected with HTTP 401.

#### Scenario: Protected endpoint rejects missing token
- **WHEN** a client calls a protected `/api/v1` endpoint with no token
- **THEN** the system responds 401 and performs no action

#### Scenario: Protected endpoint accepts valid token
- **WHEN** a client calls a protected `/api/v1` endpoint with a valid Sanctum token
- **THEN** the system authorizes the request and proceeds

### Requirement: Uniform JSON error envelope
The system SHALL return errors from `/api/v1` in a consistent JSON envelope containing a machine-readable code and a human-readable message, with the correct HTTP status (422 for validation, 401/403 for auth, 404 for missing, 500 for unexpected). Internal exception details MUST NOT leak to clients in production.

#### Scenario: Validation error envelope
- **WHEN** a request fails validation
- **THEN** the response is HTTP 422 with a JSON envelope listing field errors and a stable error code

#### Scenario: Server error hides internals
- **WHEN** an unexpected exception occurs with debug disabled
- **THEN** the response is HTTP 500 with a generic message and no stack trace or internal paths

### Requirement: Endpoint rate limiting
The system SHALL apply rate limiting to sensitive public endpoints — authentication, order placement, and OTP request/verify — and return HTTP 429 when limits are exceeded.

#### Scenario: OTP requests are throttled
- **WHEN** OTP requests from one client exceed the configured per-window limit
- **THEN** further requests in that window receive HTTP 429
