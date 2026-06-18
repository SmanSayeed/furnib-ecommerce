"use client";

import Link from "next/link";
import { useState } from "react";
import type { Category } from "@/lib/types";
import { whatsappGeneral } from "@/lib/whatsapp";

export function FloatingNav({ categories }: { categories: Category[] }) {
  const [open, setOpen] = useState(false);

  return (
    <>
      {/* Floating menu (bottom-left) */}
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="Open categories menu"
        className="fixed bottom-5 left-5 z-40 flex h-14 w-14 items-center justify-center rounded-full border border-border bg-surface-2 text-foreground shadow-lg transition hover:bg-surface"
      >
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <line x1="3" y1="6" x2="21" y2="6" />
          <line x1="3" y1="12" x2="21" y2="12" />
          <line x1="3" y1="18" x2="21" y2="18" />
        </svg>
      </button>

      {/* Floating WhatsApp (bottom-right) */}
      <a
        href={whatsappGeneral()}
        target="_blank"
        rel="noopener noreferrer"
        aria-label="Chat on WhatsApp"
        className="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-accent text-white shadow-lg shadow-accent/30 transition hover:bg-accent-hover"
      >
        <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
          <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.12c-.25.7-1.44 1.33-2 1.42-.51.08-1.16.11-1.87-.12-.43-.14-.98-.32-1.69-.62-2.97-1.28-4.91-4.27-5.06-4.47-.15-.2-1.2-1.6-1.2-3.06 0-1.45.76-2.16 1.03-2.46.27-.3.59-.37.79-.37l.57.01c.18.01.43-.07.67.51.25.6.84 2.07.91 2.22.07.15.12.32.02.52-.1.2-.15.32-.3.5-.15.17-.31.39-.45.52-.15.15-.3.31-.13.6.17.3.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.36 1.45.3.15.47.12.64-.07.17-.2.74-.86.94-1.16.2-.3.39-.25.66-.15.27.1 1.71.81 2 .96.3.15.5.22.57.35.07.12.07.72-.18 1.42Z" />
        </svg>
      </a>

      {/* Category drawer */}
      {open && (
        <div className="fixed inset-0 z-50" role="dialog" aria-modal="true">
          <div
            className="absolute inset-0 bg-black/60"
            onClick={() => setOpen(false)}
          />
          <aside className="absolute left-0 top-0 h-full w-72 max-w-[80%] animate-in border-r border-border bg-surface p-6 overflow-y-auto">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold">Collections</h3>
              <button
                type="button"
                onClick={() => setOpen(false)}
                aria-label="Close menu"
                className="text-muted hover:text-foreground"
              >
                ✕
              </button>
            </div>
            <nav className="mt-6 flex flex-col gap-1">
              <Link
                href="/"
                onClick={() => setOpen(false)}
                className="rounded-lg px-3 py-2.5 text-sm hover:bg-surface-2"
              >
                Home
              </Link>
              {categories.map((c) => (
                <Link
                  key={c.id}
                  href={`/category/${c.slug}`}
                  onClick={() => setOpen(false)}
                  className="rounded-lg px-3 py-2.5 text-sm hover:bg-surface-2"
                >
                  {c.title}
                </Link>
              ))}
              {categories.length === 0 && (
                <p className="px-3 py-2 text-sm text-muted">No categories yet.</p>
              )}
            </nav>
          </aside>
        </div>
      )}
    </>
  );
}
