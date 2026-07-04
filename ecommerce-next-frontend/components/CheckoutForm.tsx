"use client";

import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";
import { imageUrl } from "@/lib/image";
import { trackPurchase } from "@/lib/track";
import type { PlacedOrder, Product, ProductShippingZone } from "@/lib/types";
import { SafeImage } from "./SafeImage";

function taka(minor: number): string {
  // Whole taka only — round to the nearest, no decimals.
  return `৳${Math.round(minor / 100).toLocaleString("en-US")}`;
}

export function CheckoutForm({
  product,
  zones,
  initialQty,
}: {
  product: Product;
  zones: ProductShippingZone[];
  initialQty: number;
}) {
  const router = useRouter();
  const unit = product.discount_price ?? product.price;

  const [qty, setQty] = useState(Math.max(1, initialQty));
  const [name, setName] = useState("");
  const [mobile, setMobile] = useState("");
  const [address, setAddress] = useState("");
  const [zoneId, setZoneId] = useState<number | null>(zones[0]?.id ?? null);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  const selectedZone = useMemo(
    () => zones.find((z) => z.id === zoneId) ?? null,
    [zones, zoneId],
  );

  // A product whose advance is the delivery charge needs a zone; if none exist
  // yet, block submission with a clear message instead of a cryptic server error.
  const needsShippingZone =
    (product.advance?.required ?? false) &&
    product.advance?.partial_type === "shipping" &&
    !product.free_shipping;
  const blockedNoZone = needsShippingZone && zones.length === 0;

  // Effective shipping for a zone = base + this product's per-unit extra × qty.
  const zoneCostMinor = (zone: ProductShippingZone): number =>
    zone.base.minor + zone.extra_per_unit.minor * qty;

  const subtotalMinor = unit.minor * qty;
  // A free-shipping product never adds any delivery charge.
  const shippingMinor = product.free_shipping
    ? 0
    : selectedZone
      ? zoneCostMinor(selectedZone)
      : 0;
  const totalMinor = subtotalMinor + shippingMinor;

  // Live advance preview — mirrors the server's AdvancePayment rule so the
  // customer sees exactly what they'll be asked to prepay now.
  const adv = product.advance;
  const advanceRequired = adv?.required ?? false;
  let advanceMinor = 0;
  if (adv?.required && adv.type) {
    if (adv.type === "full") {
      // Full advance = the whole order (product + shipping), not just the product.
      advanceMinor = totalMinor;
    } else if (adv.partial_type === "percentage") {
      // Mirror the server: round to the nearest whole taka (half-up), no poysha.
      advanceMinor = Math.round((subtotalMinor * (adv.partial_amount ?? 0)) / 10000) * 100;
    } else if (adv.partial_type === "amount") {
      // partial_amount is whole-taka paisa on the server.
      advanceMinor = Math.min(adv.partial_amount ?? 0, subtotalMinor);
    } else if (adv.partial_type === "shipping") {
      advanceMinor = shippingMinor;
    }
    advanceMinor = Math.min(advanceMinor, totalMinor);
  }
  // Show the "payable now" line whenever an advance applies (partial OR full).
  const showAdvance = advanceRequired && advanceMinor > 0;
  // The rest is collected as cash on delivery.
  const dueMinor = Math.max(0, totalMinor - advanceMinor);

  // Opens an SSLCommerz session for the required advance and navigates the
  // browser to the gateway. Returns false if the session couldn't be started.
  async function startAdvancePayment(orderNo: string): Promise<boolean> {
    try {
      const res = await fetch("/api/payment/init", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        // `partial` charges the order's advance_amount — the correct amount for
        // both partial and full-advance products (server resolves it).
        body: JSON.stringify({ order_no: orderNo, type: "partial" }),
      });
      const json = await res.json();
      if (res.ok && json?.gateway_url) {
        window.location.href = json.gateway_url;
        return true;
      }
      setGeneralError(
        json?.error?.message ?? "Could not start the advance payment. Please try again.",
      );
      return false;
    } catch {
      setGeneralError("Network error starting payment. Please try again.");
      return false;
    }
  }

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
          customer: { name, mobile },
          // A free-shipping product carries no delivery zone at all.
          shipping_zone_id: product.free_shipping ? null : zoneId,
          address,
          // Passive acceptance — clicking Place Order = agreeing to our policies.
          terms_accepted: true,
        }),
      });
      const json = await res.json();

      if (res.status === 201 && json?.data) {
        const placed = json.data as PlacedOrder;
        // purchase fires here — the moment the order is placed — using the rich
        // dataLayer payload Laravel built for it. The server-side CAPI/GA4/TikTok
        // copy fires at the same moment and dedupes by the shared event_id.
        if (placed.tracking) trackPurchase(placed.tracking);
        sessionStorage.setItem("furnib:order", JSON.stringify(placed));
        // Stash the shopper's own mobile so the success page can fetch the live
        // paid/due state (the endpoint verifies order_no + this mobile).
        sessionStorage.setItem("furnib:order_mobile", mobile);

        // Advance/partial payment is MANDATORY: go straight to the gateway.
        // `advance_amount` (server truth) drives this — for a "full" advance it
        // equals the payable amount, for a partial it's the required prepay.
        if (placed.advance_amount.minor > 0) {
          const started = await startAdvancePayment(placed.order_no);
          if (started) return; // browser is navigating to SSLCommerz
          // Couldn't open the gateway — send them to the success page where the
          // "Pay advance" button lets them retry (order is already saved).
          router.push("/checkout/success");
          return;
        }

        // No advance required — order stands; paying online is optional there.
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

        {zones.length > 0 && !product.free_shipping && (
          <div>
            <span className="mb-2 block text-sm font-medium">Shipping method</span>
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
                  <span className="text-sm font-semibold">{taka(zoneCostMinor(zone))}</span>
                </label>
              ))}
            </div>
            {fieldError("shipping_zone_id")}
          </div>
        )}

        {blockedNoZone && (
          <div className="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400">
            Shipping methods aren’t set up yet for this product. Please contact us on
            WhatsApp to place your order.
          </div>
        )}
      </div>

      {/* Summary */}
      <div className="mt-6 space-y-2 rounded-2xl border border-border bg-surface-2 p-4 text-sm">
        <div className="flex justify-between">
          <span className="text-muted">Subtotal ({qty} item{qty > 1 ? "s" : ""})</span>
          <span className="font-medium">{taka(subtotalMinor)}</span>
        </div>
        {!product.free_shipping && (
          <div className="flex justify-between">
            <span className="text-muted">Shipping</span>
            <span className="font-medium">
              {shippingMinor ? taka(shippingMinor) : "—"}
            </span>
          </div>
        )}
        <div className="flex justify-between border-t border-border pt-2 text-base font-bold">
          <span>Total</span>
          <span className="text-accent">{taka(totalMinor)}</span>
        </div>
        {showAdvance && (
          <div className="mt-1 space-y-1 border-t border-border pt-2 text-sm">
            <div className="flex justify-between">
              <span className="font-medium">
                Pay now online (advance)
                {adv?.partial_type === "shipping" ? " · shipping" : ""}
              </span>
              <span className="font-semibold text-accent">{taka(advanceMinor)}</span>
            </div>
            {dueMinor > 0 && (
              <div className="flex justify-between text-muted">
                <span>Cash on delivery (due)</span>
                <span className="font-medium">{taka(dueMinor)}</span>
              </div>
            )}
          </div>
        )}
      </div>

      <button
        type="submit"
        disabled={submitting || blockedNoZone}
        className="mt-6 w-full rounded-xl bg-accent px-6 py-3.5 text-center font-semibold text-on-accent transition hover:bg-accent-hover disabled:cursor-not-allowed disabled:opacity-60"
      >
        {submitting
          ? advanceRequired
            ? "Starting payment…"
            : "Placing order…"
          : advanceRequired
            ? `Place order & pay advance — ${taka(advanceMinor)}`
            : "Place order"}
      </button>

      {/* Passive acceptance — clicking Place Order counts as agreement (backend
          keeps the audit trail). No blocking checkbox. */}
      <p className="mt-3 text-center text-xs leading-relaxed text-muted">
        By clicking Place Order, you agree to our{" "}
        <a
          href="/p/terms-and-conditions"
          target="_blank"
          rel="noopener noreferrer"
          className="font-medium text-accent underline underline-offset-2 hover:text-accent-hover"
        >
          Terms &amp; Conditions
        </a>
        ,{" "}
        <a
          href="/p/privacy-policy"
          target="_blank"
          rel="noopener noreferrer"
          className="font-medium text-accent underline underline-offset-2 hover:text-accent-hover"
        >
          Privacy Policy
        </a>{" "}
        &amp;{" "}
        <a
          href="/p/return-refund-policy"
          target="_blank"
          rel="noopener noreferrer"
          className="font-medium text-accent underline underline-offset-2 hover:text-accent-hover"
        >
          Refund Policy
        </a>
        .
      </p>

      <p className="mt-3 text-center text-xs text-muted">
        {advanceRequired
          ? "This item needs an advance payment. You'll pay it securely now to confirm your order."
          : "Cash on delivery or online payment — choose on the next step."}
      </p>
    </form>
  );
}
