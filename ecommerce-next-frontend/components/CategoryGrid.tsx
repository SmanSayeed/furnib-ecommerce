import Link from "next/link";
import { imageUrl } from "@/lib/image";
import type { Category } from "@/lib/types";
import { SafeImage } from "./SafeImage";

export function CategoryGrid({ categories }: { categories: Category[] }) {
  return (
    <div className="grid gap-6 sm:grid-cols-2">
      {categories.map((c) => (
        <Link
          key={c.id}
          href={`/category/${c.slug}`}
          className="group overflow-hidden rounded-card border border-border bg-surface transition hover:border-accent/50"
        >
          <div className="aspect-[16/10] overflow-hidden">
            <SafeImage
              src={imageUrl(c.header_image ?? c.thumbnail_image)}
              alt={c.title}
              className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            />
          </div>
          <div className="p-5">
            <h3 className="text-xl font-semibold">{c.title}</h3>
            {c.details && (
              <p className="mt-1 line-clamp-2 text-sm text-muted">{c.details}</p>
            )}
            <span className="mt-4 inline-block rounded-full border border-border px-4 py-1.5 text-xs font-medium uppercase tracking-wider transition group-hover:border-accent group-hover:text-accent">
              View Series
            </span>
          </div>
        </Link>
      ))}
    </div>
  );
}
