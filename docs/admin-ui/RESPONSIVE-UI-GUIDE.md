# Furnib Admin — Responsive UI/UX Guide & Rules

> Binding design rules for the Furnib admin panel (Laravel + Inertia React + shadcn/ui + Tailwind 4).
> Every admin page MUST follow this. Goal: a premium, ThemeForest-grade dashboard that is
> flawless on desktop **and** does not break on mobile.
>
> Synthesized from current (2025–26) UI/UX guidance — see Sources at the bottom.

## 0. Golden rule (the one that bit us)
**On mobile the sidebar must be an off-canvas OVERLAY, never a layout column.**
A sidebar that stays in document flow on small screens squeezes the content and breaks the
page. On `< md` (768px) the sidebar renders as a **Sheet/drawer** that slides over the content
(with a scrim), toggled by a hamburger, and closes on nav-tap / overlay-tap / Esc. The page
content always gets the full width underneath.

shadcn `components/ui/sidebar.tsx` already does this (it uses `useIsMobile()` and renders a
`Sheet` on mobile, a fixed rail on desktop). **Use it — do not hand-roll the shell.**

## 1. Breakpoints (Tailwind defaults)
| Token | Min width | Primary use |
|------|-----------|-------------|
| (base) | 0 | Mobile-first defaults |
| `sm` | 640px | Large phones / small tablets |
| `md` | 768px | **Sidebar becomes persistent here**; tablets |
| `lg` | 1024px | Laptops — full multi-column dashboards |
| `xl` | 1280px | Wide desktops — max content width caps |

- Design **mobile-first**: write base styles for phone, add `md:`/`lg:` to enhance.
- Cap content width on huge screens: `mx-auto max-w-7xl` for page containers.

## 2. App shell & navigation
- **Desktop (`md+`):** persistent left sidebar, collapsible to an **icon rail** (toggle persists in a cookie). Grouped sections with labels + icons; active item highlighted (brand orange). Top bar: breadcrumb, global search (`⌘K`), quick-create `+`, notifications, theme toggle, profile menu.
- **Mobile (`< md`):** sidebar hidden; a **hamburger** in the top bar opens the overlay drawer. Search collapses to an icon that expands to full-width. Profile/notifications go into a top-right menu.
- **Never** show the desktop rail and the content side-by-side below `md`.
- Provide a visible, ≥44px **close button** on the drawer; trap focus while open; restore focus on close.
- Keep nav labels short, group by task, icon + label (not icon-only on desktop).

## 3. Touch targets & spacing (accessibility)
- **Minimum:** 24×24 CSS px with ≥8px gap to neighbours (WCAG 2.2 SC 2.5.8, AA).
- **Recommended for primary actions / mobile:** **44×44px** (Apple HIG) — use this for buttons, nav items, table row actions, bottom-bar items.
- Form controls (input/select/button) min height **44px** on touch; `padding: 8px 12px` minimum.
- Don't crowd: never place two tap targets closer than 8px without enlarging them.
- Icon-only buttons must have `aria-label`.

## 4. Data tables (the hardest responsive part)
Default desktop = real `<table>` with sticky header, sortable columns, row hover, bulk-select,
pagination. Responsive strategy by column count:

- **≤ 5 meaningful columns:** wrap in `overflow-x-auto` so it scrolls horizontally on small
  screens (relationships preserved). Freeze the first column where helpful.
- **> 5 columns or record-style rows (orders, products, customers):** below `md`, transform each
  row into a **stacked card** — `label: value` pairs, the primary field as the card title, status
  as a pill, and row actions in an overflow `⋯` menu. Implement as two renders: `<table className="hidden md:table">` + a `md:hidden` card list (same data source).
- **Progressive disclosure:** on the card, show 3–4 key fields; "expand" reveals the rest.
- Selectively **hide secondary columns** on mobile rather than cramming everything.
- Always provide: loading **skeleton rows**, an **empty state** (icon + message + primary CTA), and an error state.

## 5. Forms
- **One column on mobile**, multi-column (`md:grid-cols-2`) only where fields are short and related.
- Label **above** the field (not placeholder-as-label). Inline validation under the field.
- Group long forms into **sectioned cards** (e.g. Product: Basics / Pricing / Media / Stock / SEO) — optionally tabs on desktop, stacked accordions/sections on mobile (no hidden tabs that hide required fields).
- **Sticky action bar** at the bottom (Save / Cancel) so the primary action is always reachable; full-width buttons on mobile.
- File/image uploads: large drop zone, tap-to-pick on mobile, show thumbnails + progress + remove, drag-reorder for galleries.

## 6. Cards, KPIs & charts
- KPI/metric cards: `grid grid-cols-2 gap-3 md:grid-cols-4`. Prioritise the most important KPIs first — on phones the top 2 must be the ones that matter.
- Content cards: `rounded-xl border` + modest padding (`p-4 md:p-6`). No heavy shadows; flat surfaces.
- Charts: wrap in a responsive container (recharts `<ResponsiveContainer>`); reduce axis ticks/legend on small screens; ensure min-height so they don't collapse.

## 7. Overlays
- **Dialogs:** centered modal on desktop; **bottom sheet** (slide-up, rounded top) on mobile. Max-height with internal scroll; never full-bleed-cover the close button.
- **Confirmations** for destructive actions (delete, force-delete, maintenance lock).
- Toasts (sonner) top-right on desktop, top-center/bottom on mobile; auto-dismiss + manual close.

## 8. Layout safety rules
- Use `min-w-0` on flex children that contain text/tables to prevent overflow blowouts.
- Tables/figures: wrap in `overflow-x-auto`, never let them widen the page.
- Respect iOS safe areas for any fixed bottom element: `pb-[env(safe-area-inset-bottom)]`.
- Add bottom padding to scroll containers when a fixed bottom bar exists so content isn't hidden.
- Images: explicit aspect-ratio boxes + `object-contain`/`cover` deliberately (no layout shift).

## 9. Typography, color, theming
- Two font weights (400 / 500–600). Sentence case. Base body 14–16px; never below 12px.
- Brand **orange** = primary/active/focus. Neutral gray scale for surfaces/borders.
- **Full dark mode** (already wired via `next-themes`/appearance hook) — every color via tokens, test both modes.
- Consistent spacing scale (4 / 8 / 12 / 16 / 24). Border radius: `md` controls, `lg`/`xl` cards.

## 10. States & feedback (never skip)
Every async surface needs: **loading (skeleton)**, **empty**, **error**, and **success** states.
Optimistic UI for quick toggles; disable + spinner on submit; preserve scroll on save.

## 11. Performance
- Paginate/virtualize long lists; lazy-load below-the-fold and route-split heavy pages.
- Defer charts/heavy widgets; show skeletons first.
- Serve responsive images (already WebP/AVIF via R2); avoid shipping desktop-size images to phones.

## 12. Furnib component checklist (apply per page)
- [ ] Works at 360px width with no horizontal scroll (except intentional table scroll).
- [ ] Sidebar is an overlay drawer below `md`; content full-width.
- [ ] All tap targets ≥44px on touch; ≥8px apart.
- [ ] Tables → stacked cards (or horizontal scroll) on mobile.
- [ ] Forms single-column on mobile + sticky save bar.
- [ ] Loading / empty / error states present.
- [ ] Light + dark mode verified.
- [ ] Keyboard + screen-reader: focus ring, ARIA labels, Esc closes overlays.

---

## Sources
- [Best sidebar menu design examples — Navbar Gallery](https://www.navbar.gallery/blog/best-side-bar-navigation-menu-design-examples)
- [Admin dashboard UI/UX best practices 2025 — Medium](https://medium.com/@CarlosSmith24/admin-dashboard-ui-ux-best-practices-for-2025-8bdc6090c57d)
- [Side drawer UI design guide — DesignMonks](https://www.designmonks.co/blog/side-drawer-ui)
- [UI/UX in admin dashboard templates — BootstrapDash](https://www.bootstrapdash.com/blog/ui-ux-in-admin-dashboard-templates)
- [Designing user-friendly mobile data tables — Design Bootcamp](https://medium.com/design-bootcamp/designing-user-friendly-data-tables-for-mobile-devices-c470c82403ad)
- [5 practical solutions for responsive data tables — Appnroll](https://medium.com/appnroll-publication/5-practical-solutions-to-make-responsive-data-tables-ff031c48b122)
- [Understanding SC 2.5.5 Target Size — W3C WAI](https://www.w3.org/WAI/WCAG21/Understanding/target-size)
- [Accessible tap target sizes — Smashing Magazine](https://www.smashingmagazine.com/2023/04/accessible-tap-target-sizes-rage-taps-clicks/)
- [All accessible touch target sizes — LogRocket](https://blog.logrocket.com/ux-design/all-accessible-touch-target-sizes/)
