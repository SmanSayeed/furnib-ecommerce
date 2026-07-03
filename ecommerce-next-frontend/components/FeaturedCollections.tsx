import type { Category } from "@/lib/types";
import { CategoryGrid } from "./CategoryGrid";

export function FeaturedCollections({ categories }: { categories: Category[] }) {
  return (
    <section id="collections" className="w-full px-0 py-10 sm:py-12">
      <div className="text-center">
        <h2 className="text-3xl font-bold sm:text-4xl">Featured Series</h2>
        <p className="mt-2 text-sm text-muted">
          Explore more collections from the menu, including Living, Dining,
          Office, Smart Furniture, Lighting, Décor, and more. Discover quality
          furniture designed for the way you live—crafted for style,
          functionality, and everyday comfort.
        </p>
      </div>
      <div className="mt-10">
        {categories.length ? (
          <CategoryGrid categories={categories} />
        ) : (
          <p className="text-center text-muted">Catalog coming soon.</p>
        )}
      </div>
    </section>
  );
}
