"use client";

import Link from "next/link";
import { config } from "@/lib/config";
import { trackLead } from "@/lib/track";
import type { Product } from "@/lib/types";
import { whatsappInquiry } from "@/lib/whatsapp";
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
  const productUrl = categorySlug
    ? `${config.siteUrl}/category/${categorySlug}`
    : `${config.siteUrl}/product/${product.slug}`;
  const unit = product.discount_price ?? product.price;

  return (
    <div className="flex items-center justify-between gap-2">
      {/* Left cluster: price + Inquiry */}
      <div className="flex min-w-0 items-center gap-2.5">
        <div className="flex min-w-0 flex-col leading-tight">
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

        {/* Inquiry (WhatsApp) — icon-only on mobile, labelled from sm */}
        <a
          href={whatsappInquiry(product, productUrl, whatsapp)}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Inquiry on WhatsApp"
          onClick={() => trackLead({ sku: product.sku, name: product.title, price: unit.display })}
          className="flex shrink-0 items-center gap-1.5 rounded-full bg-[#25D366]/15 px-3 py-1.5 text-sm font-semibold text-[#25D366] transition hover:bg-[#25D366]/25"
        >
          <WhatsAppIcon size={16} />
          <span className="hidden sm:inline">Inquiry</span>
        </a>
      </div>

      {/* Right: Order — straight to the checkout page (no modal) */}
      <Link
        href={`/checkout/${product.slug}?qty=1`}
        className="shrink-0 rounded-full bg-accent px-5 py-2 text-sm font-semibold whitespace-nowrap text-on-accent transition hover:bg-accent-hover"
      >
        Order
      </Link>
    </div>
  );
}
