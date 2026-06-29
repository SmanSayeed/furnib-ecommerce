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
}: {
  product: Product;
  categorySlug?: string;
  whatsapp?: string | null;
}) {
  const productUrl = categorySlug
    ? `${config.siteUrl}/category/${categorySlug}`
    : `${config.siteUrl}/product/${product.slug}`;
  const unit = product.discount_price ?? product.price;

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
    <div className="flex items-center justify-between gap-2">
      {/* Left cluster: price + (desktop-only) Inquiry */}
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

        {/* Desktop Inquiry — solid WhatsApp green, white text (hidden on mobile) */}
        <a
          href={inquiryHref}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Inquiry on WhatsApp"
          onClick={onInquiry}
          className="hidden shrink-0 items-center gap-1.5 rounded-full bg-[#25D366] px-4 py-2 text-sm font-bold whitespace-nowrap text-white transition hover:brightness-110 sm:flex"
        >
          <WhatsAppIcon size={16} />
          <span>Inquiry</span>
        </a>
      </div>

      {/* Right cluster: on mobile a solid green Inquiry sits beside Order;
          on desktop only Order shows here (Inquiry lives on the left). */}
      <div className="flex shrink-0 items-center gap-2">
        {/* Mobile Inquiry — same pill shape as Order, WhatsApp green (hidden from sm) */}
        <a
          href={inquiryHref}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Inquiry on WhatsApp"
          onClick={onInquiry}
          className="flex items-center gap-1.5 rounded-full bg-[#25D366] px-4 py-2 text-sm font-bold whitespace-nowrap text-white transition hover:brightness-110 sm:hidden"
        >
          <WhatsAppIcon size={16} />
          <span>Inquiry</span>
        </a>

        {/* Order — straight to the checkout page (no modal) */}
        <Link
          href={`/checkout/${product.slug}?qty=1`}
          onClick={onOrder}
          className="rounded-full bg-accent px-5 py-2 text-sm font-bold whitespace-nowrap text-white transition hover:bg-accent-hover"
        >
          Order now
        </Link>
      </div>
    </div>
  );
}
