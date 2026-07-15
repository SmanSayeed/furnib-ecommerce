## Why

A Meta/Google product feed already existed, but it was **public** — the full catalogue with
prices and stock was scrapeable at `/feed/products.csv` by anyone. It was also thin (11 columns)
and there was no admin surface to manage it. In Bangladesh, native Meta checkout is unavailable,
so the catalogue exists for **ads (Advantage+/DPA), Instagram tagging and WhatsApp/Messenger
sharing** — the buy action links out to the storefront. Meta's own recommendation for that is a
**scheduled feed URL** (it pulls the CSV hourly), which is far simpler and more robust than a
Graph API push. So this change is feed-first: secure it, enrich it, and give the owner a page to
run it.

## What Changes

- **Secure the feed.** `/feed/products.csv` (public) is replaced by
  `/feed/{slug}/products.csv` behind three gates: an on/off switch, an **unguessable path
  segment**, and **HTTP Basic auth** (password stored **encrypted**), plus rate-limiting. A
  disabled feed or wrong slug is an indistinguishable 404; missing/wrong credentials get a 401
  challenge. (`FeedAccess`, `FeedController`.)
- **Enrich the feed** to more of the Meta spec: `product_type` (category breadcrumb — powers feed
  filtering), `item_group_id`, `quantity_to_sell_on_facebook`, on top of the existing
  id/title/availability/price/sale_price rules (sale_price is only emitted when strictly below
  price; availability is the consolidated `in stock` / `out of stock`).
- **Admin page** — Marketing → Facebook Commerce (`marketing.manage`): enable the feed, see the
  secured URL + Copy, the Basic-auth username, a **regenerate** action (rotates slug + password,
  invalidating the old URL), the freshly generated password shown **once**, optional Catalog/
  Business IDs for reference, the Commerce Manager scheduled-feed steps, and a **category-filtered
  CSV export**.

## Impact

- Affected specs: `marketing`.
- Security fix: the catalogue is no longer world-readable (was flagged 🟠 in the fix plan). The
  feed password is encrypted at rest and shown once. Removing the public route updates the
  security test (the feed is no longer in the public-endpoint set) and the deployment docs.
- Scope: **feed-first** — the heavier Graph API `items_batch` push, batch-status polling, and the
  taxonomy DB columns are intentionally **out of scope** (owner-selected). Native Shops order sync
  is not built (unusable in BD). Follow-up: a JPEG social-thumbnail derivative so `image_link`
  isn't WebP.
