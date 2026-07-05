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

  // Responsive header: desktop = header_image, mobile = header_mobile_url
  // (falls back to the desktop header when no dedicated mobile image).
  const desktopHeader = imageUrl(category.header_image);
  const mobileHeader = imageUrl(category.header_mobile_url) ?? desktopHeader;

  return (
    <div>
      <section className="relative mt-3 h-[22vh] w-full overflow-hidden rounded-card border border-border sm:h-[50vh]">
        {desktopHeader ? (
          <picture className="block h-full w-full">
            <source media="(min-width:768px)" srcSet={desktopHeader} />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={mobileHeader ?? desktopHeader}
              alt={category.title}
              className="h-full w-full object-cover"
            />
          </picture>
        ) : (
          <SafeImage
            src={imageUrl(category.thumbnail_image)}
            alt={category.title}
            className="h-full w-full object-cover"
          />
        )}
        <div className="absolute bottom-0 left-0 w-full p-4 sm:p-8">
          <div className="mx-auto max-w-5xl">
            {/* No full-image overlay — a text drop-shadow keeps the title legible
                on light or dark banners without darkening the whole image. */}
            <h1 className="text-xl font-bold text-white drop-shadow-[0_2px_6px_rgba(0,0,0,0.6)] sm:text-3xl">
              {category.title}
            </h1>
            {category.details && (
              <p className="mt-1 max-w-2xl text-xs text-white/90 drop-shadow-[0_1px_4px_rgba(0,0,0,0.55)] sm:text-sm">
                {category.details}
              </p>
            )}
          </div>
        </div>
      </section>

      <div className="w-full py-5 sm:py-8">
        <InfiniteProducts
          slug={slug}
          initial={products}
          meta={meta}
          whatsapp={settings?.whatsapp}
          inquiryEnabled={settings?.whatsapp_buttons?.inquiry ?? true}
          brand={{
            name: settings?.site_name ?? "Furnib.com",
            avatar: imageUrl(settings?.favicon),
          }}
        />
      </div>

      <section className="mt-12 w-full border-t border-border pt-12 pb-16">
        <h2 className="mb-8 text-center text-3xl font-bold sm:text-4xl">
          Explore Collections
        </h2>
        <CategoryGrid categories={allCategories} />
      </section>
    </div>
  );
}
