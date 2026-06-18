import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { ProductRow } from "@/components/ProductRow";
import { getProduct } from "@/lib/api";

export const revalidate = 60;

function youtubeId(url: string): string | null {
  const match = url.match(/(?:youtu\.be\/|v=|embed\/)([\w-]{11})/);
  return match ? match[1] : null;
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const product = await getProduct(slug);
  if (!product) return { title: "Not found" };
  return {
    title: product.seo.meta_title ?? product.title,
    description: product.seo.meta_description ?? product.details ?? undefined,
  };
}

export default async function ProductPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const product = await getProduct(slug);
  if (!product) notFound();

  const ytId = product.video ? youtubeId(product.video) : null;

  return (
    <div className="mx-auto max-w-5xl px-6 py-12">
      <Link href="/" className="text-sm text-muted hover:text-foreground">
        ← Back
      </Link>
      <div className="mt-4">
        <ProductRow product={product} />
      </div>

      {ytId && (
        <div className="mt-10">
          <h2 className="mb-4 text-xl font-semibold">Product Video</h2>
          <div className="aspect-video w-full overflow-hidden rounded-2xl border border-border">
            <iframe
              className="h-full w-full"
              src={`https://www.youtube.com/embed/${ytId}`}
              title={product.title}
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          </div>
        </div>
      )}
    </div>
  );
}
