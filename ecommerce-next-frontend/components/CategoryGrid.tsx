import Link from "next/link";
import { imageUrl } from "@/lib/image";
import type { Category } from "@/lib/types";
import { SafeImage } from "./SafeImage";

export function CategoryGrid({ categories }: { categories: Category[] }) {
  return (
    <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
      {categories.map((c) => (
        <Link
          key={c.id}
          href={`/category/${c.slug}`}
          className="group overflow-hidden rounded-card border border-border bg-surface transition hover:border-accent/50"
        >
          {/* 2px inset frame + matching (slightly smaller) radius so the image
              echoes the card's rounded corners instead of butting the border. */}
          <div className="p-0.5">
            <div className="aspect-[16/10] overflow-hidden rounded-[8px]">
              <SafeImage
                src={imageUrl(c.header_image ?? c.thumbnail_image)}
                alt={c.title}
                className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
              />
            </div>
          </div>
          <div className="p-5">
            <h3 className="text-2xl font-bold">{c.title}</h3>
            {c.details && (
              <p className="mt-1.5 line-clamp-2 text-base text-muted">{c.details}</p>
            )}
            <span className="mt-4 inline-block rounded-full border-2 border-accent bg-accent px-5 py-2 text-sm font-bold uppercase tracking-wider text-white transition group-hover:border-accent-hover group-hover:bg-accent-hover">
              View Series
            </span>
          </div>
        </Link>
      ))}
    </div>
  );
}
