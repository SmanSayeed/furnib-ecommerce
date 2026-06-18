## ADDED Requirements

### Requirement: Sensitive writes are audit-logged
The system SHALL record an audit entry for every sensitive write action (create/update/delete of catalog, orders, settings, roles, and owner actions), capturing the actor identity, the affected subject, the action, and a timestamp.

#### Scenario: Update records an audit entry
- **WHEN** an authenticated staff user updates an auditable model
- **THEN** an audit entry is stored linking that actor, the subject model, and the change

#### Scenario: Unauthenticated/system action is attributable
- **WHEN** an auditable write occurs without an authenticated user (e.g. a queued job)
- **THEN** the audit entry is still recorded and marked as system-originated, not silently dropped

### Requirement: Audit entries capture request IP
The system SHALL store the originating request IP address on each audit entry created during an HTTP request.

#### Scenario: IP captured on web-originated change
- **WHEN** a sensitive write happens during an HTTP request from IP `203.0.113.10`
- **THEN** the audit entry stores `203.0.113.10` as the request IP

### Requirement: Audit log is read-only to non-owners
The system SHALL expose audit entries only to roles with the `audit.view` permission and SHALL NOT allow editing or deleting audit entries through the application.

#### Scenario: Unauthorized role cannot read audit log
- **WHEN** a user without `audit.view` requests the audit log
- **THEN** the system responds 403 and returns no entries
