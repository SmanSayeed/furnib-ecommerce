"use client";

import { useEffect, useRef } from "react";
import { trackViewContent } from "@/lib/track";

/**
 * Fires a single ViewContent / view_item when a product landing page is shown.
 * Renders nothing.
 */
export function ProductView({
  sku,
  name,
  price,
}: {
  sku: string;
  name?: string;
  price: number;
}) {
  const fired = useRef(false);

  useEffect(() => {
    if (fired.current) return;
    fired.current = true;
    trackViewContent({ sku, name, price });
  }, [sku, name, price]);

  return null;
}
