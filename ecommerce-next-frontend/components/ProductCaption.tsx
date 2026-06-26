"use client";

import { useEffect, useRef, useState } from "react";

/**
 * Facebook-style post caption: clamps to two lines and reveals a "See more"
 * toggle only when the text actually overflows.
 */
export function ProductCaption({ text }: { text: string }) {
  const [expanded, setExpanded] = useState(false);
  const [clamped, setClamped] = useState(false);
  const ref = useRef<HTMLParagraphElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    // Measured while collapsed: does the content exceed the 2-line clamp?
    setClamped(el.scrollHeight > el.clientHeight + 1);
  }, [text]);

  return (
    <div className="px-3 sm:px-4">
      <p
        ref={ref}
        className={`whitespace-pre-line text-sm leading-relaxed text-foreground ${
          expanded ? "" : "line-clamp-2"
        }`}
      >
        {text}
      </p>
      {(clamped || expanded) && (
        <button
          type="button"
          onClick={() => setExpanded((v) => !v)}
          className="mt-0.5 text-sm font-medium text-muted transition hover:text-foreground"
        >
          {expanded ? "See less" : "See more"}
        </button>
      )}
    </div>
  );
}
