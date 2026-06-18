## Context

Fresh Laravel 13 / PHP 8.3 backend (`laravel-backend`) scaffolded with the Fortify + Inertia + React starter. This change lays the architecture and security base for all later modules. It is cross-cutting (auth, audit, storage, settings, API, money), adds external dependencies, and carries security weight — so a design doc is warranted. Decisions here are constrained by the global security rules in repo `CLAUDE.md` and the rejected-backdoor decision in `MASTER-PLAN.md`.

## Goals / Non-Goals

**Goals:**
- A SOLID layering convention (Controller → Service → Repository → DTO/Action) that every module reuses.
- RBAC with a seeded matrix and a secure, env-bootstrapped owner (forced rotation + 2FA).
- Audit trail with actor + IP on sensitive writes.
- Storage behind one interface (server disk default, R2 switchable).
- Encrypted grouped settings.
- Versioned Sanctum-authenticated `/api/v1` with a uniform error envelope and rate limits.
- Money as integer minor units everywhere.

**Non-Goals:**
- No customer-facing features (catalog, orders, payments) — those are later phases.
- No license/remote-suspend module (deferred to Phase 7).
- No storefront UI work.

## Decisions

- **spatie/laravel-permission for RBAC** over hand-rolled tables: battle-tested, cache-backed, permission-first gating. We gate on *permissions*, never role names, so the matrix is data-driven.
- **Owner via env-seeded user + Fortify 2FA** over a special "super" code path: keeps the highest access as a normal, auditable account. `OWNER_BOOTSTRAP_PASSWORD` is one-time; a `must_change_password` + `two_factor_required` flag forces enrollment via middleware. Rejected: any hidden account or destructive switch.
- **spatie/laravel-activitylog** for audit over manual logging: declarative `LogsActivity` on models, with a small middleware/observer to attach request IP and a system-actor fallback for jobs.
- **StorageRepository interface + driver classes** over calling `Storage::disk()` directly: lets settings flip server↔R2 and keeps tests driver-agnostic via a fake. R2 uses the S3-compatible driver under the hood.
- **Encrypted settings via spatie/laravel-settings or a `settings` table with encrypted casts**: secret-flagged keys use Laravel's `encrypted` cast; client-facing reads pass through a masker that drops secrets.
- **Sanctum (token) for `/api/v1`** while admin stays on Fortify sessions: storefront is a separate Next app, so stateless tokens fit; sessions stay for Inertia admin.
- **Uniform error envelope** via a custom exception handler renderer for `api/*` requests: `{ "error": { "code", "message", "details?" } }`; debug details suppressed when `APP_DEBUG=false`.
- **Money value object + cast** over a money package initially: minimal, dependency-light, integer-only; can adopt `moneyphp/money` later if multi-currency is needed.

## Risks / Trade-offs

- [Permission cache staleness after seeding] → clear spatie permission cache in the seeder and after matrix changes.
- [Owner lockout if 2FA device lost] → owner can re-bootstrap via env + artisan recovery command (audited), no hidden bypass.
- [R2 misconfiguration silently falling back to local] → driver resolution fails loudly (throws) if `r2` is selected but credentials are missing, rather than silently using local.
- [Encrypted settings break if `APP_KEY` rotates] → document APP_KEY handling; provide a re-encrypt artisan command.
- [Money cast misuse with floats] → Larastan rule + tests assert integer storage; no float columns for money in any migration.

## Migration Plan

1. Install deps, publish required configs/migrations.
2. Run migrations + RBAC seeder (idempotent) in dev against the `furnib-ecommerce` MySQL DB.
3. Set `OWNER_EMAIL` / `OWNER_BOOTSTRAP_PASSWORD` in `.env` (not committed); seed owner.
4. Rollback: migrations are reversible; no destructive data ops. Re-seeding is idempotent.

## Open Questions

- Final secret-settings storage: `spatie/laravel-settings` vs a custom `settings` table — decide during tasks (lean custom table for encrypted-cast simplicity).
- Whether `manager` gets `payments.view` (currently no per matrix) — confirm with owner before Phase 3.
