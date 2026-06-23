"use client";

import Link from "next/link";
import { useEffect, useMemo, useRef, useState, useSyncExternalStore } from "react";
import { trackPurchase } from "@/lib/track";
import type { PlacedOrder } from "@/lib/types";

const SERVER_SNAPSHOT = "__server__";

function readOrder(): string | null {
  return sessionStorage.getItem("furnib:order");
}

export default function SuccessPage() {
  const [paying, setPaying] = useState<string | null>(null);
  const [payError, setPayError] = useState<string | null>(null);

  // Read the placed order from sessionStorage without setState-in-effect.
  const raw = useSyncExternalStore(
    () => () => {},
    readOrder,
    () => SERVER_SNAPSHOT,
  );
  const loaded = raw !== SERVER_SNAPSHOT;
  const order = useMemo<PlacedOrder | null>(() => {
    if (!loaded || raw === null || raw === SERVER_SNAPSHOT) return null;
    try {
      return JSON.parse(raw) as PlacedOrder;
    } catch {
      return null;
    }
  }, [raw, loaded]);

  // Browser-side Purchase, sharing event_id `purchase.<order_no>` with the
  // server CAPI copy (fired at COD placement / online-payment success) so Meta
  // de-duplicates and counts the conversion exactly once.
  const purchaseTracked = useRef<string | null>(null);
  useEffect(() => {
    if (!order || purchaseTracked.current === order.order_no) return;
    purchaseTracked.current = order.order_no;
    trackPurchase({
      orderNo: order.order_no,
      value: order.total.display,
      shipping: order.shipping_cost.display,
      items: order.items.map((i) => ({
        sku: i.sku,
        name: i.title,
        qty: i.qty,
        price: i.price.display,
      })),
    });
  }, [order]);

  async function pay(type: "full" | "partial") {
    if (!order) return;
    setPaying(type);
    setPayError(null);
    try {
      const res = await fetch("/api/payment/init", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ order_no: order.order_no, type }),
      });
      const json = await res.json();
      if (res.ok && json?.gateway_url) {
        window.location.href = json.gateway_url;
        return;
      }
      setPayError(json?.error?.message ?? "Could not start the payment. Please try again.");
    } catch {
      setPayError("Network error. Please try again.");
    } finally {
      setPaying(null);
    }
  }

  if (!loaded) {
    return <div className="mx-auto max-w-lg px-4 py-16 text-center text-sm text-muted">Loading…</div>;
  }

  if (!order) {
    return (
      <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
        <h1 className="text-2xl font-bold">No recent order</h1>
        <p className="mt-3 text-sm text-muted">
          We couldn’t find a recent order in this browser. If you just paid, check your SMS for the
          confirmation.
        </p>
        <Link
          href="/"
          className="mt-6 inline-block rounded-full bg-accent px-6 py-3 text-sm font-semibold text-on-accent hover:bg-accent-hover"
        >
          Back to home
        </Link>
      </div>
    );
  }

  const hasAdvance =
    order.advance_amount.minor > 0 && order.advance_amount.minor < order.total.minor;

  return (
    <div className="mx-auto w-full max-w-lg px-4 py-8 sm:py-12">
      {/* Celebration */}
      <div className="flex flex-col items-center text-center">
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-500/15 text-3xl text-green-600">
          ✓
        </div>
        <h1 className="mt-4 text-2xl font-bold">Order placed!</h1>
        <p className="mt-1 text-sm text-muted">
          Thank you. Your order number is{" "}
          <span className="font-semibold text-foreground">{order.order_no}</span>.
        </p>
        <p className="mt-1 text-xs text-muted">We’ll contact you shortly to confirm delivery.</p>
      </div>

      {/* Items */}
      <div className="mt-6 rounded-2xl border border-border bg-surface p-4">
        <h2 className="text-sm font-semibold text-muted">Order summary</h2>
        <ul className="mt-3 space-y-3">
          {order.items.map((item, i) => (
            <li key={i} className="flex items-start justify-between gap-3 text-sm">
              <span className="min-w-0">
                <span className="block truncate font-medium">{item.title}</span>
                <span className="text-xs text-muted">
                  {item.qty} × {item.price.formatted}
                </span>
              </span>
              <span className="shrink-0 font-semibold">{item.line_total.formatted}</span>
            </li>
          ))}
        </ul>

        <div className="mt-4 space-y-1 border-t border-border pt-3 text-sm">
          <div className="flex justify-between">
            <span className="text-muted">Subtotal</span>
            <span>{order.subtotal.formatted}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted">Shipping</span>
            <span>{order.shipping_cost.minor ? order.shipping_cost.formatted : "—"}</span>
          </div>
          <div className="flex justify-between border-t border-border pt-2 text-base font-bold">
            <span>Total</span>
            <span className="text-accent">{order.total.formatted}</span>
          </div>
          {hasAdvance && (
            <div className="flex justify-between pt-1 text-xs text-muted">
              <span>Advance required</span>
              <span>{order.advance_amount.formatted}</span>
            </div>
          )}
        </div>

        <p className="mt-3 text-xs text-muted">Deliver to: {order.address}</p>
      </div>

      {payError && (
        <div className="mt-4 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-600 dark:text-red-400">
          {payError}
        </div>
      )}

      {/* Payment + invoice */}
      <div className="mt-5 space-y-3">
        <button
          type="button"
          onClick={() => pay("full")}
          disabled={paying !== null}
          className="w-full rounded-xl bg-accent px-6 py-3.5 font-semibold text-on-accent transition hover:bg-accent-hover disabled:opacity-60"
        >
          {paying === "full" ? "Starting payment…" : `Pay online — ${order.total.formatted}`}
        </button>

        {hasAdvance && (
          <button
            type="button"
            onClick={() => pay("partial")}
            disabled={paying !== null}
            className="w-full rounded-xl border border-accent px-6 py-3.5 font-semibold text-accent transition hover:bg-accent/5 disabled:opacity-60"
          >
            {paying === "partial" ? "Starting payment…" : `Pay advance — ${order.advance_amount.formatted}`}
          </button>
        )}

        <a
          href={order.invoice_url}
          target="_blank"
          rel="noopener noreferrer"
          className="block w-full rounded-xl border border-border bg-surface px-6 py-3.5 text-center font-semibold transition hover:bg-surface-2"
        >
          Download invoice (PDF)
        </a>

        <p className="text-center text-xs text-muted">
          Prefer cash on delivery? No payment needed now — we’ll collect on delivery.
        </p>

        <Link
          href="/"
          className="block pt-1 text-center text-sm font-medium text-accent hover:underline"
        >
          Continue shopping
        </Link>
      </div>
    </div>
  );
}
