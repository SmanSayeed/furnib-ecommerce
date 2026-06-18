"use client";

import { useState } from "react";
import { SafeImage } from "./SafeImage";

export type Slide = { url: string | null; alt: string };

export function ImageSlider({ slides, title }: { slides: Slide[]; title: string }) {
  const items = slides.length ? slides : [{ url: null, alt: title }];
  const [index, setIndex] = useState(0);
  const go = (delta: number) =>
    setIndex((prev) => (prev + delta + items.length) % items.length);

  return (
    <div>
      <div className="relative aspect-square w-full overflow-hidden rounded-2xl border border-border bg-surface">
        <SafeImage
          src={items[index].url}
          alt={items[index].alt}
          className="h-full w-full object-cover"
        />
        {items.length > 1 && (
          <>
            <button
              type="button"
              onClick={() => go(-1)}
              aria-label="Previous image"
              className="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-2xl text-white backdrop-blur transition hover:bg-black/70"
            >
              ‹
            </button>
            <button
              type="button"
              onClick={() => go(1)}
              aria-label="Next image"
              className="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-2xl text-white backdrop-blur transition hover:bg-black/70"
            >
              ›
            </button>
          </>
        )}
      </div>

      {items.length > 1 && (
        <div className="mt-3 flex gap-2 overflow-x-auto pb-1">
          {items.map((s, idx) => (
            <button
              type="button"
              key={idx}
              onClick={() => setIndex(idx)}
              aria-label={`View image ${idx + 1}`}
              className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 transition ${
                idx === index ? "border-accent" : "border-border opacity-70"
              }`}
            >
              <SafeImage src={s.url} alt={s.alt} className="h-full w-full object-cover" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
