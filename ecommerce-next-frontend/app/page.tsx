import { FeaturedCollections } from "@/components/FeaturedCollections";
import { Hero } from "@/components/Hero";
import { getCategories } from "@/lib/api";
import { whatsappGeneral } from "@/lib/whatsapp";

export const revalidate = 60;

export default async function Home() {
  const categories = await getCategories();

  return (
    <>
      <Hero />
      <FeaturedCollections categories={categories} />

      <section className="border-t border-border bg-surface/40">
        <div className="mx-auto max-w-3xl px-6 py-16 text-center">
          <h2 className="text-2xl font-bold sm:text-3xl">
            Price &amp; Dimensions Shown in Photos
          </h2>
          <p className="mt-3 text-sm text-muted">
            Product prices and dimensions are shown in the images. Most items
            are ready stock, and immediate delivery can be arranged.
          </p>
          <a
            href={whatsappGeneral()}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-6 inline-block rounded-full bg-accent px-6 py-3 text-sm font-semibold text-white transition hover:bg-accent-hover"
          >
            Contact us on WhatsApp
          </a>
        </div>
      </section>
    </>
  );
}
