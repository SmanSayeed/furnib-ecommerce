"use client";

import { useEffect, useState } from "react";

type Banner = { desktop: string; mobile: string };

export function BannerCarousel({ banners }: { banners: Banner[] }) {
  const [index, setIndex] = useState(0);
  const count = banners.length;

  useEffect(() => {
    if (count <= 1) return;
    const id = setInterval(() => setIndex((i) => (i + 1) % count), 5000);
    return () => clearInterval(id);
  }, [count]);

  if (count === 0) return null;

  const go = (delta: number) => setIndex((i) => (i + delta + count) % count);

  return (
    <section className="relative mt-3 w-full overflow-hidden rounded-card border border-border">
      <div className="relative aspect-[4/5] w-full sm:aspect-[9/2]">
        {banners.map((b, i) => (
          <picture
            key={i}
            className={`absolute inset-0 h-full w-full transition-opacity duration-700 ${
              i === index ? "opacity-100" : "opacity-0"
            }`}
          >
            <source media="(min-width:768px)" srcSet={b.desktop} />
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={b.mobile}
              alt={`Banner ${i + 1}`}
              className="h-full w-full object-cover"
            />
          </picture>
        ))}
      </div>

      {count > 1 && (
        <>
          <button
            type="button"
            onClick={() => go(-1)}
            aria-label="Previous banner"
            className="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-2xl text-white backdrop-blur transition hover:bg-black/60"
          >
            ‹
          </button>
          <button
            type="button"
            onClick={() => go(1)}
            aria-label="Next banner"
            className="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-2xl text-white backdrop-blur transition hover:bg-black/60"
          >
            ›
          </button>
          <div className="absolute bottom-3 left-1/2 flex -translate-x-1/2 gap-2">
            {banners.map((_, i) => (
              <button
                key={i}
                type="button"
                onClick={() => setIndex(i)}
                aria-label={`Go to banner ${i + 1}`}
                className={`h-2 rounded-full transition-all ${
                  i === index ? "w-6 bg-white" : "w-2 bg-white/50"
                }`}
              />
            ))}
          </div>
        </>
      )}
    </section>
  );
}
