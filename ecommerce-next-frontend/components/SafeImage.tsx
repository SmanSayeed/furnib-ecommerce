"use client";

import { useState } from "react";

export function SafeImage({
  src,
  alt,
  className = "",
}: {
  src: string | null;
  alt: string;
  className?: string;
}) {
  const [failed, setFailed] = useState(false);

  if (!src || failed) {
    return (
      <div
        className={`flex items-center justify-center bg-gradient-to-br from-surface-2 to-surface text-muted ${className}`}
        aria-label={alt}
      >
        <span className="px-3 text-center text-xs uppercase tracking-widest opacity-60">
          {alt || "Furnib.com"}
        </span>
      </div>
    );
  }

  return (
    // eslint-disable-next-line @next/next/no-img-element
    <img
      src={src}
      alt={alt}
      loading="lazy"
      onError={() => setFailed(true)}
      className={className}
    />
  );
}
