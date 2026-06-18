import { config } from "./config";
import type { Product } from "./types";

function link(message: string, number?: string | null): string {
  const to = number && number.trim() !== "" ? number : config.whatsapp;
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
  productUrl: string,
  number?: string | null,
): string {
  const price = product.discount_price ?? product.price;
  const lines = [
    `*Inquiry — ${product.title}*`,
    `SKU: ${product.sku}`,
    `Price: ${price.formatted}`,
    product.details ? `\n${product.details}` : "",
    `\n${productUrl}`,
  ].filter(Boolean);
  return link(lines.join("\n"), number);
}

export function whatsappOrder(
  product: Product,
  qty: number,
  productUrl: string,
  number?: string | null,
): string {
  const unit = product.discount_price ?? product.price;
  const total = (unit.minor * qty) / 100;
  const lines = [
    `*Order — ${config.siteName}*`,
    `Product: ${product.title}`,
    `SKU: ${product.sku}`,
    `Quantity: ${qty}`,
    `Unit price: ${unit.formatted}`,
    `Total: ৳${total.toLocaleString("en-US", { minimumFractionDigits: 2 })}`,
    `\n${productUrl}`,
  ];
  return link(lines.join("\n"), number);
}
