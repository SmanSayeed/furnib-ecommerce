"use client";

import { clearEcommerce, pushEvent } from "./dataLayer";
import type { OrderTracking } from "./types";

/**
 * One funnel action = two synchronized sends that share an `event_id`:
 *   1. dataLayer.push  → Web GTM fires the browser tags (GA4 + Meta Pixel)
 *   2. POST /api/collect → our Laravel server-side tagging server (Meta CAPI)
 * Meta de-duplicates the two by `event_id`, so each action counts ONCE while
 * staying ad-block-proof.
 *
 * The dataLayer push follows the GA4 ecommerce spec (canonical event name +
 * `ecommerce` object, with the previous object cleared first). The Meta-only
 * fields (`event_id`, `content_ids`, `content_type`) ride along on the same push
 * so a single GTM trigger can drive both the GA4 and the Meta Pixel tag.
 * @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce
 */

type Item = {
  sku: string;
  name?: string;
  /** Unit price in display units (e.g. 1999.00). */
  price?: number;
  qty?: number;
  currency?: string;
};

/** Maps our funnel actions to the GA4 event name and the Meta Pixel event name. */
const EVENTS = {
  view: { ga4: "view_item", meta: "ViewContent" },
  checkout: { ga4: "begin_checkout", meta: "InitiateCheckout" },
  lead: { ga4: "generate_lead", meta: "Lead" },
} as const;

type Action = keyof typeof EVENTS;

function genId(prefix: string): string {
  const rand = Math.random().toString(36).slice(2, 10);
  return `${prefix}.${Date.now()}.${rand}`;
}

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${name}=([^;]+)`));
  return match ? decodeURIComponent(match[1]) : undefined;
}

/** A single GA4-spec ecommerce item. Omits empty optional fields. */
function ga4Item(item: Item, quantity: number) {
  return {
    item_id: item.sku,
    ...(item.name ? { item_name: item.name } : {}),
    ...(item.price !== undefined ? { price: item.price } : {}),
    quantity,
  };
}

async function emit(action: Action, eventId: string, item: Item): Promise<void> {
  const { ga4, meta } = EVENTS[action];
  const qty = item.qty ?? 1;
  const value = item.price !== undefined ? item.price * qty : undefined;

  // 1) Browser tags via GTM. GA4 reads `ecommerce`; the Meta Pixel tag (same
  //    trigger) reads content_ids/value and MUST use Event ID = {{event_id}}.
  clearEcommerce();
  pushEvent(ga4, {
    event_id: eventId,
    meta_event: meta,
    ecommerce: {
      currency: item.currency ?? "BDT",
      value,
      items: [ga4Item(item, qty)],
    },
    content_type: "product",
    content_ids: [item.sku],
  });

  // 2) Server-side copy (CAPI). Same-origin proxy → Laravel, which derives the
  //    authoritative value from the catalog (a tampered client value is moot).
  try {
    await fetch("/api/collect", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        event: meta,
        event_id: eventId,
        sku: item.sku,
        qty,
        event_source_url: typeof location !== "undefined" ? location.href : undefined,
        fbp: readCookie("_fbp"),
        fbc: readCookie("_fbc"),
        ttp: readCookie("_ttp"),
        ttclid: readCookie("ttclid"),
      }),
      keepalive: true,
    });
  } catch {
    // Non-fatal — a tracking hiccup must never affect the shopper.
  }
}

export function trackViewContent(item: Item): void {
  void emit("view", genId(`view.${item.sku}`), item);
}

export function trackInitiateCheckout(item: Item): void {
  void emit("checkout", genId(`checkout.${item.sku}`), item);
}

export function trackLead(item: Item): void {
  void emit("lead", genId(`lead.${item.sku}`), item);
}

/**
 * Category card click. A category view has no Meta product event, so this is a
 * dataLayer-only signal — the marketer wires whatever GTM tag they want to it.
 */
export function trackViewCategory(c: {
  id: number;
  name: string;
  slug: string;
}): void {
  pushEvent("view_category", {
    category_id: c.id,
    category_name: c.name,
    category_slug: c.slug,
  });
}

/**
 * `purchase` — fired when the order is placed (checkout HTTP 201). The rich
 * payload (ecommerce + raw/hashed user_data + fbp/fbc/client_ip + order_info) is
 * built server-side by Laravel and pushed verbatim; no PII handling happens in
 * JS. This browser push and the server-side Meta CAPI / TikTok / GA4 copy (fired
 * by Laravel at the same moment) share the `purchase.<order_no>` event_id, so
 * Meta de-duplicates the two into one counted sale. This is the sole purchase
 * conversion point — admin status changes fire nothing.
 */
export function trackPurchase(tracking: OrderTracking): void {
  const { event, ...rest } = tracking;
  clearEcommerce();
  pushEvent(event, rest);
}
