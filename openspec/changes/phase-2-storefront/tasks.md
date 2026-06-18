## 1. Setup

- [x] 1.1 Env (`API_BASE_URL`, `NEXT_PUBLIC_*`) + `.env.example`; reads via server fetch (no CORS needed — infinite scroll proxied through a Next route handler)
- [x] 1.2 API client + types (`lib/api.ts`, `lib/types.ts`, `lib/config.ts`, `lib/image.ts`, `lib/whatsapp.ts`) with `revalidate: 60`
- [x] 1.3 Dark mobile-first theme (globals.css) + async root layout + dynamic metadata/OG

## 2. Home

- [x] 2.1 Hero banner + CTAs + "price in photos" section + footer (contact/whatsapp/email/address)
- [x] 2.2 Featured Collections (active categories from API, 2-col desktop / 1-col mobile)

## 3. Floating navigation

- [x] 3.1 Floating WhatsApp button (bottom-right)
- [x] 3.2 Floating menu button (bottom-left) → left category drawer (verified: Home/Chair/Table)

## 4. Category page

- [x] 4.1 Header image + title + details
- [x] 4.2 Product list (1/row) + infinite scroll (IntersectionObserver via same-origin proxy) + categories grid at end

## 5. Product display & actions

- [x] 5.1 Image slider (arrows + thumbnails) with safe-image fallback
- [x] 5.2 Price (struck-through discount) / Inquiry (WhatsApp) / Order Now buttons
- [x] 5.3 Order modal (qty stepper, WhatsApp + Web options; Web → checkout stub)

## 6. Verify

- [x] 6.1 Verified in browser: home, category (5 products + order modal), product detail, mobile drawer — data from live API
- [x] 6.2 tsc --noEmit clean, eslint clean
