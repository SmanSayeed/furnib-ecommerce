"use client";

import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

type PaySummary = {
  order_no: string;
  status: string;
  payment_status: string;
  customer_name: string | null;
  address: string;
  items: { title: string; qty: number; price: string; line_total: string }[];
  subtotal: string;
  shipping: string;
  total: string;
  advance_paid: string;
  due: string;
  can_pay_shipping: boolean;
  can_pay_full: boolean;
  payments: {
    type: string;
    status: string;
    amount: string;
    date: string | null;
  }[];
};

export default function PayPage() {
  const params = useParams();
  const search = useSearchParams();
  const order = String(params?.order ?? "");
  const token = search.get("t") ?? "";

  const [data, setData] = useState<PaySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [paying, setPaying] = useState<string | null>(null);

  useEffect(() => {
    let alive = true;
    // All state updates live inside the async body — never synchronously in the
    // effect, which would trigger cascading renders.
    (async () => {
      if (order === "" || token === "") {
        if (alive) {
          setError("This payment link is invalid.");
          setLoading(false);
        }
        return;
      }
      try {
        const res = await fetch(
          `/api/pay-summary?order=${encodeURIComponent(order)}&t=${encodeURIComponent(token)}`,
          { cache: "no-store" },
        );
        const json = await res.json();
        if (!alive) return;
        if (res.ok && json?.data) setData(json.data as PaySummary);
        else setError("This payment link is invalid or has expired.");
      } catch {
        if (alive) setError("Network error. Please try again.");
      } finally {
        if (alive) setLoading(false);
      }
    })();
    return () => {
      alive = false;
    };
  }, [order, token]);

  async function pay(type: "shipping" | "full") {
    setPaying(type);
    setError(null);
    try {
      const res = await fetch("/api/payment/init", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ order_no: order, type }),
      });
      const json = await res.json();
      if (res.ok && json?.gateway_url) {
        window.location.href = json.gateway_url;
        return;
      }
      setError(json?.error?.message ?? "Could not start the payment. Please try again.");
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setPaying(null);
    }
  }

  if (loading) {
    return <div className="mx-auto max-w-lg px-4 py-16 text-center text-sm text-muted">Loading…</div>;
  }

  if (error && data === null) {
    return (
      <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
        <h1 className="text-2xl font-bold">Payment link</h1>
        <p className="mt-3 text-sm text-muted">{error}</p>
        <Link
          href="/"
          className="mt-6 inline-block rounded-full bg-accent px-6 py-3 text-sm font-semibold text-on-accent hover:bg-accent-hover"
        >
          Back to home
        </Link>
      </div>
    );
  }

  if (data === null) return null;

  const nothingDue = !data.can_pay_full && !data.can_pay_shipping;

  return (
    <div className="mx-auto w-full max-w-lg px-4 py-8 sm:py-12">
      <div className="text-center">
        <h1 className="text-2xl font-bold">Payment</h1>
        <p className="mt-1 text-sm text-muted">
          Order <span className="font-semibold text-foreground">{data.order_no}</span>
        </p>
      </div>

      {/* Order summary */}
      <div className="mt-6 rounded-2xl border border-border bg-surface p-4">
        <h2 className="text-sm font-semibold text-muted">Order summary</h2>
        <ul className="mt-3 space-y-3">
          {data.items.map((item, i) => (
            <li key={i} className="flex items-start justify-between gap-3 text-sm">
              <span className="min-w-0">
                <span className="block truncate font-medium">{item.title}</span>
                <span className="text-xs text-muted">
                  {item.qty} × {item.price}
                </span>
              </span>
              <span className="shrink-0 font-semibold">{item.line_total}</span>
            </li>
          ))}
        </ul>

        <div className="mt-4 space-y-1 border-t border-border pt-3 text-sm">
          <div className="flex justify-between">
            <span className="text-muted">Subtotal</span>
            <span>{data.subtotal}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-muted">Shipping</span>
            <span>{data.shipping}</span>
          </div>
          <div className="flex justify-between border-t border-border pt-2 text-base font-bold">
            <span>Total</span>
            <span className="text-accent">{data.total}</span>
          </div>
          {data.advance_paid !== data.subtotal && data.payment_status !== "unpaid" && (
            <div className="flex justify-between pt-1 text-xs text-green-600 dark:text-green-400">
              <span>Already paid</span>
              <span className="font-semibold">{data.advance_paid}</span>
            </div>
          )}
          <div className="flex justify-between text-sm font-semibold">
            <span>Amount due</span>
            <span>{data.due}</span>
          </div>
        </div>

        <p className="mt-3 text-xs text-muted">Deliver to: {data.address}</p>
      </div>

      {data.payments.length > 0 && (
        <div className="mt-4 rounded-2xl border border-border bg-surface p-4">
          <h2 className="text-sm font-semibold text-muted">Payment history</h2>
          <ul className="mt-3 space-y-2">
            {data.payments.map((p, i) => (
              <li key={i} className="flex items-center justify-between gap-3 text-sm">
                <span className="min-w-0">
                  <span className="block font-medium capitalize">
                    {p.type === "shipping" ? "Delivery charge" : p.type} payment
                  </span>
                  <span className="text-xs text-muted">
                    {p.date ?? ""}
                    {p.status === "pending" ? " · pending" : ""}
                  </span>
                </span>
                <span
                  className={`shrink-0 font-semibold ${
                    p.status === "pending" ? "text-amber-600 dark:text-amber-400" : ""
                  }`}
                >
                  {p.amount}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}

      {error && (
        <div className="mt-4 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-600 dark:text-red-400">
          {error}
        </div>
      )}

      {/* Payment options */}
      <div className="mt-5 space-y-3">
        {nothingDue ? (
          <p className="rounded-xl border border-green-500/40 bg-green-500/10 px-4 py-3 text-center text-sm text-green-700 dark:text-green-400">
            This order is fully paid. Thank you!
          </p>
        ) : (
          <>
            {data.can_pay_shipping && (
              <button
                type="button"
                onClick={() => pay("shipping")}
                disabled={paying !== null}
                className="w-full rounded-xl border border-accent bg-surface px-6 py-3.5 font-semibold text-accent transition hover:bg-surface-2 disabled:opacity-60"
              >
                {paying === "shipping" ? "Starting payment…" : `Pay delivery charge — ${data.shipping}`}
              </button>
            )}
            {data.can_pay_full && (
              <button
                type="button"
                onClick={() => pay("full")}
                disabled={paying !== null}
                className="w-full rounded-xl bg-accent px-6 py-3.5 font-semibold text-on-accent transition hover:bg-accent-hover disabled:opacity-60"
              >
                {paying === "full" ? "Starting payment…" : `Pay full amount — ${data.due}`}
              </button>
            )}
            <p className="text-center text-xs text-muted">
              Secure payment via SSLCommerz. The rest is collected on delivery.
            </p>
          </>
        )}

        <Link href="/" className="block pt-1 text-center text-sm font-medium text-accent hover:underline">
          Continue shopping
        </Link>
      </div>
    </div>
  );
}
