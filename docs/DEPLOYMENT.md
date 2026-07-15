# Furnib — Deployment (EasyPanel / Docker)

Both apps are fully dockerized and **build + boot verified** locally
(`furnib-frontend` → HTTP 200; `furnib-backend` → `/api/v1/health` 200).

```
            Cloudflare (DNS + WAF, Full-Strict TLS)
                       │
                       ▼
        EasyPanel Traefik  :80/:443   (edge — TLS + Let's Encrypt)
          ├── <domain>          → furnib_frontend  (Next.js  :3000)
          └── admin.<domain>    → furnib_backend   (nginx+php-fpm :80)
                                       │  internal docker network
                                       ▼
                                  furnib_db  (MySQL — NO public port)
```

> Do **everything inside the existing empty `furnib` project**. Never run raw
> `docker` commands or touch other projects / Traefik — that's what keeps the
> other live sites safe.

---

## 1. Images (in this repo, already tested)

| App | Build path | Dockerfile | Serves |
|---|---|---|---|
| Backend | `laravel-backend` | `Dockerfile` (multi-stage → nginx + php-fpm + supervisor) | `:80` |
| Frontend | `ecommerce-next-frontend` | `Dockerfile` (Next.js standalone) | `:3000` |

The backend image runs migrations on boot (`RUN_MIGRATIONS=true` by default) and
caches config + views. The frontend bakes `NEXT_PUBLIC_*` at **build** time.

Local test (optional):
```bash
# backend
docker build -t furnib-backend laravel-backend
# frontend (NEXT_PUBLIC_* are build args)
docker build -t furnib-frontend \
  --build-arg NEXT_PUBLIC_API_BASE_URL=https://admin.<domain>/api/v1 \
  --build-arg NEXT_PUBLIC_BACKEND_ORIGIN=https://admin.<domain> \
  --build-arg NEXT_PUBLIC_SITE_URL=https://<domain> \
  --build-arg NEXT_PUBLIC_WHATSAPP=8801XXXXXXXXX \
  ecommerce-next-frontend
```

---

## 2. EasyPanel services (in the `furnib` project)

1. **`furnib_db`** — add a **MySQL** service. Note the generated password +
   internal hostname. **Do not expose a public port.**
2. **`furnib_backend`** — **App** → Source = GitHub `SmanSayeed/furnib-ecommerce`,
   branch `master`, **Build path `laravel-backend`**, Build = Dockerfile.
   Add the env from §3. Add domain `admin.<domain>` → port **80**.
3. **`furnib_frontend`** — **App** → same repo, **Build path
   `ecommerce-next-frontend`**, Build = Dockerfile, **Build args** = the four
   `NEXT_PUBLIC_*` (§4), runtime env `API_BASE_URL`. Add domain `<domain>` → port **3000**.

After the first backend deploy, in **furnib_backend → Console**:
```bash
php artisan migrate --force        # (also runs automatically on boot)
php artisan db:seed --force        # essential seeders only (admin user, roles, shipping zones)
```
> Do NOT run the demo product seeder in production.

---

## 3. Backend env (EasyPanel → furnib_backend → Environment)

🔒 = secret. Use the internal hostnames EasyPanel shows for your services.

| Key | Value | |
|---|---|---|
| `APP_NAME` | `Furnib` | |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_KEY` | `base64:…` (run `php artisan key:generate --show`) | 🔒 |
| `APP_URL` | `https://admin.<domain>` | |
| `FRONTEND_URL` | `https://<domain>` | |
| `LOG_CHANNEL` | `stderr` (so logs show in EasyPanel) | |
| `DB_CONNECTION` | `mysql` | |
| `DB_HOST` | `<furnib_db internal host>` | |
| `DB_PORT` | `3306` | |
| `DB_DATABASE` | `furnib` | |
| `DB_USERNAME` | `<from MySQL service>` | |
| `DB_PASSWORD` | `<from MySQL service>` | 🔒 |
| `SESSION_DRIVER` | `database` | |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `database` | |
| `FILESYSTEM_DISK` | `s3` (Cloudflare R2) | |
| `AWS_ACCESS_KEY_ID` | R2 key | 🔒 |
| `AWS_SECRET_ACCESS_KEY` | R2 secret | 🔒 |
| `AWS_DEFAULT_REGION` | `auto` | |
| `AWS_BUCKET` | `<r2-bucket>` | |
| `AWS_ENDPOINT` | `https://<acct>.r2.cloudflarestorage.com` | |
| `AWS_URL` | `https://<public-r2-or-cdn-domain>` | |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` | |

Gateway / SMTP / Marketing-CAPI creds are entered in **Admin → Settings** and
stored **encrypted in the DB** — they do NOT go in env.

> ⚠️ **TrustProxies:** behind Traefik, set Laravel to trust the proxy so
> `request->ip()` (CAPI) and HTTPS URL generation are correct. This is a small
> code change in `bootstrap/app.php` (`->trustProxies(at: '*')`) — ask and I'll add it.

---

## 4. Frontend env (EasyPanel → furnib_frontend)

**Build args** (baked into the client bundle):

| Key | Value |
|---|---|
| `NEXT_PUBLIC_API_BASE_URL` | `https://admin.<domain>/api/v1` |
| `NEXT_PUBLIC_BACKEND_ORIGIN` | `https://admin.<domain>` |
| `NEXT_PUBLIC_SITE_URL` | `https://<domain>` |
| `NEXT_PUBLIC_WHATSAPP` | `8801XXXXXXXXX` |

**Runtime env** (server-side fetches use the internal network — faster, no public hop):

| Key | Value |
|---|---|
| `API_BASE_URL` | `http://<furnib_backend internal host>:80/api/v1` |

---

## 5. Domain + Cloudflare (safe order)

1. Test first on EasyPanel's free `*.easypanel.host` domains.
2. Cloudflare DNS → A records `<domain>` and `admin.<domain>` → `194.233.74.24`,
   start **DNS-only (grey ☁️)**.
3. EasyPanel Domains map: `<domain> → furnib_frontend:3000`,
   `admin.<domain> → furnib_backend:80` → let Let's Encrypt issue the cert.
4. Cert issued → flip Cloudflare proxy to **orange 🟧** and set
   **SSL/TLS = Full (Strict)** (never *Flexible*).

---

## 6. Go-live checklist

- [ ] Storefront loads; admin login works; place a test order (COD + online).
- [ ] Media (R2) images load on storefront.
- [ ] Product feed enabled at Marketing → Facebook Commerce; the secured `/feed/{slug}/products.csv` (Basic auth) returns the catalog. (The old public `/feed/products.csv` is removed.)
- [ ] (Later) GTM / Pixel / CAPI token in **Admin → Marketing → Tracking & Pixels**.
- [ ] TrustProxies set (see §3).
