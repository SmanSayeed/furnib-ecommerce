"use client";

import { useEffect, useRef, useState } from "react";
import { trackViewContent } from "@/lib/track";

/** The product whose caption this is — used to fire `view_item` on "See more". */
type CaptionItem = { sku: string; name: string; price: number };

const MAX_LINES = 2;
const ELLIPSIS = "… ";
const MORE = "See more";
// Bold "See more" is a touch wider than the normal-weight text we measure with,
// so reserve a small safety margin to keep it from wrapping to a 3rd line.
const MEASURE_TAIL = ELLIPSIS + MORE + "  ";

/**
 * Facebook-style post caption. Collapsed it shows at most two lines and, when the
 * text overflows, the text is truncated so an inline "… See more" sits at the END
 * of line 2 (never on a 3rd line, never an overlay that could bleed clipped words
 * through the semi-transparent card). The cut point is found by measuring: we
 * binary-search the longest prefix whose "{prefix}… See more" still fits two lines.
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
  // undefined = not measured yet (render a plain 2-line clamp to avoid a flash);
  // null = the whole text fits; string = the truncated prefix to show inline.
  const [truncated, setTruncated] = useState<string | null | undefined>(undefined);
  const measureRef = useRef<HTMLParagraphElement>(null);

  useEffect(() => {
    if (expanded) return;

    const measure = () => {
      const el = measureRef.current;
      if (!el) return;

      const lineHeight = parseFloat(getComputedStyle(el).lineHeight) || 20;
      const maxHeight = lineHeight * MAX_LINES + 1;

      // Does the full text already fit in two lines?
      el.textContent = text;
      if (el.scrollHeight <= maxHeight) {
        setTruncated(null);
        return;
      }

      // Largest prefix length such that "{prefix}… See more" fits two lines.
      let lo = 0;
      let hi = text.length;
      let best = 0;
      while (lo <= hi) {
        const mid = (lo + hi) >> 1;
        el.textContent = text.slice(0, mid) + MEASURE_TAIL;
        if (el.scrollHeight <= maxHeight) {
          best = mid;
          lo = mid + 1;
        } else {
          hi = mid - 1;
        }
      }

      setTruncated(text.slice(0, best).replace(/\s+$/, ""));
    };

    measure();
    window.addEventListener("resize", measure);
    return () => window.removeEventListener("resize", measure);
  }, [text, expanded]);

  const expand = () => {
    setExpanded(true);
    if (item) trackViewContent(item);
  };

  const base = "whitespace-pre-line text-sm leading-relaxed text-foreground";

  return (
    <div className="min-h-[3.1em] px-3 sm:px-4">
      {/* Hidden, zero-height measurer at the exact content width + type. */}
      <p ref={measureRef} aria-hidden className={`block h-0 overflow-hidden ${base}`} />

      {expanded ? (
        <p className={base}>
          {text}{" "}
          <button
            type="button"
            onClick={() => setExpanded(false)}
            className="font-semibold text-muted transition hover:text-accent"
          >
            See less
          </button>
        </p>
      ) : truncated === undefined ? (
        // Pre-measurement: a plain 2-line clamp so there's no flash of full text.
        <p className={`${base} line-clamp-2`}>{text}</p>
      ) : truncated === null ? (
        <p className={base}>{text}</p>
      ) : (
        <p className={base}>
          {truncated}
          <span aria-hidden>{ELLIPSIS}</span>
          <button
            type="button"
            onClick={expand}
            className="font-semibold text-accent underline-offset-2 hover:underline"
          >
            {MORE}
          </button>
        </p>
      )}
    </div>
  );
}
