"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { config } from "@/lib/config";
import { imageUrl } from "@/lib/image";
import { trackLead } from "@/lib/track";
import type { Product } from "@/lib/types";
import { whatsappInquiry } from "@/lib/whatsapp";
import { SafeImage } from "./SafeImage";
import { WhatsAppIcon } from "./WhatsAppIcon";

export function ProductActions({
  product,
  categorySlug,
  whatsapp,
}: {
  product: Product;
  categorySlug?: string;
  whatsapp?: string | null;
}) {
  const [open, setOpen] = useState(false);
  const [qty, setQty] = useState(1);
  const productUrl = categorySlug
    ? `${config.siteUrl}/category/${categorySlug}`
    : `${config.siteUrl}/product/${product.slug}`;

  // Lock background scroll while the order modal is open.
  useEffect(() => {
    if (!open) return;
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.body.style.overflow = previous;
    };
  }, [open]);
  const unit = product.discount_price ?? product.price;
  const total = (unit.minor * qty) / 100;

  return (
    <>
      <div className="flex items-stretch gap-2">
        {/* Price (currency icon only, no label) */}
        <div className="flex min-w-0 flex-1 flex-col justify-center rounded-xl border border-border bg-surface px-3 py-2 leading-tight">
          {product.discount_price ? (
            <>
              <span className="truncate text-base font-extrabold text-accent sm:text-lg">
                {product.discount_price.formatted}
              </span>
              <span className="truncate text-xs text-muted line-through">
                {product.price.formatted}
              </span>
            </>
          ) : (
            <span className="truncate text-base font-extrabold sm:text-lg">
              {product.price.formatted}
            </span>
          )}
        </div>

        {/* Inquiry (WhatsApp green) */}
        <a
          href={whatsappInquiry(product, productUrl, whatsapp)}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Inquiry on WhatsApp"
          onClick={() => trackLead({ sku: product.sku, value: unit.display })}
          className="flex shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#25D366] px-3 text-sm font-semibold text-white transition hover:bg-[#1ebe5b] sm:gap-2 sm:px-4 sm:text-base"
        >
          <WhatsAppIcon size={18} />
          <span>Inquiry</span>
        </a>

        {/* Order Now (primary) */}
        <button
          type="button"
          onClick={() => setOpen(true)}
          className="flex flex-1 items-center justify-center rounded-xl bg-accent px-3 text-sm font-semibold whitespace-nowrap text-on-accent transition hover:bg-accent-hover sm:text-base"
        >
          Order Now
        </button>
      </div>

      {open && (
        <div
          className="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
          role="dialog"
          aria-modal="true"
        >
          <div className="absolute inset-0 bg-black/60" onClick={() => setOpen(false)} />
          <div className="relative z-10 w-full max-w-md animate-in rounded-t-2xl border border-border bg-surface p-6 sm:rounded-2xl">
            <div className="flex items-start gap-4">
              <div className="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-border">
                <SafeImage
                  src={imageUrl(product.main_image)}
                  alt={product.title}
                  className="h-full w-full object-cover"
                />
              </div>
              <div className="min-w-0">
                <h3 className="truncate font-semibold">{product.title}</h3>
                <p className="text-xs text-muted">SKU: {product.sku}</p>
                <p className="mt-1 text-sm font-medium text-accent">{unit.formatted}</p>
              </div>
              <button
                type="button"
                onClick={() => setOpen(false)}
                aria-label="Close"
                className="ml-auto text-muted hover:text-foreground"
              >
                ✕
              </button>
            </div>

            <div className="mt-5 flex items-center justify-between">
              <span className="text-sm text-muted">Quantity</span>
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={() => setQty((q) => Math.max(1, q - 1))}
                  className="h-9 w-9 rounded-full border border-border text-lg hover:bg-surface-2"
                >
                  −
                </button>
                <span className="w-8 text-center font-semibold">{qty}</span>
                <button
                  type="button"
                  onClick={() => setQty((q) => q + 1)}
                  className="h-9 w-9 rounded-full border border-border text-lg hover:bg-surface-2"
                >
                  +
                </button>
              </div>
            </div>

            <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
              <span className="text-sm text-muted">Total</span>
              <span className="text-lg font-bold">
                ৳{total.toLocaleString("en-US", { minimumFractionDigits: 2 })}
              </span>
            </div>

            <div className="mt-5">
              <Link
                href={`/checkout/${product.slug}?qty=${qty}`}
                className="block rounded-xl bg-accent px-4 py-3 text-center font-semibold text-on-accent transition hover:bg-accent-hover"
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
