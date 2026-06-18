# Brand logo files

The header loads these by **exact filename** (theme-swapped via CSS):

| File | Used on | Source |
|------|---------|--------|
| `furnib-light.png` | **Light** theme (white bg) — orange logo | `logo/logo-Furnib-1.1-big.png` |
| `furnib-dark.png`  | **Dark** theme (dark bg) — white logo  | `logo/logo-Furnib-1.2-big.png` |

The favicon is `app/icon.png` (from `logo/Logo-furnib-favicon-1.2.png`) — Next.js
serves it automatically.

To update the brand later, replace these files with the same names (transparent
background, full lockup ~3.5:1). The component scales them with CSS (`h-8 w-auto`),
so any reasonable resolution works. SVG also works — just change the extension in
`components/Logo.tsx`.
