"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { Category } from "@/lib/types";

export const OPEN_CATEGORIES_EVENT = "furnib:open-categories";

/** Opens the category drawer from anywhere (header / bottom bar). */
export function openCategories() {
  window.dispatchEvent(new Event(OPEN_CATEGORIES_EVENT));
}

export function CategoryDrawer({ categories }: { categories: Category[] }) {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const handler = () => setOpen(true);
    window.addEventListener(OPEN_CATEGORIES_EVENT, handler);
    return () => window.removeEventListener(OPEN_CATEGORIES_EVENT, handler);
  }, []);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50" role="dialog" aria-modal="true">
      <div className="absolute inset-0 bg-black/60" onClick={() => setOpen(false)} />
      <aside className="absolute left-0 top-0 h-full w-72 max-w-[80%] animate-in overflow-y-auto border-r border-border bg-surface p-6">
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
  );
}
