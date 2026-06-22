"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { Category } from "@/lib/types";
import { Logo } from "./Logo";

export const OPEN_CATEGORIES_EVENT = "furnib:open-categories";

/** Opens the category drawer from anywhere (header / bottom bar). */
export function openCategories() {
  window.dispatchEvent(new Event(OPEN_CATEGORIES_EVENT));
}

export function CategoryDrawer({
  categories,
  logoLight,
  logoDark,
}: {
  categories: Category[];
  logoLight?: string | null;
  logoDark?: string | null;
}) {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const handler = () => setOpen(true);
    window.addEventListener(OPEN_CATEGORIES_EVENT, handler);
    return () => window.removeEventListener(OPEN_CATEGORIES_EVENT, handler);
  }, []);

  if (!open) return null;

  const linkClass =
    "rounded-card px-4 py-3.5 text-lg font-medium text-foreground transition hover:bg-surface-2 hover:text-accent";

  return (
    <div className="fixed inset-0 z-50" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/60" onClick={() => setOpen(false)} />
      <aside className="absolute left-0 top-0 flex h-full w-80 max-w-[85%] animate-in flex-col overflow-y-auto border-r border-border bg-surface p-6">
        {/* Brand + close */}
        <div className="flex items-center justify-between border-b border-border pb-5">
          <Logo
            className="h-9 w-auto"
            lightUrl={logoLight}
            darkUrl={logoDark}
            onClick={() => setOpen(false)}
          />
          <button
            type="button"
            onClick={() => setOpen(false)}
            aria-label="Close menu"
            className="flex h-9 w-9 items-center justify-center rounded-full text-2xl text-muted transition hover:bg-surface-2 hover:text-foreground"
          >
            ✕
          </button>
        </div>

        <p className="mt-6 text-xs font-semibold uppercase tracking-wider text-muted">
          Collections
        </p>

        <nav className="mt-3 flex flex-col gap-1.5">
          <Link href="/" onClick={() => setOpen(false)} className={linkClass}>
            Home
          </Link>
          {categories.map((c) => (
            <Link
              key={c.id}
              href={`/category/${c.slug}`}
              onClick={() => setOpen(false)}
              className={linkClass}
            >
              {c.title}
            </Link>
          ))}
          {categories.length === 0 && (
            <p className="px-4 py-3 text-base text-muted">No categories yet.</p>
          )}
        </nav>
      </aside>
    </div>
  );
}
