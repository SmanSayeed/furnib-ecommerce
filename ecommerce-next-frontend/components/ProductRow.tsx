import { imageUrl } from "@/lib/image";
import type { Product } from "@/lib/types";
import { ImageSlider, type Slide } from "./ImageSlider";
import { ProductActions } from "./ProductActions";

function Badge({ label }: { label: string }) {
  return (
    <span className="rounded-full bg-surface-2 px-3 py-1 text-xs font-medium">
      {label}
    </span>
  );
}

export function ProductRow({ product }: { product: Product }) {
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
    <article className="grid animate-in gap-8 rounded-2xl border border-border bg-surface/40 p-5 md:grid-cols-2 md:p-8">
      <ImageSlider slides={slides} title={product.title} />
      <div className="flex flex-col justify-center">
        <div className="flex flex-wrap gap-2">
          {product.is_new && <Badge label="New" />}
          {product.is_featured && <Badge label="Featured" />}
          <span
            className={`rounded-full px-3 py-1 text-xs font-medium ${
              product.in_stock
                ? "bg-accent/15 text-accent"
                : "bg-red-500/15 text-red-400"
            }`}
          >
            {product.in_stock ? "In Stock" : "Out of Stock"}
          </span>
        </div>
        <h2 className="mt-3 text-2xl font-bold">{product.title}</h2>
        <p className="mt-1 text-xs text-muted">SKU: {product.sku}</p>
        {product.details && (
          <p className="mt-3 line-clamp-4 text-sm leading-relaxed text-muted">
            {product.details}
          </p>
        )}
        <div className="mt-6">
          <ProductActions product={product} />
        </div>
      </div>
    </article>
  );
}
