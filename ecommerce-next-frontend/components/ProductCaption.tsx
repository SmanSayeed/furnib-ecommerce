"use client";

import { useEffect, useRef, useState } from "react";
import { trackViewContent } from "@/lib/track";

/** The product whose caption this is — used to fire `view_item` on "See more". */
type CaptionItem = { sku: string; name: string; price: number };

/**
 * Facebook-style post caption. Collapsed it is a FIXED two-line block, with an
 * inline "See more" sitting at the END of the second line (not on a third line)
 * — every card's caption is the same height, so the media below stays aligned.
 * "See more" only appears when the text actually overflows two lines.
 *
 * Opening "See more" is our `view_item` signal (the storefront has no separate
 * product page), so the click fires ViewContent / view_item once.
 */
export function ProductCaption({
  text,
  item,
}: {
  text: string;
  item?: CaptionItem;
}) {
  const [expanded, setExpanded] = useState(false);
  const [clamped, setClamped] = useState(false);
  const ref = useRef<HTMLParagraphElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    // Measured while collapsed: does the content exceed the 2-line clamp?
    setClamped(el.scrollHeight > el.clientHeight + 1);
  }, [text]);

  const expand = () => {
    setExpanded(true);
    if (item) trackViewContent(item);
  };

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
            onClick={expand}
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
