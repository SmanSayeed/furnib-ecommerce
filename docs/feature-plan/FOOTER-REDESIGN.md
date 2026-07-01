# Footer Redesign — Plan & Feature Breakdown

> Storefront footer rebuilt to match the "Solfa Furnishers" reference layout, on
> our **orange brand background** (`bg-brand`, #e85d1f), with animated hovers and
> **everything manageable from the admin Footer menu**. No Customer-Service column.
>
> Decisions locked with the owner (2026-07-01):
> - Contact "brand" button → links to **Home (`/`)**.
> - "Member's Of" & "Delivery Partner" → **one logo each** (toggle + image + heading + optional link).
> - Delivery Time / Trade License / Registered Address are **NOT** shown as standalone
>   footer text. Instead the **About Us column lists page links** (like the reference),
>   and that compliance content lives **inside the pages** (About Us page carries the
>   trade licence + registered address; a Delivery/Return page carries the delivery times).

---

## 1. Target layout — three bands

### Band 1 — four columns
Desktop: 4 columns. Mobile: stacked (1 col), tablet: 2 cols.

| Col | Heading | Content | Admin source |
|---|---|---|---|
| 1 | *(brand)* | Footer **logo**, address, phone, email — each with hover animation | Footer details (existing) |
| 2 | **About Us** | A single **list of page links**: the 3 fixed legal pages (Terms, Privacy, Return/Refund) **+** any pages the owner adds via the footer page-picker (`/admin/pages`) | Footer details link-picker + Pages (existing) |
| 3 | **Contact Us** | Business-hours text ("Every Day 9 AM To 2 AM"), **Call Us** button, **phone** button, **brand** button (→ `/`) | Footer details (new fields) |
| 4 | **Follow Us** | Social icons (top) + newsletter subscribe (below) | Footer social + Newsletter (existing) |

No Customer-Service column.

### Band 2 — partner badges (two toggle-able blocks)
| Block | Content | Admin |
|---|---|---|
| **Member's Of** | one logo (e.g. e-CAB) | enabled on/off, heading, image upload, optional link |
| **Delivery Partner** | one logo (e.g. RedX) | enabled on/off, heading, image upload, optional link |

### Band 3 — Pay securely with
- **SSLCommerz banner**, full-width, centered (kept as-is; `payment_banner` still owner-managed).

---

## 2. Removed from the current footer
1. Standalone **Delivery Time** block (Inside/Outside Dhaka) — removed. This also kills
   the current **"Inside Dhaka: Inside Dhaka: 5 days" double-prefix bug** (admin default
   value already contains the prefix that the footer label also prepended).
2. Standalone **Trade License No.** and **Registered Address** text blocks — removed from
   footer; the info moves into the About Us / policy **page bodies** (still satisfies
   gateway compliance #4/#5/#6 because those pages are linked in the footer).
3. Old "Pay securely with" placement → replaced by the full-width centered version.

---

## 3. Data model — settings keys (group `branding`)

**Existing (reused):** `logo_footer`, `contact_phone`, `contact_email`, `contact_address`,
`about_links`, `whatsapp`, `payment_banner`, social_* keys, newsletter.

**New keys:**
| Key | Type | Purpose |
|---|---|---|
| `contact_hours` | text | "Every Day 9 AM To 2 AM" |
| `member_of_enabled` | bool | show/hide Member's Of block |
| `member_of_heading` | text | default "Member's Of" |
| `member_of_image` | file | badge logo (≤20 MB, png/jpg/webp) |
| `member_of_url` | text (opt) | optional link on the badge |
| `delivery_partner_enabled` | bool | show/hide Delivery Partner block |
| `delivery_partner_heading` | text | default "Delivery Partner" |
| `delivery_partner_image` | file | badge logo |
| `delivery_partner_url` | text (opt) | optional link |

The Contact brand button reuses `site_name` (label) + links to `/`; no new key needed.

---

## 4. Public settings API (`Api\SettingController::index`) — additions
```
footer_contact: { hours: string|null }
footer_badges: {
  member_of:        { enabled: bool, heading: string, image_url: string|null, url: string|null },
  delivery_partner: { enabled: bool, heading: string, image_url: string|null, url: string|null }
}
```
`compliance.delivery_inside_dhaka` / `delivery_outside_dhaka` / `trade_license_no` /
`registered_address` remain in the API for the pages/other uses, but the footer no
longer renders them as text.

---

## 5. Work breakdown

### Backend (Laravel)
- Add new keys to `FooterDetailController` (TEXT_KEYS + 2 boolean toggles + 2 image files) — store/load/delete.
- `FooterDetailUpdateRequest` — validate new text/bool fields + 2 image files (max 20480, png/jpg/jpeg/webp).
- `Api\SettingController` — add `footer_contact` + `footer_badges` to the response.
- Test: assert the settings API returns the new blocks.

### Admin UI (`resources/js/pages/settings/footer-details.tsx`)
- New sections: **Contact hours**; **Member's Of** (toggle + heading + image + link); **Delivery Partner** (toggle + heading + image + link).
- Match the existing responsive form pattern (single column, image previews, sticky save).

### Frontend (Next storefront — `components/Footer.tsx`)
- Rewrite to the 3-band layout, orange bg, animated hovers (link: underline/translate on hover; badge: grayscale→color).
- **About Us column** = merge `about_links` + `legal_pages` (dedupe) into one link list.
- **Contact column** = hours + Call Us / phone / brand(→/) buttons.
- **Follow Us column** = socials + newsletter.
- **Band 2** = badges (render only when enabled + image present).
- **Band 3** = SSLCommerz full-width centered.
- Remove the compliance `dl` text block.
- Update `lib/types.ts` (`footer_contact`, `footer_badges`).
- Mobile-first; verify light/dark, down to 360px.

---

## 6. Phases / checklist
1. Backend fields + API shape (+ test).
2. Admin UI controls in Footer details.
3. Footer.tsx rewrite (layout + hover + responsive).
4. Remove Delivery Time / compliance text blocks (bug gone).
5. Verify + commit + push. Owner then: `php artisan migrate` (none needed — settings only), fill values, upload badge logos, rebuild both apps.

---

## 7. Everything the admin can manage (dynamic)
Logo · address · phone · email · About-Us page links (fixed 3 + custom) · contact hours ·
Call/phone/brand buttons · social icons · newsletter · Member's Of (on/off + logo) ·
Delivery Partner (on/off + logo) · SSLCommerz banner.
