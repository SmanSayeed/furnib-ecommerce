# Furnib.com — Build Log

> Running log of autonomous build progress. Newest at top. See `ROADMAP.md` / OpenSpec `tasks.md` for the task list.

## Conventions in effect
- Branch: `feat/phase-0-foundations` (Phase 0). Conventional commits. Pushed to GitHub.
- Every module: OpenSpec spec → Pest TDD (RED→GREEN) → Pint/Larastan → commit.
- PHP 8.3.16 invoked explicitly (system default is 8.1). DB: MySQL `furnib-ecommerce`.
- Integrations needing live keys (R2, SMS, SteadFast, SSLCommerz, SMTP) are built as **interfaces + services + fakes + tests**; owner plugs credentials into encrypted settings later.

## Progress

### Phase 0 — Foundations  (in progress)
- ✅ 1.1 Deps installed + pinned (spatie permission/data/activitylog, sanctum, intervention/image, dompdf, league/csv).
- ✅ 1.2 Vendor configs/migrations published + migrated to `furnib-ecommerce`.
- ✅ 1.3 Baseline green — enabled RefreshDatabase in Pest.php; 39 starter tests pass.
- ✅ 2. Architecture base — RepositoryInterface + BaseRepository + UserRepository, RepositoryServiceProvider.
- ✅ 3. Money value object + MoneyCast (integer paisa).
- ✅ 4. RBAC + bootstrap — config/rbac.php matrix, PermissionRoleSeeder, OwnerSeeder (a@a.com) + AdminSeeder (admin@gmail.com), `must_change_password`/`two_factor_required` + `EnsureAccountSecured` middleware, no-backdoor guard test. **62 tests pass.**
  - Note: argon2id needs Linux/libsodium; on Windows local we hash with bcrypt. Set `HASH_DRIVER=argon2id` in prod.
  - Note: bootstrap creds live in `.env` only (a@a.com / admin@gmail.com), forced change + (owner) 2FA on first login.
- ⏳ 5. Audit logging.
- ⏳ 6. Encrypted settings.
- ⏳ 7. Storage abstraction (server/R2).
- ⏳ 8. API foundation (Sanctum, error envelope, rate limits).
