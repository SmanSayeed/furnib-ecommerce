import Link from "next/link";

export default async function CheckoutStub({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;

  return (
    <div className="mx-auto flex max-w-md flex-col items-center px-6 py-24 text-center">
      <div className="flex h-16 w-16 items-center justify-center rounded-full bg-surface-2 text-2xl">
        🛒
      </div>
      <h1 className="mt-6 text-2xl font-bold">Web Checkout</h1>
      <p className="mt-3 text-sm text-muted">
        The full web checkout — shipping zones, BD mobile + OTP, payment via
        SSLCommerz, and invoice — arrives in <strong>Phase 3</strong>.
      </p>
      <p className="mt-1 text-xs text-muted">Product: {slug}</p>
      <Link
        href="/"
        className="mt-6 inline-block rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white transition hover:bg-accent-hover"
      >
        Back to home
      </Link>
    </div>
  );
}
