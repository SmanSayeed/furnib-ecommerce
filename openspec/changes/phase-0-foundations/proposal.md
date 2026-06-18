## Why

Every later module (catalog, orders, payments, marketing) depends on a shared architecture base and security spine. Building these foundations once — clean layering, role-based access, audit trail, pluggable storage, encrypted settings, and a versioned API — prevents rework and bakes in security from the first commit. This is the groundwork phase; no customer-facing feature ships yet.

## What Changes

- Add Composer dependencies: `spatie/laravel-permission`, `spatie/laravel-data`, `spatie/laravel-activitylog`, `laravel/sanctum`, `intervention/image`, `barryvdh/laravel-dompdf`, `league/csv`.
- Introduce a SOLID layering convention: `Repositories/` (interface + Eloquent impl + binding provider), `Services/`, `DTOs/`, `Actions/`, `Support/` integration interfaces.
- Add a `Money` value object + Eloquent cast storing all money as integer minor units (paisa).
- Add RBAC: roles `owner, admin, manager, sub-admin, marketer, editor` with a seeded permission matrix.
- Secure **owner** bootstrap from env (`OWNER_EMAIL` + one-time `OWNER_BOOTSTRAP_PASSWORD`, argon2id), with **forced password change + mandatory 2FA on first login** via Fortify. No hardcoded credentials. **BREAKING** with the originally-requested destructive backdoor idea — it is explicitly rejected; nothing in the codebase deletes or locks server folders.
- Add audit logging on sensitive writes, capturing actor + IP.
- Add a `StorageRepository` abstraction with `ServerDisk` (default) and `CloudflareR2` drivers, switchable via settings.
- Add an encrypted grouped settings service (secrets stored via encrypted casts).
- Add an API skeleton: `/api/v1` route group, Sanctum auth, JSON resource layer, standard JSON error handler, and rate limiters for auth/order/otp endpoints.

## Capabilities

### New Capabilities
- `access-control`: roles & permissions (RBAC), the seeded role matrix, secure owner bootstrap, and forced password-change + mandatory 2FA on first login.
- `audit-logging`: recording sensitive write actions with actor identity and request IP.
- `media-storage`: a swappable storage abstraction (server disk default, Cloudflare R2 optional) used by all later upload features.
- `app-settings`: grouped key-value application settings with encryption-at-rest for secret values.
- `api-foundation`: the versioned `/api/v1` surface — Sanctum token auth, JSON resources, a uniform error envelope, and endpoint rate limiting.
- `money-handling`: representing and casting monetary amounts as integer minor units to eliminate floating-point errors.

### Modified Capabilities
<!-- none — this is the first change; no existing specs to modify -->

## Impact

- **Code**: `laravel-backend/app/{Repositories,Services,DTOs,Actions,Support,Models,Providers}`, `config/`, `database/{migrations,seeders}`, `routes/api.php`, `tests/`.
- **Dependencies**: 7 new Composer packages (above).
- **Config/env**: new `OWNER_EMAIL`, `OWNER_BOOTSTRAP_PASSWORD`, storage + Sanctum config keys. `.env` stays git-ignored.
- **Security**: introduces the authn/authz, audit, and secret-handling baseline the whole app relies on.
- **No frontend impact** in this phase (storefront consumes `api-foundation` from Phase 1 onward).
