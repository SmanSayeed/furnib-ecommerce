"use client";

import { useEffect, useRef, useState } from "react";
import { trackViewContent } from "@/lib/track";

/** The product whose caption this is — used to fire `view_item` on "See more". */
type CaptionItem = { sku: string; name: string; price: number };

/**
 * Facebook-style post caption. Collapsed it clamps to two lines (an ellipsis ends
 * line 2), and when the text overflows a "See more" appears on its OWN line below,
 * with a gap — never overlaid on the text (an overlay bled the clipped words
 * through on the semi-transparent card). The caption block reserves a min height so
 * one-line captions don't collapse the card and the grid stays tidy.
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
    <div className="flex min-h-[2.6em] flex-col gap-1.5 px-3 sm:px-4">
      <p
        ref={ref}
        className={`whitespace-pre-line text-sm leading-relaxed text-foreground ${
          expanded ? "" : "line-clamp-2"
        }`}
      >
        {text}
      </p>

      {/* A normal block on its own line, left-aligned, with a gap above — no
          overlay, so nothing bleeds through the semi-transparent card. py-1
          gives the tap target height on top of the line box. */}
      {clamped && !expanded && (
        <button
          type="button"
          onClick={expand}
          className="self-start py-1 text-sm font-semibold text-accent underline-offset-2 hover:underline"
        >
          See more
        </button>
      )}

      {expanded && (
        <button
          type="button"
          onClick={() => setExpanded(false)}
          className="self-start py-1 text-sm font-semibold text-muted transition hover:text-accent"
        >
          See less
        </button>
      )}
    </div>
  );
}
