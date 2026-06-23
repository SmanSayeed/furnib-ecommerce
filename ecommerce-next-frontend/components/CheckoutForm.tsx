"use client";

import { useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import { imageUrl } from "@/lib/image";
import { trackInitiateCheckout } from "@/lib/track";
import type { Product, ShippingZone } from "@/lib/types";
import { SafeImage } from "./SafeImage";

function taka(minor: number): string {
  return `৳${(minor / 100).toLocaleString("en-US", { minimumFractionDigits: 2 })}`;
}

export function CheckoutForm({
  product,
  zones,
  initialQty,
}: {
  product: Product;
  zones: ShippingZone[];
  initialQty: number;
}) {
  const router = useRouter();
  const unit = product.discount_price ?? product.price;

  const [qty, setQty] = useState(Math.max(1, initialQty));
  const [name, setName] = useState("");
  const [mobile, setMobile] = useState("");
  const [email, setEmail] = useState("");
  const [address, setAddress] = useState("");
  const [zoneId, setZoneId] = useState<number | null>(zones[0]?.id ?? null);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  const selectedZone = useMemo(
    () => zones.find((z) => z.id === zoneId) ?? null,
    [zones, zoneId],
  );

  // Fire InitiateCheckout once when the checkout screen is first shown.
  const checkoutTracked = useRef(false);
  useEffect(() => {
    if (checkoutTracked.current) return;
    checkoutTracked.current = true;
    trackInitiateCheckout({
      sku: product.sku,
      name: product.title,
      price: unit.minor / 100,
      qty,
    });
  }, [product.sku, product.title, qty, unit.minor]);

  const subtotalMinor = unit.minor * qty;
  const shippingMinor = selectedZone?.cost.minor ?? 0;
  const totalMinor = subtotalMinor + shippingMinor;

  // Live advance preview — mirrors the server's AdvancePayment rule so the
  // customer sees exactly what they'll be asked to prepay now.
  const adv = product.advance;
  let advanceMinor = 0;
  if (adv?.required && adv.type) {
    if (adv.type === "full") {
      advanceMinor = subtotalMinor;
    } else if (adv.partial_type === "percentage") {
      advanceMinor = Math.floor((subtotalMinor * (adv.partial_amount ?? 0)) / 100);
    } else if (adv.partial_type === "amount") {
      advanceMinor = Math.min(adv.partial_amount ?? 0, subtotalMinor);
    } else if (adv.partial_type === "shipping") {
      advanceMinor = shippingMinor;
    }
    advanceMinor = Math.min(advanceMinor, totalMinor);
  }
  const showAdvance = advanceMinor > 0 && advanceMinor < totalMinor;

  async function placeOrder(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setErrors({});
    setGeneralError(null);

    try {
      const res = await fetch("/api/checkout", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          items: [{ product_id: product.id, qty }],
          customer: { name, mobile, ...(email ? { email } : {}) },
          shipping_zone_id: zoneId,
          address,
        }),
      });
      const json = await res.json();

      if (res.status === 201 && json?.data) {
        sessionStorage.setItem("furnib:order", JSON.stringify(json.data));
        router.push("/checkout/success");
        return;
      }

      if (res.status === 422 && json?.error?.details) {
        const mapped: Record<string, string> = {};
        for (const [field, msgs] of Object.entries(json.error.details)) {
          mapped[field] = Array.isArray(msgs) ? String(msgs[0]) : String(msgs);
        }
        setErrors(mapped);
        setGeneralError(json?.error?.message ?? "Please fix the highlighted fields.");
      } else {
        setGeneralError(json?.error?.message ?? json?.message ?? "Something went wrong. Please try again.");
      }
    } catch {
      setGeneralError("Network error. Please check your connection and try again.");
    } finally {
      setSubmitting(false);
    }
  }

  const fieldError = (key: string) =>
    errors[key] ? <p className="mt-1 text-xs text-red-500">{errors[key]}</p> : null;

  return (
    <form onSubmit={placeOrder} className="mx-auto w-full max-w-lg px-4 py-6 sm:py-10">
      <h1 className="text-xl font-bold sm:text-2xl">Checkout</h1>

      {/* Product summary */}
      <div className="mt-5 flex items-start gap-4 rounded-2xl border border-border bg-surface p-4">
        <div className="h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-border">
          <SafeImage
            src={imageUrl(product.main_image)}
            alt={product.title}
            className="h-full w-full object-cover"
          />
        </div>
        <div className="min-w-0 flex-1">
          <h2 className="truncate font-semibold">{product.title}</h2>
          <p className="text-xs text-muted">SKU: {product.sku}</p>
          <p className="mt-1 text-sm font-bold text-accent">{unit.formatted}</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setQty((q) => Math.max(1, q - 1))}
            className="h-9 w-9 rounded-full border border-border text-lg leading-none hover:bg-surface-2"
            aria-label="Decrease quantity"
          >
            −
          </button>
          <span className="w-6 text-center font-semibold">{qty}</span>
          <button
            type="button"
            onClick={() => setQty((q) => q + 1)}
            className="h-9 w-9 rounded-full border border-border text-lg leading-none hover:bg-surface-2"
            aria-label="Increase quantity"
          >
            +
          </button>
        </div>
      </div>

      {generalError && (
        <div className="mt-5 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-600 dark:text-red-400">
          {generalError}
        </div>
      )}

      {/* Customer */}
      <div className="mt-5 space-y-4">
        <div>
          <label htmlFor="name" className="mb-1 block text-sm font-medium">
            Full name
          </label>
          <input
            id="name"
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="w-full rounded-xl border border-border bg-surface px-4 py-3 outline-none focus:border-accent"
            placeholder="Your name"
            autoComplete="name"
          />
          {fieldError("customer.name")}
        </div>

        <div>
          <label htmlFor="mobile" className="mb-1 block text-sm font-medium">
            Mobile number
          </label>
          <input
            id="mobile"
            type="tel"
            inputMode="numeric"
            value={mobile}
            onChange={(e) => setMobile(e.target.value)}
            className="w-full rounded-xl border border-border bg-surface px-4 py-3 outline-none focus:border-accent"
            placeholder="01XXXXXXXXX"
            autoComplete="tel"
          />
          {fieldError("customer.mobile")}
        </div>

        <div>
          <label htmlFor="email" className="mb-1 block text-sm font-medium">
            Email <span className="text-muted">(optional)</span>
          </label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full rounded-xl border border-border bg-surface px-4 py-3 outline-none focus:border-accent"
            placeholder="you@example.com"
            autoComplete="email"
          />
          {fieldError("customer.email")}
        </div>

        <div>
          <label htmlFor="address" className="mb-1 block text-sm font-medium">
            Delivery address
          </label>
          <textarea
            id="address"
            value={address}
            onChange={(e) => setAddress(e.target.value)}
            rows={3}
            className="w-full resize-none rounded-xl border border-border bg-surface px-4 py-3 outline-none focus:border-accent"
            placeholder="House, road, area, city"
          />
          {fieldError("address")}
        </div>

        {zones.length > 0 && (
          <div>
            <span className="mb-2 block text-sm font-medium">Delivery area</span>
            <div className="space-y-2">
              {zones.map((zone) => (
                <label
                  key={zone.id}
                  className={`flex cursor-pointer items-center justify-between rounded-xl border px-4 py-3 transition ${
                    zoneId === zone.id
                      ? "border-accent bg-accent/5"
                      : "border-border bg-surface hover:bg-surface-2"
                  }`}
                >
                  <span className="flex items-center gap-3">
                    <input
                      type="radio"
                      name="zone"
                      checked={zoneId === zone.id}
                      onChange={() => setZoneId(zone.id)}
                      className="accent-[var(--accent)]"
                    />
                    <span className="text-sm font-medium">{zone.name}</span>
                  </span>
                  <span className="text-sm font-semibold">{zone.cost.formatted}</span>
                </label>
              ))}
            </div>
            {fieldError("shipping_zone_id")}
          </div>
        )}
      </div>

      {/* Summary */}
      <div className="mt-6 space-y-2 rounded-2xl border border-border bg-surface-2 p-4 text-sm">
        <div className="flex justify-between">
          <span className="text-muted">Subtotal ({qty} item{qty > 1 ? "s" : ""})</span>
          <span className="font-medium">{taka(subtotalMinor)}</span>
        </div>
        <div className="flex justify-between">
          <span className="text-muted">Shipping</span>
          <span className="font-medium">{shippingMinor ? taka(shippingMinor) : "—"}</span>
        </div>
        <div className="flex justify-between border-t border-border pt-2 text-base font-bold">
          <span>Total</span>
          <span className="text-accent">{taka(totalMinor)}</span>
        </div>
        {showAdvance && (
          <div className="flex justify-between border-t border-border pt-2 text-sm">
            <span className="font-medium">
              Advance payable now
              {adv?.partial_type === "shipping" ? " (delivery charge)" : ""}
            </span>
            <span className="font-semibold text-accent">{taka(advanceMinor)}</span>
          </div>
        )}
      </div>

      <button
        type="submit"
        disabled={submitting}
        className="mt-5 w-full rounded-xl bg-accent px-6 py-3.5 text-center font-semibold text-on-accent transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60"
      >
        {submitting ? "Placing order…" : "Place order"}
      </button>

      <p className="mt-3 text-center text-xs text-muted">
        Cash on delivery or online payment — choose on the next step.
      </p>
    </form>
  );
}
