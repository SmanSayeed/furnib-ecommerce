import { imageUrl } from "@/lib/image";
import type { Product } from "@/lib/types";
import { ImageSlider, type Slide } from "./ImageSlider";
import { ProductActions } from "./ProductActions";

export function ProductRow({
  product,
  categorySlug,
  whatsapp,
}: {
  product: Product;
  categorySlug: string;
  whatsapp?: string | null;
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

  return (
    <article className="animate-in w-full overflow-hidden border-b border-border pb-3 sm:rounded-2xl sm:border sm:bg-surface/30 sm:pb-0">
      <ImageSlider slides={slides} title={product.title} />
      <div className="mt-2 px-3 sm:mt-3 sm:px-3 sm:pb-3">
        <ProductActions
          product={product}
          categorySlug={categorySlug}
          whatsapp={whatsapp}
        />
      </div>
    </article>
  );
}
