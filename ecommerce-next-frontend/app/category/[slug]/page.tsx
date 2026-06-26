import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CategoryGrid } from "@/components/CategoryGrid";
import { InfiniteProducts } from "@/components/InfiniteProducts";
import { SafeImage } from "@/components/SafeImage";
import { getCategories, getCategory, getSettings } from "@/lib/api";
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
  const [allCategories, settings] = await Promise.all([
    getCategories(),
    getSettings(),
  ]);

  return (
    <div>
      <section className="relative mt-3 h-[23vh] w-full overflow-hidden rounded-card border border-border sm:h-[50vh]">
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

      <div className="mx-auto w-full max-w-6xl px-0 py-5 sm:py-8">
        <InfiniteProducts
          slug={slug}
          initial={products}
          meta={meta}
          whatsapp={settings?.whatsapp}
          brand={{
            name: settings?.site_name ?? "Furnib.com",
            avatar: imageUrl(settings?.favicon),
          }}
        />
      </div>

      <section className="mx-auto max-w-2xl px-4 pb-16 sm:px-4">
        <h2 className="mb-8 text-center text-2xl font-bold sm:text-3xl">
          Explore Collections
        </h2>
        <CategoryGrid categories={allCategories} />
      </section>
    </div>
  );
}
