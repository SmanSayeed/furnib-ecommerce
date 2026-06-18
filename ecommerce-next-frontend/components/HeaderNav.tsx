"use client";

import Link from "next/link";
import { whatsappGeneral } from "@/lib/whatsapp";
import { openCategories } from "./CategoryDrawer";
import { ThemeToggle } from "./ThemeToggle";
import { WhatsAppIcon } from "./WhatsAppIcon";

const navLink =
  "rounded-lg px-3 py-2 text-sm font-medium text-foreground/80 transition hover:bg-surface-2 hover:text-foreground";

export function HeaderNav({ whatsapp }: { whatsapp?: string | null }) {
  return (
    <div className="flex items-center gap-1 sm:gap-2">
      {/* Desktop top nav */}
      <nav className="hidden items-center gap-1 md:flex" aria-label="Primary">
        <Link href="/" className={navLink}>
          Home
        </Link>
        <button type="button" onClick={openCategories} className={navLink}>
          Categories
        </button>
        <a
          href={whatsappGeneral(whatsapp)}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#1ebe5b]"
        >
          <WhatsAppIcon size={16} />
          WhatsApp
        </a>
      </nav>
      <ThemeToggle />
    </div>
  );
}
