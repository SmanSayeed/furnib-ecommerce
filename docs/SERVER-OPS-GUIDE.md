# Furnib — Server & Deployment Operations Guide

The complete, reusable playbook for deploying & operating Furnib (Laravel
backend + Next.js storefront) on an **EasyPanel** host. Written from the real
first deployment so it can be repeated on a fresh server quickly.

Companion docs: [`DEPLOYMENT.md`](./DEPLOYMENT.md) (env tables/images),
[`DEPLOYMENT-RUNBOOK.md`](./DEPLOYMENT-RUNBOOK.md) (phased checklist).

---

## 0. TL;DR — order of operations
1. Snapshot the server (rollback point).
2. EasyPanel project (`furnib`) → create **db** (MySQL 8.4), **backend** (App), **frontend** (App).
3. Make the GitHub repo **public** (or use a registry) so EasyPanel can pull it.
4. Backend: set env → deploy → `migrate --force` + `db:seed --force` → set **Storage = R2** in admin.
5. Frontend: set env → deploy.
6. Domains: Cloudflare A records (grey) → EasyPanel add domains → Let's Encrypt → Cloudflare orange + Full(Strict).
7. Verify storefront + admin + a test order.

---

## 1. The stack / architecture

```
            Cloudflare (DNS + WAF, SSL: Full-Strict)
                       │
                       ▼
        EasyPanel Traefik   :80 / :443   ← edge: TLS + Let's Encrypt + routing
          ├── furnib.com           → frontend   (Next.js standalone, :3000)
          ├── www.furnib.com       → frontend
          └── admin.furnib.com     → backend    (nginx + php-fpm, :80)
                                        │  internal swarm overlay network
                                        ▼
                                   db  (MySQL 8.4, NO public port)
                                        +
                                   Cloudflare R2  (media, external, public CDN url)
```

- **EasyPanel = Docker Swarm orchestrator + Traefik proxy.** "EasyPanel" and
  "Docker" are not separate — EasyPanel builds your Dockerfiles and runs them as
  swarm services, with Traefik owning host :80/:443 and issuing Let's Encrypt certs.
- **Every project is isolated** (own overlay network). Furnib lives only in the
  `furnib` project; other devs' projects (e.g. `solfa`) are untouched.
- **Internal DNS hostname = `<project>_<service>`.** Project `furnib`, services
  `db`/`backend`/`frontend` → hosts `furnib_db`, `furnib_backend`, `furnib_frontend`.

### This server (reference)
- Host: Contabo VPS, Ubuntu 24.04, 8 cores / 23 GB RAM / 774 GB disk, IP `194.233.74.24`.
- EasyPanel v2.30.1, Traefik 3.6.7. Edge ports 80/443 + EasyPanel UI on 3000.

---

## 2. Repository & images

Monorepo `github.com/SmanSayeed/furnib-ecommerce`:
- `laravel-backend/` → `Dockerfile` (multi-stage: composer + Vite assets build →
  **nginx + php-fpm + supervisor** runtime on :80). Entrypoint caches config/views
  and runs `migrate --force` on boot.
- `ecommerce-next-frontend/` → `Dockerfile` (pnpm 9 → **Next.js standalone** on :3000).

Build/runtime decisions that matter:
- **pnpm 9** (pinned) — the repo lockfile is v9; pnpm 10 errors on ignored builds.
- Backend uses **pnpm** (not npm — `package-lock.json` is stale).
- GD compiled with **`--with-webp --with-avif`** — product images are converted to
  WebP, which fails on a GD without webp.
- nginx **fastcgi buffers enlarged** — Laravel session/XSRF headers overflow the 4k default.
- `next.config.ts` has `output: "standalone"`.

---

## 3. Step-by-step deploy (EasyPanel)

### 3.1 Database
EasyPanel → `furnib` → **+ Service → Database → MySQL**:
- Name `db`, Database `furnib`, User `furnib_user`, Image `mysql:8.4`.
- **Leave Password + Root Password BLANK → auto-generated strong values.** (Never type a weak/known password.)
- **Expose Port = 0** (internal only — never expose a DB publicly).
- Internal host → `furnib_db`. Read the password from the **Credentials** tab.

### 3.2 Backend (App)
- **Source → Github**: Owner `SmanSayeed`, Repo `furnib-ecommerce`, Branch `master`,
  **Build Path `laravel-backend`**.
- **Build → Dockerfile**, File `Dockerfile`.
- **Environment** → see [`DEPLOYMENT.md` §3]; key ones below. Generate `APP_KEY`
  locally with `php artisan key:generate --show`.
- Deploy → watch **Deployments → View** (build) then **Overview → Logs** (runtime).
- **Console** (`>_`): `php artisan migrate --force` then `php artisan db:seed --force`
  (seeds roles/permissions + owner + admin from env; the demo product seeder is NOT
  in DatabaseSeeder, so production stays clean).
- Log in at `/login` with the owner/admin creds → set **Settings → Storage (R2)**.

### 3.3 Frontend (App)
- **Source → Github**: same repo/branch, **Build Path `ecommerce-next-frontend`**.
- **Build → Dockerfile**, File `Dockerfile`.
- **Environment**: `API_BASE_URL=http://furnib_backend:80/api/v1` (internal), plus the
  four `NEXT_PUBLIC_*`.
- Deploy → test on the temp `*.easypanel.host` domain.

---

## 4. Environment variables (essentials)

**Backend** (🔒 = secret, enter directly in EasyPanel, never in repo/chat):
```
APP_ENV=production   APP_DEBUG=false   APP_KEY=🔒(base64, unique per env)
APP_URL=https://admin.furnib.com   FRONTEND_URL=https://furnib.com
LOG_CHANNEL=stderr   LOG_LEVEL=warning
DB_CONNECTION=mysql  DB_HOST=furnib_db  DB_PORT=3306
DB_DATABASE=furnib   DB_USERNAME=furnib_user   DB_PASSWORD=🔒
SESSION_DRIVER=database  CACHE_STORE=database  QUEUE_CONNECTION=database
# R2 (can also be set in Admin → Storage instead): R2_ACCESS_KEY_ID🔒 R2_SECRET_ACCESS_KEY🔒
#   R2_BUCKET R2_ENDPOINT R2_URL R2_DEFAULT_REGION=auto
```
- **DB_HOST is the service name `furnib_db`, NOT localhost/127.0.0.1** (separate containers).
- Gateway / SMTP / Marketing-CAPI secrets are entered in Admin → Settings (encrypted DB), not env.

**Frontend**:
```
API_BASE_URL=http://furnib_backend:80/api/v1     # server-side, internal, fast
NEXT_PUBLIC_API_BASE_URL=https://admin.furnib.com/api/v1
NEXT_PUBLIC_BACKEND_ORIGIN=https://admin.furnib.com
NEXT_PUBLIC_SITE_URL=https://furnib.com
NEXT_PUBLIC_WHATSAPP=8801XXXXXXXXX
```
- `NEXT_PUBLIC_*` are baked at **build** time (EasyPanel passes service env as build args).

---

## 5. Media storage (Cloudflare R2)

- Media disk is chosen by the DB setting `storage.driver` (`server` | `r2`), managed in
  **Admin → Settings → Storage (R2)**. R2 keys are stored encrypted there (env = fallback).
- Product images are optimized to **WebP** before upload (needs GD webp — handled in the image).
- Category/branding images are stored as-is.
- R2 bucket must have **public access (r2.dev) or a custom domain** enabled so images load.
- Switching driver to R2 does NOT move existing files — re-upload, or migrate the bucket.

---

## 6. Where to check logs

| What | Where |
|---|---|
| App runtime errors / 500s (full stack trace) | `backend` → **Overview → Logs** (we log to `stderr`) |
| Build logs (composer/pnpm/docker) | service → **Deployments → View** the deploy |
| Run artisan / tinker / migrate | service → **Console** (`>_` icon) |
| nginx access/errors | same Overview → Logs (nginx logs to stdout/stderr) |
| Live container stats | service → Overview (CPU/Mem) or **Monitor** |

> `APP_DEBUG=false` in prod, so the browser shows a generic 500 — the real error is in the logs.

---

## 7. Domain + Cloudflare + SSL (safe order)

1. Test everything on the free `*.easypanel.host` domains first.
2. **Cloudflare DNS** (zone `furnib.com`): A records `@`, `www`, `admin` → `194.233.74.24`,
   set to **DNS only (grey ☁️)** initially.
   - ⚠️ If `@` already serves a live site, confirm before repointing.
3. **EasyPanel Domains**: `frontend` → add `furnib.com` (+`www`) → port 3000;
   `backend` → add `admin.furnib.com` → port 80. Let's Encrypt issues certs (grey DNS lets HTTP-01 succeed).
4. Cert issued → Cloudflare proxy **orange 🟧** + **SSL/TLS = Full (Strict)** (never *Flexible*).
5. Verify `https://furnib.com` + `https://admin.furnib.com/login`.

---

## 8. Issues we hit & their fixes (real)

| Symptom | Cause | Fix |
|---|---|---|
| `npm ci` fails in build | stale `package-lock.json` | Backend builds with **pnpm 9** |
| pnpm `ERR_PNPM_IGNORED_BUILDS` (sharp) | corepack pulled pnpm 10 | **Pin `npm i -g pnpm@9`** in Dockerfile |
| pnpm `packages field missing` | `pnpm-workspace.yaml` had no `packages:` | add `packages: ['.']` |
| Service "Waiting to start…" / crash | deployed before setting env | set Environment → **redeploy** |
| `/login` → **502**, log: *upstream sent too big header* | nginx default 4k fastcgi buffer too small for session/XSRF headers | enlarge `fastcgi_buffer_size/buffers/busy_buffers_size` in `docker/nginx.conf` |
| Admin category image broken | driver=local + `APP_URL` not live yet | switch **Storage = R2** (public URL) + re-upload |
| No way to set storage driver | missing admin UI | built **Settings → Storage (R2)** page |
| Product upload → **500** (category upload was fine) | GD lacked **WebP** (product images convert to WebP) | GD `--with-webp --with-avif` + libwebp/libavif in Dockerfile |
| EasyPanel GitHub token is another dev's | global token, shared | **make repo public** (don't overwrite the shared token → would break their deploys) |

---

## 9. Common mistakes (avoid)
- ❌ `DB_HOST=localhost` — use the **service name** (`furnib_db`).
- ❌ Overwriting EasyPanel's **global GitHub token** on a shared host → breaks others.
- ❌ Raw `docker rm/stop/prune` on a shared host → can kill other projects. Use EasyPanel only.
- ❌ Pasting secrets (DB pw, R2 keys, APP_KEY) into chat/screenshots/repo. Rotate if leaked.
- ❌ `APP_DEBUG=true` in prod (leaks stack traces/secrets).
- ❌ Cloudflare SSL = *Flexible* → use **Full (Strict)**.
- ❌ Exposing the DB on a public port.
- ❌ Reusing the dev `APP_KEY` in prod (and reusing one bucket across envs is best avoided).
- ❌ Running demo/catalog seeders in production.

---

## 10. Backup

- **Whole server:** Contabo **Snapshot** (one-click rollback) — take before any risky change.
- **Database:** `backend`/`db` **Console** →
  `mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" furnib > /tmp/furnib_$(date +%F).sql`
  then download with `scp root@IP:/tmp/furnib_*.sql ./` (or use EasyPanel `db → Backups`).
- **Media (R2):** lives in Cloudflare R2 (external) — already off-server; back up the bucket
  via `rclone` if needed.
- **Settings/secrets:** stored encrypted in the DB (covered by the DB dump). Keep the
  `APP_KEY` safe — without it, encrypted settings can't be decrypted.

---

## 11. Migration / data changes
- Schema: `php artisan migrate --force` (also auto-runs on container boot via entrypoint).
- Roll back a bad migrate: `php artisan migrate:rollback --step=1` (Console).
- Seeders (prod-safe): `php artisan db:seed --force` → roles/permissions + owner + admin only.
- After deploys the entrypoint re-caches config/views automatically.

---

## 12. Moving to a NEW server (transfer)

1. New server: install EasyPanel; create project `furnib`.
2. Create `db` (MySQL 8.4), `backend`, `frontend` exactly as §3 (same service names → same internal hosts).
3. **Restore DB:** import the dump into the new `db` (Console: `mysql -u root -p furnib < dump.sql`).
   Reuse the **same `APP_KEY`** so encrypted settings still decrypt.
4. Media: R2 is external — **no move needed**; keep the same R2 settings.
5. Set env on backend/frontend (same as old). Deploy both from the same repo/branch.
6. Domains: repoint Cloudflare A records to the **new server IP** (grey → cert → orange Full-Strict).
7. Verify, then decommission the old server.

> Because media (R2) + DB dump + APP_KEY carry all state, a transfer is mostly: recreate
> services, restore DB, repoint DNS.

---

## 13. Go-live checklist
- [ ] Snapshot taken
- [ ] db / backend / frontend green on temp domains
- [ ] migrate + seed done; admin login works
- [ ] Storage = R2; product image upload works
- [ ] Real domains + Let's Encrypt + Cloudflare Full(Strict)
- [ ] Test order (COD + online); R2 images load; feed at `/feed/products.csv`
- [ ] `TrustProxies` set (correct client IP/HTTPS); GTM/Pixel/CAPI IDs in Admin → Marketing
- [ ] `APP_DEBUG=false`, secrets only in EasyPanel/encrypted DB, DB not public
