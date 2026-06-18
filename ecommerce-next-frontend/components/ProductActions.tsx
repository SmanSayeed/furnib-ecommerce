"use client";

import Link from "next/link";
import { useState } from "react";
import { config } from "@/lib/config";
import { imageUrl } from "@/lib/image";
import type { Product } from "@/lib/types";
import { whatsappInquiry, whatsappOrder } from "@/lib/whatsapp";
import { SafeImage } from "./SafeImage";

export function ProductActions({ product }: { product: Product }) {
  const [open, setOpen] = useState(false);
  const [qty, setQty] = useState(1);
  const productUrl = `${config.siteUrl}/product/${product.slug}`;
  const unit = product.discount_price ?? product.price;
  const total = (unit.minor * qty) / 100;

  return (
    <>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div className="flex flex-col items-center justify-center rounded-xl border border-border bg-surface px-4 py-3 text-center">
          <span className="text-[11px] uppercase tracking-wider text-muted">Price</span>
          {product.discount_price ? (
            <span>
              <span className="text-lg font-bold text-accent">
                {product.discount_price.formatted}
              </span>
              <span className="ml-2 text-sm text-muted line-through">
                {product.price.formatted}
              </span>
            </span>
          ) : (
            <span className="text-lg font-bold">{product.price.formatted}</span>
          )}
        </div>

        <a
          href={whatsappInquiry(product, productUrl)}
          target="_blank"
          rel="noopener noreferrer"
          className="flex items-center justify-center gap-2 rounded-xl border border-border bg-surface px-4 py-3 font-semibold transition hover:bg-surface-2"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" className="text-accent">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Z" />
          </svg>
          Inquiry
        </a>

        <button
          type="button"
          onClick={() => setOpen(true)}
          className="rounded-xl bg-accent px-4 py-3 font-semibold text-white transition hover:bg-accent-hover"
        >
          Order Now
        </button>
      </div>

      {open && (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true">
          <div className="absolute inset-0 bg-black/60" onClick={() => setOpen(false)} />
          <div className="relative z-10 w-full max-w-md animate-in rounded-t-2xl border border-border bg-surface p-6 sm:rounded-2xl">
            <div className="flex items-start gap-4">
              <div className="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-border">
                <SafeImage src={imageUrl(product.main_image)} alt={product.title} className="h-full w-full object-cover" />
              </div>
              <div className="min-w-0">
                <h3 className="truncate font-semibold">{product.title}</h3>
                <p className="text-xs text-muted">SKU: {product.sku}</p>
                <p className="mt-1 text-sm font-medium text-accent">{unit.formatted}</p>
              </div>
              <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="ml-auto text-muted hover:text-foreground">✕</button>
            </div>

            <div className="mt-5 flex items-center justify-between">
              <span className="text-sm text-muted">Quantity</span>
              <div className="flex items-center gap-3">
                <button type="button" onClick={() => setQty((q) => Math.max(1, q - 1))} className="h-9 w-9 rounded-full border border-border text-lg hover:bg-surface-2">−</button>
                <span className="w-8 text-center font-semibold">{qty}</span>
                <button type="button" onClick={() => setQty((q) => q + 1)} className="h-9 w-9 rounded-full border border-border text-lg hover:bg-surface-2">+</button>
              </div>
            </div>

            <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
              <span className="text-sm text-muted">Total</span>
              <span className="text-lg font-bold">
                ৳{total.toLocaleString("en-US", { minimumFractionDigits: 2 })}
              </span>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
              <a
                href={whatsappOrder(product, qty, productUrl)}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-xl bg-accent px-4 py-3 text-center font-semibold text-white transition hover:bg-accent-hover"
              >
                Order on WhatsApp
              </a>
              <Link
                href={`/checkout/${product.slug}?qty=${qty}`}
                className="rounded-xl border border-border bg-surface px-4 py-3 text-center font-semibold transition hover:bg-surface-2"
              >
                Order on Web
              </Link>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
