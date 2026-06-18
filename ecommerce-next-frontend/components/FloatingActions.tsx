"use client";

import { whatsappGeneral } from "@/lib/whatsapp";
import { openCategories } from "./CategoryDrawer";
import { WhatsAppIcon } from "./WhatsAppIcon";

/**
 * Desktop-only floating action buttons:
 *   - bottom-left: open the categories drawer
 *   - bottom-right: WhatsApp chat
 * Hidden on mobile (the bottom tab bar covers those there).
 */
export function FloatingActions({ whatsapp }: { whatsapp?: string | null }) {
  return (
    <>
      <button
        type="button"
        onClick={openCategories}
        aria-label="Open categories menu"
        className="fixed bottom-5 left-5 z-40 hidden h-14 w-14 items-center justify-center rounded-full border border-border bg-surface-2 text-foreground shadow-lg transition hover:bg-surface md:flex"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>

      <a
        href={whatsappGeneral(whatsapp)}
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Chat on WhatsApp"
        className="fixed bottom-5 right-5 z-40 hidden h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/20 transition hover:bg-[#1ebe5b] md:flex"
      >
        <WhatsAppIcon size={26} />
      </a>
    </>
  );
}
