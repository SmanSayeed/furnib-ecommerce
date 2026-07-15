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
  const imageAbsolute = imageUrl(image);
  const url = `${config.siteUrl}/product/${product.slug}`;
  return {
    title: product.seo.meta_title ?? product.title,
    description: product.seo.meta_description ?? product.details ?? undefined,
    alternates: { canonical: url },
    openGraph: {
      title: product.seo.meta_title ?? product.title,
      description: product.seo.meta_description ?? product.details ?? undefined,
      url,
      type: "website",
      // A fully-specified OG image so WhatsApp/Facebook render a proper preview
      // card. width/height/secure_url matter — crawlers skip images they can't
      // size, and secure_url is required over HTTPS.
      images: imageAbsolute
        ? [
            {
              url: imageAbsolute,
              secureUrl: imageAbsolute,
              width: 1200,
              height: 630,
              alt: product.title,
            },
          ]
        : undefined,
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
        <ImageSlider
          slides={slides}
          title={product.title}
          discountPct={discountPct}
          outOfStock={!product.in_stock}
        />
        <div className="space-y-4 p-4 sm:p-6">
          <h1 className="text-xl font-extrabold sm:text-2xl">{product.title}</h1>
          <p className="text-xs text-muted">SKU: {product.sku}</p>

          {/* Compliance #9 — clear stock status (with quantity when available). */}
          {product.in_stock ? (
            <p className="inline-flex items-center gap-1.5 text-sm font-semibold text-green-600 dark:text-green-400">
              <span className="size-2 rounded-full bg-green-500" aria-hidden="true" />
              In Stock
              {typeof product.stock_amount === "number" && (
                <span className="font-normal text-muted">
                  ({product.stock_amount} available)
                </span>
              )}
            </p>
          ) : (
            <p className="inline-flex items-center gap-1.5 text-sm font-semibold text-red-600 dark:text-red-400">
              <span className="size-2 rounded-full bg-red-500" aria-hidden="true" />
              Out of Stock
            </p>
          )}

          {product.details && (
            <p className="text-sm leading-relaxed text-muted">{product.details}</p>
          )}

          <ProductActions
            product={product}
            whatsapp={settings?.whatsapp}
            inquiryEnabled={settings?.whatsapp_buttons?.inquiry ?? true}
          />
        </div>
      </article>
    </div>
  );
}
