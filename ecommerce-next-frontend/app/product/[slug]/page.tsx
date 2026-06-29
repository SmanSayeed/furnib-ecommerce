import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { ImageSlider, type Slide } from "@/components/ImageSlider";
import { ProductActions } from "@/components/ProductActions";
import { getProduct, getSettings } from "@/lib/api";
import { config } from "@/lib/config";
import { imageUrl } from "@/lib/image";

export const revalidate = 60;

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const product = await getProduct(slug);
  if (!product) return { title: "Not found" };

  const image = product.social_thumbnail || product.main_image;
  return {
    title: product.seo.meta_title ?? product.title,
    description: product.seo.meta_description ?? product.details ?? undefined,
    openGraph: {
      title: product.seo.meta_title ?? product.title,
      images: image ? [{ url: imageUrl(image) ?? "" }] : undefined,
      type: "website",
    },
  };
}

export default async function ProductPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const [product, settings] = await Promise.all([getProduct(slug), getSettings()]);
  if (!product) notFound();

  const unit = product.discount_price ?? product.price;
  const discountPct =
    product.discount_price && product.price.display > 0
      ? Math.round(
          (1 - product.discount_price.display / product.price.display) * 100,
        )
      : 0;
  const slides: Slide[] = [
    ...(product.main_image ? [{ url: imageUrl(product.main_image), alt: product.title }] : []),
    ...(product.images ?? []).map((g) => ({ url: imageUrl(g.path), alt: g.alt ?? product.title })),
  ];

  // Product structured data — rich results + Merchant Center signals.
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.title,
    sku: product.sku,
    image: product.main_image ? [imageUrl(product.main_image)] : undefined,
    description: product.seo.meta_description ?? product.details ?? undefined,
    offers: {
      "@type": "Offer",
      priceCurrency: "BDT",
      price: unit.display,
      availability: product.in_stock
        ? "https://schema.org/InStock"
        : "https://schema.org/OutOfStock",
      url: `${config.siteUrl}/product/${product.slug}`,
    },
  };

  return (
    <div className="mx-auto w-full max-w-3xl px-3 py-5 sm:py-8">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />

      <article className="overflow-hidden rounded-2xl border border-border bg-surface/30">
        <ImageSlider slides={slides} title={product.title} discountPct={discountPct} />
        <div className="space-y-4 p-4 sm:p-6">
          <h1 className="text-xl font-extrabold sm:text-2xl">{product.title}</h1>
          <p className="text-xs text-muted">SKU: {product.sku}</p>

          {product.details && (
            <p className="text-sm leading-relaxed text-muted">{product.details}</p>
          )}

          <ProductActions product={product} whatsapp={settings?.whatsapp} />
        </div>
      </article>
    </div>
  );
}
