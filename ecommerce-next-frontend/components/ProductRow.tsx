import { imageUrl } from "@/lib/image";
import type { Product } from "@/lib/types";
import { ImageSlider, type Slide } from "./ImageSlider";
import { ProductActions } from "./ProductActions";
import { ProductCaption } from "./ProductCaption";
import { SafeImage } from "./SafeImage";

export type Brand = { name: string; avatar: string | null };

export function ProductRow({
  product,
  categorySlug,
  whatsapp,
  brand,
}: {
  product: Product;
  categorySlug: string;
  whatsapp?: string | null;
  brand?: Brand;
}) {
  const slides: Slide[] = [
    ...(product.main_image
      ? [{ url: imageUrl(product.main_image), alt: product.title }]
      : []),
    ...(product.images ?? []).map((g) => ({
      url: imageUrl(g.path),
      alt: g.alt ?? product.title,
    })),
  ];

  const discountPct =
    product.discount_price && product.price.display > 0
      ? Math.round(
          (1 - product.discount_price.display / product.price.display) * 100,
        )
      : 0;

  const brandName = brand?.name ?? "Furnib.com";
  const initial = brandName.trim().charAt(0).toUpperCase() || "F";

  return (
    <article className="animate-in w-full overflow-hidden rounded-card border border-border bg-surface/30">
      {/* Header — brand avatar + product title (Facebook post header) */}
      <div className="flex items-center gap-2.5 px-3 pt-3 sm:px-4">
        <div className="h-9 w-9 shrink-0 overflow-hidden rounded-full border border-border bg-surface-2">
          {brand?.avatar ? (
            <SafeImage
              src={brand.avatar}
              alt={brandName}
              className="h-full w-full object-cover"
            />
          ) : (
            <span className="flex h-full w-full items-center justify-center text-sm font-bold text-accent">
              {initial}
            </span>
          )}
        </div>
        <div className="min-w-0">
          <h3 className="truncate text-[15px] font-semibold leading-tight">
            {product.title}
          </h3>
          <p className="truncate text-xs text-muted">
            {brandName}
            {product.in_stock ? " · In stock" : " · Made to order"}
          </p>
        </div>
      </div>

      {/* Caption — short description with See more */}
      {product.details ? (
        <div className="mt-2">
          <ProductCaption text={product.details} />
        </div>
      ) : null}

      {/* Media — slider + discount chip + thumbnails */}
      <div className="mt-3">
        <ImageSlider
          slides={slides}
          title={product.title}
          discountPct={discountPct}
        />
      </div>

      {/* Action bar — price + Inquiry (left), Order (right) */}
      <div className="mt-3 border-t border-border px-3 py-3 sm:px-4">
        <ProductActions
          product={product}
          categorySlug={categorySlug}
          whatsapp={whatsapp}
        />
      </div>
    </article>
  );
}
