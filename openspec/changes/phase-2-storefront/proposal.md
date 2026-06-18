## Why

Customers need a fast, mobile-first storefront to browse the catalog and inquire/order via WhatsApp or web. This phase builds the public Next.js 16 UI consuming the Phase 1 catalog API (`/api/v1`), inspired by lovinna.com — dark, minimal, no header nav, no cart.

## What Changes

- Next.js storefront API client + types for categories/products (server-side fetch, ISR-style caching).
- Dark theme + mobile-first layout, dynamic metadata/OG.
- Home page: full-width banner, hero CTAs, Featured Collections (2-col desktop / 1-col mobile), info section, footer (contact/whatsapp/email/address).
- Floating WhatsApp button (bottom-right) + floating menu button (bottom-left) opening a left category drawer.
- Category page: full-width header image, name + details, product list (1/row), infinite scroll, categories grid at the end.
- Product display: image slider (arrows + thumbnails); Price (struck-through discount), Inquiry (WhatsApp deep link), Order Now (modal with qty +/- and WhatsApp/Web options).

## Capabilities

### New Capabilities
- `storefront-ui`: the public-facing Next.js pages, components, and API client for browsing the catalog and initiating inquiry/order.

### Modified Capabilities
<!-- none -->

## Impact

- **Code**: `ecommerce-next-frontend/` — `lib/`, `app/`, components.
- **Depends on**: Phase 1 `catalog-api`.
- **Config**: `API_BASE_URL` / `NEXT_PUBLIC_API_BASE_URL` env; backend CORS for the storefront origin.
- Order placement (web checkout) is Phase 3; here "Order on Web" links to the checkout route (stub until Phase 3).
