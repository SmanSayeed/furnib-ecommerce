import { config } from "./config";
import type { Product } from "./types";

/**
 * WhatsApp needs an international number (country code, no leading 0, digits
 * only) — a local BD number like "01748870651" resolves to "chat not found".
 * Normalize any stored form (0…, +880…, 880…, or bare 1…) to 880XXXXXXXXXX.
 */
export function normalizeWhatsappNumber(raw: string): string {
  const digits = raw.replace(/\D/g, "");
  if (digits === "") return "";
  if (digits.startsWith("880")) return digits;
  if (digits.startsWith("0")) return `880${digits.slice(1)}`;
  if (digits.length === 10 && digits.startsWith("1")) return `880${digits}`;
  return digits; // already international (some other country code)
}

function link(message: string, number?: string | null): string {
  const raw = number && number.trim() !== "" ? number : config.whatsapp;
  const to = normalizeWhatsappNumber(raw ?? "");
  return `https://wa.me/${to}?text=${encodeURIComponent(message)}`;
}

export function whatsappGeneral(number?: string | null): string {
  return link(
    `Hello ${config.siteName}, I'd like to know more about your furniture.`,
    number,
  );
}

export function whatsappInquiry(
  product: Product,
  number?: string | null,
): string {
  const price = product.discount_price ?? product.price;
  // WhatsApp renders a link PREVIEW (thumbnail + title) by crawling the OG tags
  // of the FIRST URL in the text. A bare image-file URL has no OG tags, so it
  // showed nothing — the old bug. Send the product PAGE url instead and let its
  // OG image (see product/[slug] generateMetadata) drive the thumbnail.
  const url = `${config.siteUrl}/product/${product.slug}`;
  const lines = [
    `*Inquiry — ${product.title}*`,
    `SKU: ${product.sku}`,
    `Price: ${price.formatted}`,
    `\n${url}`,
  ];
  return link(lines.join("\n"), number);
}

export function whatsappOrder(
  product: Product,
  qty: number,
  productUrl: string,
  number?: string | null,
): string {
  const unit = product.discount_price ?? product.price;
  const total = Math.round((unit.minor * qty) / 100);
  const lines = [
    `*Order — ${config.siteName}*`,
    `Product: ${product.title}`,
    `SKU: ${product.sku}`,
    `Quantity: ${qty}`,
    `Unit price: ${unit.formatted}`,
    `Total: ৳${total.toLocaleString("en-US")}`,
    `\n${productUrl}`,
  ];
  return link(lines.join("\n"), number);
}
