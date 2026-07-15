## Why

Three storefront issues the owner reported:

- **J — WhatsApp inquiry shows no photo.** The inquiry deep link pasted a raw `.webp` image-file
  URL into the message text. WhatsApp only renders a preview by crawling the **OG tags of a page
  URL**, so a bare image URL showed nothing — and the inquiry carried no page link at all.
- **K — "See more" overlaps the caption on mobile.** The button was absolutely positioned over
  line 2 behind a gradient mask; on the semi-transparent card the clipped words bled through it.
- **L — Desktop category banner is cropped.** The banner was a viewport-*height* box
  (`h-[22vh] sm:h-[50vh]`), so its aspect ratio changed with the window and `object-cover` cropped
  a different amount per visitor; the `<source>`/height breakpoints were also mismatched
  (768px vs 640px).

## What Changes

- **J:** the WhatsApp inquiry message now leads with the **product page URL**
  (`{siteUrl}/product/{slug}`) and drops the raw image URL, so WhatsApp previews the page's OG
  card. The product and category pages emit **fully-specified OG images** (absolute url,
  secure_url, 1200×630, alt), and `app/layout` sets **`metadataBase`** so relative OG/canonical
  URLs resolve. `NEXT_PUBLIC_SITE_URL` is documented in `.env.example` (must be the real origin at
  build time).
- **K:** the caption clamps to two lines and "See more" is a normal block on its own line with a
  gap — no overlay, nothing bleeds through. The block reserves a min height so the grid stays tidy.
- **L:** the banner is now an **aspect box** (`aspect-4/5 md:aspect-8/3`) matching the 800×1000
  mobile / 1600×600 desktop assets, with the aspect switch aligned to the `<source>` 768px
  breakpoint — `object-cover` has nothing left to crop.

## Impact

- Storefront only. `NEXT_PUBLIC_SITE_URL` is a **build-time** var → the frontend must be
  redeployed for J to take effect, and set to `https://furnib.com`.
- Verify after deploy with Playwright at 360/390/414px (K), 1280–1920px + the 700px band (L), and
  Facebook's Sharing Debugger (J — same crawler WhatsApp uses; force a re-scrape).
- Follow-up (not in this change): a server-side **JPEG social thumbnail** derivative so OG images
  aren't WebP, and enforced banner dimensions on upload.
