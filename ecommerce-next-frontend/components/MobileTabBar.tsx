"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { whatsappGeneral } from "@/lib/whatsapp";
import { openCategories } from "./CategoryDrawer";
import { WhatsAppIcon } from "./WhatsAppIcon";

const item =
  "flex flex-1 flex-col items-center justify-center gap-1 py-2 text-[11px] font-medium text-muted transition hover:text-foreground";

export function MobileTabBar({ whatsapp }: { whatsapp?: string | null }) {
  const [hidden, setHidden] = useState(false);
  const lastY = useRef(0);

  useEffect(() => {
    lastY.current = window.scrollY;
    const onScroll = () => {
      const y = window.scrollY;
      if (y > lastY.current && y > 80) setHidden(true);
      else if (y < lastY.current) setHidden(false);
      lastY.current = y;
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  return (
    <nav
      aria-label="Bottom navigation"
      className={`fixed inset-x-0 bottom-0 z-40 flex border-t border-border bg-background/95 backdrop-blur transition-transform duration-300 md:hidden ${
        hidden ? "translate-y-full" : "translate-y-0"
      }`}
    >
      <button type="button" onClick={openCategories} className={item}>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="3" y="3" width="7" height="7" rx="1" />
          <rect x="14" y="3" width="7" height="7" rx="1" />
          <rect x="3" y="14" width="7" height="7" rx="1" />
          <rect x="14" y="14" width="7" height="7" rx="1" />
        </svg>
        Categories
      </button>

      <Link href="/" className={item}>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 10.5 12 3l9 7.5" />
          <path d="M5 9.5V21h14V9.5" />
        </svg>
        Home
      </Link>

      <a
        href={whatsappGeneral(whatsapp)}
        target="_blank"
        rel="noopener noreferrer"
        className={`${item} text-[#25D366] hover:text-[#1ebe5b]`}
      >
        <WhatsAppIcon size={22} />
        WhatsApp
      </a>
    </nav>
  );
}
