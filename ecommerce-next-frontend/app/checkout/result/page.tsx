import Link from "next/link";

/**
 * Payment result landing. SSLCommerz posts to the Laravel callback, which then
 * redirects the shopper here with ?status=success|failed|cancelled&order=<no>.
 * This page is display-only — the order's real payment state was already
 * recorded server-side (validated via the SSLCommerz validation API).
 */
export default async function PaymentResultPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string; order?: string }>;
}) {
  const { status, order } = await searchParams;
  const ok = status === "success";
  const cancelled = status === "cancelled";

  const title = ok
    ? "Payment successful"
    : cancelled
      ? "Payment cancelled"
      : "Payment not completed";

  const message = ok
    ? "We’ve received your payment. We’ll contact you shortly to confirm delivery."
    : cancelled
      ? "You cancelled the payment. Your order is still saved — you can try again or pay cash on delivery."
      : "The payment could not be completed. Your order is still saved — you can try again or pay cash on delivery.";

  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-6 py-20 text-center">
      <div
        className={`flex h-16 w-16 items-center justify-center rounded-full text-3xl ${
          ok ? "bg-green-500/15 text-green-600" : "bg-red-500/15 text-red-600"
        }`}
      >
        {ok ? "✓" : "✕"}
      </div>

      <h1 className="mt-4 text-2xl font-bold">{title}</h1>

      {order && (
        <p className="mt-1 text-sm text-muted">
          Order <span className="font-semibold text-foreground">{order}</span>
        </p>
      )}

      <p className="mt-3 text-sm leading-relaxed text-muted">{message}</p>

      <div className="mt-8 flex w-full flex-col gap-3">
        {!ok && (
          <Link
            href="/checkout/success"
            className="w-full rounded-xl bg-accent px-6 py-3.5 font-semibold text-on-accent transition hover:bg-accent-hover"
          >
            Try payment again
          </Link>
        )}
        <Link
          href="/"
          className={`w-full rounded-xl px-6 py-3.5 font-semibold transition ${
            ok
              ? "bg-accent text-on-accent hover:bg-accent-hover"
              : "border border-border bg-surface hover:bg-surface-2"
          }`}
        >
          Continue shopping
        </Link>
      </div>
    </div>
  );
}
