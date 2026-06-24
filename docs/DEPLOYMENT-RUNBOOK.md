# Furnib — Step-by-step Deployment Runbook (shared server, EasyPanel)

This server runs **other developers' live, public projects** (solfa, test, …).
The whole runbook is built around one rule: **never touch anything outside the
`furnib` project, and recon before every action.** Nothing is assumed — each
phase first gathers exact facts, then acts.

Companion: env-var tables + image details are in [`DEPLOYMENT.md`](./DEPLOYMENT.md).

---

## 🔐 Safety rules (read once, apply always)

1. **Only the `furnib` EasyPanel project.** Do not edit/stop/delete any other
   project, service, container, volume, network, or Traefik config.
2. **Never run raw destructive Docker:** no `docker rm`, `docker stop`,
   `docker system prune`, `docker volume rm`, `docker network rm`. Recon uses
   **read-only** commands only (each is marked `[SAFE / READ-ONLY]`).
3. **Never paste secrets into chat** (DB passwords, R2 keys, APP_KEY, `.env`
   contents). Enter them directly in EasyPanel. Share only non-secret structure.
4. **Run only the commands given here.** If a command isn't in this runbook or
   explicitly provided, don't run it — ask first.
5. **One phase at a time.** At each `⛔ REPORT` gate, paste the output and wait
   for the next step before continuing.

---

## Phase 0 — FINDINGS (locked 2026-06-24)

Recon confirmed; decisions below are final unless noted.

- **Edge:** `easypanel-traefik` (Traefik 3.6.7) owns host `:80/:443`. We never bind them.
- **Orchestrator:** Docker **Swarm** (overlay nets `easypanel`, `easypanel-solfa`).
  EasyPanel builds our Dockerfiles and runs them as swarm services. No Dockerfile changes needed.
- **solfa backend** runs **nginx + php8-fpm + supervisor** from a Git App — identical to our
  backend image pattern (proven on this host).
- **Internal DNS host = `<project>_<service>`.** So name furnib services WITHOUT a prefix:
  | Service (name it exactly) | Internal host | EasyPanel temp domain |
  |---|---|---|
  | `db`  (MySQL 8)   | `furnib_db`       | — (no public port) |
  | `backend` (App)   | `furnib_backend`  | `furnib-backend.<...>.easypanel.host` |
  | `frontend` (App)  | `furnib_frontend` | `furnib-frontend.<...>.easypanel.host` |
- **DB engine:** **MySQL 8** (matches the app's dev DB; avoids MariaDB edge cases). DB private, no host port.
- **GitHub:** a PAT with `repo` scope is already saved in EasyPanel → Settings → Github,
  so private repos accessible to that account can be pulled.
- **Headroom:** 23 GB RAM / 750 GB disk / 8 cores free — ample.
- **Domain:** `furnib.com` on Cloudflare (A record exists). Storefront → `furnib.com` (+ `www`),
  admin/API → `admin.furnib.com`.

---

## Phase 0b — Recon commands (already run; kept for reference)

Goal: learn the running topology so the deployment can't collide with others.
All commands below are **read-only**.

```bash
# 0.1 What is running (names/images/ports/status) — read-only
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Ports}}\t{{.Status}}'

# 0.2 Docker networks — read-only
docker network ls

# 0.3 Any pre-existing furnib container? — read-only
docker ps -a --format '{{.Names}}' | grep -i furnib || echo "no furnib containers"

# 0.4 Is Traefik the edge proxy, and who owns 80/443? — read-only
docker ps --format '{{.Names}} {{.Ports}}' | grep -iE 'traefik|:80->|:443->' || echo "none"

# 0.5 Host listening ports (confirm we won't clash) — read-only
ss -tlnp | grep -E ':80 |:443 |:3306 |:3000 ' || echo "none of those host-bound"

# 0.6 Headroom — read-only
free -h ; df -h / ; nproc
```

**⛔ REPORT 0:** paste the output of 0.1–0.6.
Also confirm in the EasyPanel UI (screenshots, **blur any secret values**):
- (a) `solfa` → `solfa_backend` → **Source/Build** tab (Git? Image? Dockerfile? build path?).
- (b) `solfa` → **Domains** mapping (to learn the internal-hostname pattern).
- (c) `furnib` project page (confirm it is still empty / what's in it).

**Also answer (no secrets):**
- F1. Exact Furnib domain(s) — e.g. `furnib.com.bd` for storefront, `admin.…` for backend?
- F2. Is `github.com/SmanSayeed/furnib-ecommerce` **public or private**?
      (Private → EasyPanel needs a deploy key / GitHub App — we'll set that up.)
- F3. Is the Cloudflare zone for the Furnib domain the **same Cloudflare account**
      that already fronts solfa? (affects DNS steps)

> I will read your output and fill the real values into Phases 2–5 before you run them.

---

## Phase 1 — Backup & rollback point (before any change)

1. **Contabo snapshot** of the whole VPS (control panel) — the one-click rollback.
2. Pull the loose code/db already in `/root` to your PC (from **your PC**):
   ```bash
   scp root@194.233.74.24:/root/solfa-backend-code.zip  ./furnib-server-backup/
   scp root@194.233.74.24:/root/solfa-frontend-code.zip ./furnib-server-backup/
   scp root@194.233.74.24:/root/solfa_db_backup.sql     ./furnib-server-backup/
   ```
3. (Optional, insurance) fresh dump of other projects' DBs via their EasyPanel
   service Console — **read-only**, provided per-project when we get there.

**⛔ REPORT 1:** confirm the snapshot is taken and backups downloaded.

---

## Phase 2 — Database service (`furnib_db`)

> Exact names/hosts filled in after Phase 0.
1. EasyPanel → `furnib` project → **+ Service → MySQL** (or MariaDB to match solfa).
2. Name it, set DB name `furnib`, let it generate a password. **No public port.**
3. Note the **internal hostname** EasyPanel shows — you'll paste it (host only,
   not the password) so I can set `DB_HOST` correctly.

**⛔ REPORT 2:** the internal hostname + DB name + username (NOT the password).

---

## Phase 3 — Backend service (`furnib_backend`)

1. EasyPanel → `furnib` → **+ Service → App**.
2. Source = GitHub repo (set up access first if private), branch `master`,
   **Build path `laravel-backend`**, Build method = **Dockerfile**.
3. Environment: per [`DEPLOYMENT.md` §3](./DEPLOYMENT.md) — you enter secrets
   directly; I'll give you the exact non-secret values + `APP_KEY` generation step.
4. Deploy. Watch logs. Then **Console**: `php artisan migrate --force` + essential seeders.

**⛔ REPORT 3:** deploy log tail (last ~30 lines) + `*.easypanel.host` health check
result for the backend.

---

## Phase 4 — Frontend service (`furnib_frontend`)

1. EasyPanel → `furnib` → **+ Service → App**, same repo, **Build path
   `ecommerce-next-frontend`**, Dockerfile.
2. **Build args** = the four `NEXT_PUBLIC_*` (real domain values).
3. **Runtime env** `API_BASE_URL` = internal backend host.
4. Deploy; open the `*.easypanel.host` URL.

**⛔ REPORT 4:** the storefront loads on the temp domain? screenshot.

---

## Phase 5 — Domain + Cloudflare + SSL (safe order)

1. Test fully on `*.easypanel.host` first.
2. Cloudflare DNS A records → `194.233.74.24`, **DNS-only (grey)** initially.
3. EasyPanel Domains: map storefront + admin domains → respective services →
   Let's Encrypt issues certs.
4. Certs OK → Cloudflare proxy **orange**, **SSL/TLS = Full (Strict)**.

**⛔ REPORT 5:** both domains serve HTTPS; cert issued.

---

## Phase 6 — Verify & go-live

- Storefront loads; admin login; place a COD + an online test order; R2 images load.
- `https://admin.<domain>/feed/products.csv` returns the feed.

## Phase 7 — Hardening

- Set Laravel **TrustProxies** (`bootstrap/app.php`) for correct client IP/HTTPS.
- Later: GTM/Pixel/CAPI token in **Admin → Marketing → Tracking & Pixels**.
- Cloudflare WAF/rate-limit on `admin.<domain>`; confirm `furnib_db` has no public port.

---

### Progress log (fill as we go)
- [ ] Phase 0 recon reported
- [ ] Phase 1 snapshot + backup
- [ ] Phase 2 db created
- [ ] Phase 3 backend live (temp domain)
- [ ] Phase 4 frontend live (temp domain)
- [ ] Phase 5 real domains + SSL
- [ ] Phase 6 verified
- [ ] Phase 7 hardened
