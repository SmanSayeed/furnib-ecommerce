"use client";

import Link from "next/link";
import { config } from "@/lib/config";
import { trackInitiateCheckout, trackLead } from "@/lib/track";
import type { Product } from "@/lib/types";
import { whatsappInquiry } from "@/lib/whatsapp";
import { WhatsAppIcon } from "./WhatsAppIcon";

export function ProductActions({
  product,
  categorySlug,
  whatsapp,
  inquiryEnabled = true,
}: {
  product: Product;
  categorySlug?: string;
  whatsapp?: string | null;
  inquiryEnabled?: boolean;
}) {
  const productUrl = categorySlug
    ? `${config.siteUrl}/category/${categorySlug}`
    : `${config.siteUrl}/product/${product.slug}`;
  const unit = product.discount_price ?? product.price;

  const discountPct =
    product.discount_price && product.price.display > 0
      ? Math.round((1 - product.discount_price.display / product.price.display) * 100)
      : 0;

  const inquiryHref = whatsappInquiry(product, productUrl, whatsapp);
  const onInquiry = () =>
    trackLead({ sku: product.sku, name: product.title, price: unit.display });

  // "Order now" is our begin_checkout signal — fires before the click navigates
  // to the checkout page (keepalive sends keep the beacon alive across the nav).
  const onOrder = () =>
    trackInitiateCheckout({
      sku: product.sku,
      name: product.title,
      price: unit.display,
      qty: 1,
    });

  return (
    <div className="flex flex-wrap items-center justify-between gap-y-3 gap-x-4">
      {/* Price — discounted (big, accent) on top; the struck original + discount
          (e.g. "92% off") sit on the line just below it. Amounts are already
          rounded to whole taka (no decimals) server-side. */}
      <div className="flex min-w-0 flex-col leading-tight">
        <span className="text-lg font-extrabold text-accent sm:text-xl">
          {unit.formatted}
        </span>
        {product.discount_price && (
          <span className="mt-0.5 flex items-center gap-2 text-xs">
            <span className="text-muted line-through">{product.price.formatted}</span>
            {discountPct > 0 && (
              <span className="font-semibold text-muted">{discountPct}% off</span>
            )}
          </span>
        )}
      </div>

      {/* Actions — Inquiry sits immediately left of Order now with a 10px gap,
          both a touch larger, white text in light and dark mode. */}
      <div className="flex shrink-0 items-center gap-2.5">
        {inquiryEnabled && (
          <a
            href={inquiryHref}
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Inquiry on WhatsApp"
            onClick={onInquiry}
            style={{ color: "#ffffff" }}
            className="flex items-center gap-1.5 rounded-full bg-[#25D366] px-5 py-2.5 text-[15px] font-bold whitespace-nowrap text-white transition hover:brightness-110"
          >
            <WhatsAppIcon size={18} />
            <span>Inquiry</span>
          </a>
        )}

        <Link
          href={`/checkout/${product.slug}?qty=1`}
          onClick={onOrder}
          style={{ color: "#ffffff" }}
          className="rounded-full bg-accent px-6 py-2.5 text-[15px] font-bold whitespace-nowrap text-white transition hover:bg-accent-hover"
        >
          Order now
        </Link>
      </div>
    </div>
  );
}
