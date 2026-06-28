"use client";

import { useEffect, useRef, useState } from "react";

/**
 * Facebook-style post caption. Collapsed it is a FIXED two-line block, with an
 * inline "See more" sitting at the END of the second line (not on a third line)
 * — every card's caption is the same height, so the media below stays aligned.
 * "See more" only appears when the text actually overflows two lines.
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
      <div className="relative">
        <p
          ref={ref}
          className={`whitespace-pre-line text-sm leading-relaxed text-foreground ${
            expanded ? "" : "line-clamp-2 min-h-[2.6em]"
          }`}
        >
          {text}
        </p>

        {/* Inline at the end of line 2 — a left-fading mask hides the clipped
            word so "See more" reads cleanly instead of dropping to a 3rd line. */}
        {clamped && !expanded && (
          <button
            type="button"
            onClick={() => setExpanded(true)}
            className="absolute right-0 bottom-0 flex items-center bg-gradient-to-l from-surface from-40% to-transparent pl-10 text-sm font-semibold text-accent"
          >
            … See more
          </button>
        )}
      </div>

      {expanded && (
        <button
          type="button"
          onClick={() => setExpanded(false)}
          className="mt-1 text-sm font-semibold text-muted transition hover:text-accent"
        >
          See less
        </button>
      )}
    </div>
  );
}
