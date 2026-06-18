import { config } from "./config";
import type { Product } from "./types";

function link(message: string): string {
  return `https://wa.me/${config.whatsapp}?text=${encodeURIComponent(message)}`;
}

export function whatsappGeneral(): string {
  return link(`Hello ${config.siteName}, I'd like to know more about your furniture.`);
}

export function whatsappInquiry(product: Product, productUrl: string): string {
  const price = product.discount_price ?? product.price;
  const lines = [
    `*Inquiry — ${product.title}*`,
    `SKU: ${product.sku}`,
    `Price: ${price.formatted}`,
    product.details ? `\n${product.details}` : "",
    `\n${productUrl}`,
  ].filter(Boolean);
  return link(lines.join("\n"));
}

export function whatsappOrder(product: Product, qty: number, productUrl: string): string {
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
  return link(lines.join("\n"));
}
