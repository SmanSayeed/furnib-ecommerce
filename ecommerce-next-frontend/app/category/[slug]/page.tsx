import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CategoryGrid } from "@/components/CategoryGrid";
import { InfiniteProducts } from "@/components/InfiniteProducts";
import { SafeImage } from "@/components/SafeImage";
import { getCategories, getCategory } from "@/lib/api";
import { imageUrl } from "@/lib/image";

export const revalidate = 60;

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const data = await getCategory(slug);
  if (!data) return { title: "Not found" };
  const { category } = data;
  return {
    title: category.seo.meta_title ?? category.title,
    description: category.seo.meta_description ?? category.details ?? undefined,
  };
}

export default async function CategoryPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const data = await getCategory(slug, 1);
  if (!data) notFound();

  const { category, products, meta } = data;
  const allCategories = await getCategories();

  return (
    <div>
      <section className="relative h-[55vh] w-full overflow-hidden border-b border-border sm:h-[75vh]">
        <SafeImage
          src={imageUrl(category.header_image ?? category.thumbnail_image)}
          alt={category.title}
          className="h-full w-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-background via-background/40 to-transparent" />
        <div className="absolute bottom-0 left-0 w-full p-6 sm:p-12">
          <div className="mx-auto max-w-5xl">
            <h1 className="text-3xl font-extrabold sm:text-5xl">{category.title}</h1>
            {category.details && (
              <p className="mt-2 max-w-2xl text-sm text-muted sm:text-base">
                {category.details}
              </p>
            )}
          </div>
        </div>
      </section>

      <div className="mx-auto max-w-5xl px-6 py-12">
        <InfiniteProducts slug={slug} initial={products} meta={meta} />
      </div>

      <section className="mx-auto max-w-5xl px-6 pb-16">
        <h2 className="mb-8 text-center text-2xl font-bold sm:text-3xl">
          Explore Collections
        </h2>
        <CategoryGrid categories={allCategories} />
      </section>
    </div>
  );
}
