## 1. Dependencies & tooling

- [x] 1.1 Add Composer deps: spatie/laravel-permission, spatie/laravel-data, spatie/laravel-activitylog, laravel/sanctum, intervention/image, barryvdh/laravel-dompdf, league/csv (pinned ^versions)
- [x] 1.2 Publish + configure: Sanctum config/migrations, spatie-permission config/migrations, activitylog config/migrations (migrated to furnib-ecommerce)
- [x] 1.3 Confirm baseline green — enabled RefreshDatabase in Pest.php; 39 tests pass

## 2. Architecture base (SOLID)

- [x] 2.1 Create `app/Repositories` (RepositoryInterface + BaseRepository), `app/Support`; Services/DTOs/Actions created as needed per module
- [x] 2.2 Add a `RepositoryServiceProvider` binding interfaces → implementations (UserRepository); registered in bootstrap/providers.php
- [x] 2.3 Architecture smoke test: binding resolves + findByEmail works

## 3. Money handling (money-handling)

- [x] 3.1 RED: unit tests for `Money` (round-trip, add/subtract, negative+non-numeric rejected, format) and `MoneyCast` (set/get/null)
- [x] 3.2 GREEN: implemented `Support/Money` value object + `Casts/MoneyCast`
- [x] 3.3 REFACTOR: Pint/Larastan pass (run in batch); integer-paisa convention documented in MODULES/MASTER-PLAN

## 4. RBAC + owner security (access-control)

- [ ] 4.1 RED: feature tests — roles seeded idempotently, permission gate denies `editor` on `orders.manage`, owner has all permissions
- [ ] 4.2 GREEN: permissions catalog + `RoleSeeder` from the MASTER-PLAN §6 matrix; clear permission cache in seeder
- [ ] 4.3 RED: tests — owner seeded from env, missing `OWNER_EMAIL` aborts, no hardcoded creds
- [ ] 4.4 GREEN: `OwnerSeeder` reading `OWNER_EMAIL`/`OWNER_BOOTSTRAP_PASSWORD` (argon2id), assign owner role
- [ ] 4.5 RED: tests — first login forces password change, then forces 2FA enrollment before protected routes
- [ ] 4.6 GREEN: `must_change_password` + `two_factor_required` flags, middleware enforcing both via Fortify
- [ ] 4.7 Test: codebase contains no filesystem-destruction capability (guard test)

## 5. Audit logging (audit-logging)

- [ ] 5.1 RED: tests — sensitive update logs actor+subject, system action attributed, IP captured, non-`audit.view` gets 403
- [ ] 5.2 GREEN: activitylog setup, `LogsActivity` trait usage, middleware/observer attaching request IP + system-actor fallback
- [ ] 5.3 GREEN: audit-log read endpoint/policy gated by `audit.view`, no edit/delete path

## 6. Encrypted settings (app-settings)

- [ ] 6.1 RED: tests — read-with-default, typed round-trip, secret stored as ciphertext, secret masked in client payload
- [ ] 6.2 GREEN: `settings` table (group, key, value, is_secret) + `SettingsService` with typed casts + encrypted cast for secrets
- [ ] 6.3 GREEN: client-facing masker dropping/masking secret-flagged values

## 7. Storage abstraction (media-storage)

- [ ] 7.1 RED: tests — default resolves server disk, switches to R2 via setting, store→URL→delete round-trip, R2 creds never in responses, missing R2 creds throws
- [ ] 7.2 GREEN: `StorageRepository` interface + `ServerDiskStorage` + `CloudflareR2Storage` (S3 driver) + settings-driven resolver + test fake

## 8. API foundation (api-foundation)

- [ ] 8.1 RED: tests — `GET /api/v1/health` 200, protected endpoint 401 without token / authorized with Sanctum token, validation→422 envelope, 500 hides internals, OTP route throttled→429
- [ ] 8.2 GREEN: `routes/api.php` `/api/v1` group, Sanctum guard, base JSON Resource, exception handler renderer for the uniform envelope
- [ ] 8.3 GREEN: rate limiters (auth/order/otp) registered and applied

## 9. Verify & ship

- [ ] 9.1 Run full suite: Pest green, Larastan max clean, Pint clean
- [ ] 9.2 Seed against `furnib-ecommerce` MySQL DB; manually verify owner first-login flow
- [ ] 9.3 `/opsx:archive` the change; commit on `feat/phase-0-foundations`, merge `master`, push; tag not yet (pre-Phase-1)
