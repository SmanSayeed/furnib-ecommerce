# furnib-ecommerce — Project Rules & Skills

Furniture e-commerce (Lovinna-inspired) **owned by Saadman Sayeed** (not a client project; client gets a free trial). Monorepo:
- `laravel-backend/` — Admin panel (Laravel 13 / PHP 8.3 / Inertia + React 19 + shadcn / Fortify / Pest 4) **+ JSON API** for the storefront.
- `ecommerce-next-frontend/` — Public storefront (Next.js 16 / React 19 / Tailwind 4 / shadcn).
- `docs/feature-plan/MASTER-PLAN.md` — the authoritative plan. Read it before building.

DB: **MySQL**. Money stored as **integer minor units (paisa)** — never floats.

---

## GIT — standing permission (this project only)
The owner has granted **standing permission to run git commands for THIS project** (recorded 2026-06-18). This **overrides** the global "re-ask every time" rule, **for furnib-ecommerce only** — other projects still follow the global re-ask rule.

**Still confirm before running** (hard to reverse / outward-facing):
`git push --force` / `--force-with-lease`, `git reset --hard`, `git clean -fd`, `git branch -D`, `git tag -d`, history rewrites (`rebase -i`, `filter-branch`, `filter-repo`), and pushing to a **new** remote/branch the owner hasn't named.

Routine git (status, add, commit, push to existing upstream, pull, fetch, log, diff, branch, checkout, switch, merge, remote, stash, restore) — just do it.

Commit message footer: `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`

---

## SECURITY — strict (inherits global rules)
- **No destructive backdoor, ever.** The "delete/lock server folders" idea is permanently rejected. Top access = a secure `owner` role (env-seeded, forced password change + mandatory 2FA, audit-logged) with a reversible **Maintenance Lock** only.
- **No secrets in the repo or the Next bundle.** Gateway keys (SSLCommerz, SteadFast, SMS, R2, SMTP, Meta CAPI token) live encrypted in DB or env. Only public IDs (GTM, GA4, Pixel) may reach the client via `NEXT_PUBLIC_*`.
- `.env` files must never be committed (already gitignored — verify on each push). Repo must stay **Private**.
- Every `:id` route: ownership/authorization check (no IDOR). Validate all input (FormRequest / Zod). Rate-limit auth, order, and OTP endpoints. SSLCommerz return verified **server-side** via `val_id` — never trust the redirect. OTP hashed + rate-limited.
- Flag every insecure pattern up front; propose the hardened option.

---

## ENGINEERING WORKFLOW
- **Spec-first with OpenSpec** — write the spec under `docs/feature-plan/openspec/changes/<module>/` before code.
- **TDD with Pest** — red → green → refactor. Tests before merge.
- **context7 MCP** — pull *current* Laravel 13 / Next 16 / Fortify / Sanctum / Inertia v3 / Tailwind 4 APIs before writing code (training data is stale; Next 16 has breaking changes — also read `ecommerce-next-frontend/node_modules/next/dist/docs/`).
- **Laravel layering (SOLID/DRY):** thin Controller → Service → Repository (interface + Eloquent impl) → DTO (spatie/laravel-data) → Action → Policy. Storage behind a `StorageRepository` interface (default **server disk**, R2 switchable).
- **Next.js:** ISR for catalog reads, dynamic/server-actions for checkout & auth (secrets server-side), Suspense + skeletons, Zod validation, lodash debounce, optimistic UI, mobile-first.
- **Gates (Definition of Done):** Pest green · Larastan max clean · Pint clean · eslint/type-check clean · authz + validation present · no secret in client bundle · audit log on sensitive writes · openspec change archived.

## SKILLS to use on this project
- `shadcn-ui` — admin + storefront components.
- `frontend-design` — distinctive, non-generic storefront UI (Lovinna inspiration).
- `engineering:code-review` / `security-review` — before merging significant changes.
- `verify` / `run` — prove changes work in the real app, not just tests.

## KEY DECISIONS (locked 2026-06-18)
- Secure `owner` role; no backdoor; reversible Maintenance Lock.
- Default storage = **server disk** (R2 implemented + switchable later).
- SMS = provider-agnostic `SmsGateway` interface now; concrete BD adapter later.
- **Client hosts** the app; **license code skipped for now** → future **Phase 7** (signed heartbeat + reversible Suspended state; disable, never destroy). Until paid: don't hand over source/server access; rely on a written contract.

## LANGUAGE — ALWAYS (this project)
- **Always reply in easy, native, casual Dhakaiya Bangla** — warm and friendly, like the owner's "Dhakaia mama". No stiff/literary Bangla, no heavy Sanskritized words. Short, plain sentences.
- Keep **technical & UI terms in English** (database, migration, service, repository, Discounted price, Stock, deploy, commit, push…) — don't force awkward Bangla translations like "কাটা-দাম".
- Code, file paths, commands, and code comments stay in English. Only the conversation/explanation is Dhakaiya Bangla.
- Switch to English only if the owner explicitly asks for English.
