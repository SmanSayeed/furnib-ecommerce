"use client";

import { useEffect, useRef } from "react";
import { trackViewContent } from "@/lib/track";

/**
 * Fires a single ViewContent / view_item when a product landing page is shown.
 * Renders nothing.
 */
export function ProductView({ sku, value }: { sku: string; value: number }) {
  const fired = useRef(false);

  useEffect(() => {
    if (fired.current) return;
    fired.current = true;
    trackViewContent({ sku, value });
  }, [sku, value]);

  return null;
}
