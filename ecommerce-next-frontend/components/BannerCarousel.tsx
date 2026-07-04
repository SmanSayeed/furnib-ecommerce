"use client";

import { useEffect, useState } from "react";

type Banner = { desktop: string | null; mobile: string | null };

export function BannerCarousel({ banners }: { banners: Banner[] }) {
  // Split the banners per device. A banner only appears on a device when it has
  // an image for that device: the API already falls a desktop-only banner back
  // to the wide image on mobile (mobile = mobile ?? desktop), but a mobile-only
  // banner has no desktop image, so it is filtered out of the desktop carousel
  // and never leaks onto desktop screens.
  const desktopSlides = banners
    .map((b) => b.desktop)
    .filter((src): src is string => Boolean(src));
  const mobileSlides = banners
    .map((b) => b.mobile)
    .filter((src): src is string => Boolean(src));

  if (desktopSlides.length === 0 && mobileSlides.length === 0) return null;

  return (
    <>
      {/* Desktop (≥768px): wide banners only. */}
      <Slides slides={desktopSlides} className="hidden aspect-9/2 md:block" />
      {/* Mobile (<768px): portrait banners only. */}
      <Slides slides={mobileSlides} className="aspect-4/5 md:hidden" />
    </>
  );
}

function Slides({ slides, className }: { slides: string[]; className: string }) {
  const [index, setIndex] = useState(0);
  const count = slides.length;

  useEffect(() => {
    if (count <= 1) return;
    const id = setInterval(() => setIndex((i) => (i + 1) % count), 5000);
    return () => clearInterval(id);
  }, [count]);

  if (count === 0) return null;

  const go = (delta: number) => setIndex((i) => (i + delta + count) % count);

  return (
    <section
      className={`relative mt-3 w-full overflow-hidden rounded-card border border-border ${className}`}
    >
      <div className="relative h-full w-full">
        {slides.map((src, i) => (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            key={i}
            src={src}
            alt={`Banner ${i + 1}`}
            className={`absolute inset-0 h-full w-full object-cover transition-opacity duration-700 ${
              i === index ? "opacity-100" : "opacity-0"
            }`}
          />
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
            {slides.map((_, i) => (
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
