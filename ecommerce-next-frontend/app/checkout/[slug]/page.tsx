import { notFound } from "next/navigation";
import { CheckoutForm } from "@/components/CheckoutForm";
import { getProduct, getProductShippingZones } from "@/lib/api";

export default async function CheckoutPage({
  params,
  searchParams,
}: {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ qty?: string }>;
}) {
  const { slug } = await params;
  const { qty } = await searchParams;

  const [product, zones] = await Promise.all([
    getProduct(slug),
    getProductShippingZones(slug),
  ]);

  if (!product) {
    notFound();
  }

  const parsedQty = Number(qty);
  const initialQty = Number.isFinite(parsedQty) && parsedQty > 0 ? Math.floor(parsedQty) : 1;

  return <CheckoutForm product={product} zones={zones} initialQty={initialQty} />;
}
