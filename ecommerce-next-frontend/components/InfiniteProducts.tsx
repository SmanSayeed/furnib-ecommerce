"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import type { PageMeta, Product } from "@/lib/types";
import { type Brand, ProductRow } from "./ProductRow";

export function InfiniteProducts({
  slug,
  initial,
  meta,
  whatsapp,
  inquiryEnabled = true,
  brand,
}: {
  slug: string;
  initial: Product[];
  meta: PageMeta;
  whatsapp?: string | null;
  inquiryEnabled?: boolean;
  brand?: Brand;
}) {
  const [products, setProducts] = useState<Product[]>(initial);
  const [page, setPage] = useState(meta.current_page);
  const [lastPage, setLastPage] = useState(meta.last_page);
  const [loading, setLoading] = useState(false);
  const sentinel = useRef<HTMLDivElement | null>(null);

  const loadMore = useCallback(async () => {
    if (loading || page >= lastPage) return;
    setLoading(true);
    try {
      const res = await fetch(`/api/catalog/${slug}?page=${page + 1}`);
      if (res.ok) {
        const json: { products: Product[]; meta: PageMeta } = await res.json();
        setProducts((prev) => [...prev, ...(json.products ?? [])]);
        setPage(json.meta?.current_page ?? page + 1);
        setLastPage(json.meta?.last_page ?? lastPage);
      }
    } finally {
      setLoading(false);
    }
  }, [loading, page, lastPage, slug]);

  useEffect(() => {
    const el = sentinel.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) void loadMore();
      },
      { rootMargin: "400px" },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [loadMore]);

  if (products.length === 0) {
    return (
      <p className="py-10 text-center text-muted">
        No products in this collection yet.
      </p>
    );
  }

  return (
    <>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {products.map((p) => (
          <ProductRow
            key={p.id}
            product={p}
            categorySlug={slug}
            whatsapp={whatsapp}
            inquiryEnabled={inquiryEnabled}
            brand={brand}
          />
        ))}
      </div>
      {page < lastPage && (
        <div ref={sentinel} className="py-6 text-center text-sm text-muted">
          {loading ? "Loading…" : "Scroll for more"}
        </div>
      )}
    </>
  );
}
