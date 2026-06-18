## ADDED Requirements

### Requirement: Seeded roles and permissions
The system SHALL provide six roles — `owner`, `admin`, `manager`, `sub-admin`, `marketer`, `editor` — each seeded with a fixed permission set per the matrix in `docs/feature-plan/MASTER-PLAN.md` §6. Authorization SHALL be enforced through permissions (not hardcoded role-name checks) so the matrix can change without code changes.

#### Scenario: Roles are seeded idempotently
- **WHEN** the RBAC seeder runs once or multiple times
- **THEN** exactly the six roles exist, each with its mapped permissions, and re-running creates no duplicates

#### Scenario: Permission gate denies unauthorized action
- **WHEN** a user holding the `editor` role attempts an action requiring the `orders.manage` permission
- **THEN** the system denies it with HTTP 403 and records no state change

#### Scenario: Owner has every permission
- **WHEN** the `owner` role is evaluated against any defined permission
- **THEN** authorization passes for all of them

### Requirement: Secure owner bootstrap
The system SHALL create the single `owner` user from environment values `OWNER_EMAIL` and one-time `OWNER_BOOTSTRAP_PASSWORD`, hashing the password with argon2id. Credentials MUST NOT appear anywhere in source or committed config. If `OWNER_EMAIL` is unset, the seeder SHALL abort with a clear error and create no owner.

#### Scenario: Owner seeded from environment
- **WHEN** `OWNER_EMAIL` and `OWNER_BOOTSTRAP_PASSWORD` are set and the seeder runs
- **THEN** one owner user exists with the given email, an argon2id password hash, and the `owner` role

#### Scenario: Missing owner email aborts seeding
- **WHEN** the seeder runs with `OWNER_EMAIL` unset
- **THEN** seeding aborts with an explanatory error and no owner user is created

### Requirement: Forced password change and mandatory 2FA on first login
The system SHALL require the owner (and any env-bootstrapped staff) to change the bootstrap password and enable two-factor authentication before accessing any protected area, on first authenticated session.

#### Scenario: First login redirects to password change
- **WHEN** the owner authenticates for the first time with the bootstrap password
- **THEN** the system forces a password change before granting access to any admin route

#### Scenario: 2FA enrollment required before admin access
- **WHEN** the owner has changed the password but not yet enabled 2FA
- **THEN** the system requires 2FA enrollment before any protected admin route is reachable

### Requirement: No destructive backdoor
The codebase SHALL contain no mechanism that deletes or locks server folders or files, and no hidden/undisclosed privileged account. The highest access is the auditable `owner` role only.

#### Scenario: No filesystem-destruction capability exists
- **WHEN** the codebase is searched for folder/file deletion triggered by an account or endpoint
- **THEN** no such capability exists; emergency control is limited to reversible, audited maintenance actions
