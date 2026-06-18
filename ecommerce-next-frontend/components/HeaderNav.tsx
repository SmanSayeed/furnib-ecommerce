"use client";

import Link from "next/link";
import { ThemeToggle } from "./ThemeToggle";

const navLink =
  "rounded-lg px-3 py-2 text-sm font-medium text-foreground/80 transition hover:bg-surface-2 hover:text-foreground";

export function HeaderNav() {
  return (
    <div className="flex items-center gap-1 sm:gap-2">
      {/* Desktop top nav (Categories & WhatsApp live in the floating buttons) */}
      <nav className="hidden items-center gap-1 md:flex" aria-label="Primary">
        <Link href="/" className={navLink}>
          Home
        </Link>
      </nav>
      <ThemeToggle />
    </div>
  );
}
