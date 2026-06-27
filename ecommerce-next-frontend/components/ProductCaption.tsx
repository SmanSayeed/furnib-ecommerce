"use client";

import { useEffect, useRef, useState } from "react";

/**
 * Facebook-style post caption. Collapsed it occupies a FIXED two-line block
 * (plus a reserved "See more" row) so every card's media starts at the same Y
 * — cards in a grid stay aligned regardless of caption length. "See more"
 * appears only when the text actually overflows two lines.
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
          expanded ? "" : "line-clamp-2 min-h-[2.6em]"
        }`}
      >
        {text}
      </p>
      {/* Reserved row so a clamped caption and a short one are the same height. */}
      <div className="h-5">
        {(clamped || expanded) && (
          <button
            type="button"
            onClick={() => setExpanded((v) => !v)}
            className="text-sm font-semibold text-muted transition hover:text-accent"
          >
            {expanded ? "See less" : "See more"}
          </button>
        )}
      </div>
    </div>
  );
}
