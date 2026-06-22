"use client";

import { hasConsent } from "./consent";
import { pushEvent } from "./dataLayer";

/**
 * One funnel event = two synchronized sends that share an `event_id`:
 *   1. dataLayer.push  → Web GTM fires the browser tags (Meta Pixel, GA4)
 *   2. POST /api/collect → our Laravel server-side tagging server (Meta CAPI)
 * Meta de-duplicates the two by `event_id`, so each action counts ONCE while
 * still being ad-block-proof. Nothing fires until the visitor grants consent.
 */

type Item = {
  sku: string;
  qty?: number;
  /** Display value (e.g. 1999.00), already qty-adjusted by the caller. */
  value?: number;
  currency?: string;
};

function genId(prefix: string): string {
  const rand = Math.random().toString(36).slice(2, 10);
  return `${prefix}.${Date.now()}.${rand}`;
}

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));
  return match ? decodeURIComponent(match[1]) : undefined;
}

type MetaEvent = "ViewContent" | "InitiateCheckout" | "Lead";
const GA4: Record<MetaEvent, string> = {
  ViewContent: "view_item",
  InitiateCheckout: "begin_checkout",
  Lead: "generate_lead",
};

async function emit(metaEvent: MetaEvent, eventId: string, item: Item): Promise<void> {
  if (!hasConsent()) return;

  // 1) Browser tags via GTM. In the GTM GUI, set the Pixel tag's Event ID to
  //    this `event_id` data-layer variable so it de-dupes with the server copy.
  pushEvent(metaEvent, {
    event_id: eventId,
    ga4_event: GA4[metaEvent],
    ecommerce: {
      currency: item.currency ?? "BDT",
      value: item.value,
      items: [{ item_id: item.sku, quantity: item.qty ?? 1, price: item.value }],
    },
    content_type: "product",
    content_ids: [item.sku],
  });

  // 2) Server-side copy (CAPI). Same-origin proxy → Laravel. The server derives
  //    the monetary value from the catalog, so a tampered client value is moot.
  try {
    await fetch("/api/collect", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        event: metaEvent,
        event_id: eventId,
        sku: item.sku,
        qty: item.qty ?? 1,
        event_source_url: typeof location !== "undefined" ? location.href : undefined,
        fbp: readCookie("_fbp"),
        fbc: readCookie("_fbc"),
      }),
      keepalive: true,
    });
  } catch {
    // Non-fatal — a tracking hiccup must never affect the shopper.
  }
}

export function trackViewContent(item: Item): void {
  void emit("ViewContent", genId(`view.${item.sku}`), item);
}

export function trackInitiateCheckout(item: Item): void {
  void emit("InitiateCheckout", genId(`checkout.${item.sku}`), item);
}

export function trackLead(item: Item): void {
  void emit("Lead", genId(`lead.${item.sku}`), item);
}

/**
 * Purchase fires on the browser only — the server already sent the CAPI copy
 * (at COD placement and/or online-payment success) using the SAME id
 * `purchase.<order_no>`, so Meta de-duplicates. Set the GTM Pixel Purchase tag's
 * Event ID to this `event_id`.
 */
export function trackPurchase(opts: {
  orderNo: string;
  value: number;
  currency?: string;
  items: { sku: string; qty: number; price: number }[];
}): void {
  if (!hasConsent()) return;
  pushEvent("Purchase", {
    event_id: `purchase.${opts.orderNo}`,
    ga4_event: "purchase",
    ecommerce: {
      transaction_id: opts.orderNo,
      currency: opts.currency ?? "BDT",
      value: opts.value,
      items: opts.items.map((i) => ({ item_id: i.sku, quantity: i.qty, price: i.price })),
    },
    content_type: "product",
    content_ids: opts.items.map((i) => i.sku),
  });
}
