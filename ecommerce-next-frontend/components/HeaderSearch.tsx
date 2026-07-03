"use client";

import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";
import { imageUrl } from "@/lib/image";
import { trackInitiateCheckout, trackLead } from "@/lib/track";
import type { Product } from "@/lib/types";
import { whatsappInquiry } from "@/lib/whatsapp";
import { config } from "@/lib/config";
import { SafeImage } from "./SafeImage";
import { WhatsAppIcon } from "./WhatsAppIcon";

/**
 * Header typeahead. Debounced query → our /api/search proxy → dropdown of
 * matching products with an Order and (optionally) an Inquiry button. Purely a
 * discovery aid; the real product/checkout pages own the actual flows.
 */
export function HeaderSearch({
  whatsapp,
  inquiryEnabled = true,
}: {
  whatsapp?: string | null;
  inquiryEnabled?: boolean;
}) {
  const [term, setTerm] = useState("");
  const [results, setResults] = useState<Product[]>([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);

  const boxRef = useRef<HTMLDivElement | null>(null);
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Guards against out-of-order responses overwriting a newer query's results.
  const seq = useRef(0);

  // Debounced search (~300ms), driven by input changes rather than an effect so
  // there is no synchronous setState-in-effect. A query under 2 chars clears the
  // list immediately.
  const onChange = useCallback((value: string) => {
    setTerm(value);
    setOpen(true);
    if (debounce.current) clearTimeout(debounce.current);

    const q = value.trim();
    if (q.length < 2) {
      seq.current++;
      setResults([]);
      setLoading(false);
      return;
    }

    setLoading(true);
    const id = ++seq.current;
    debounce.current = setTimeout(async () => {
      try {
        const res = await fetch(`/api/search?q=${encodeURIComponent(q)}`, {
          cache: "no-store",
        });
        const data = await res.json();
        if (id === seq.current) {
          setResults(Array.isArray(data?.data) ? data.data : []);
        }
      } catch {
        if (id === seq.current) setResults([]);
      } finally {
        if (id === seq.current) setLoading(false);
      }
    }, 300);
  }, []);

  // Close on outside click or Escape.
  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onClick);
      document.removeEventListener("keydown", onKey);
    };
  }, []);

  const showPanel = open && term.trim().length >= 2;

  return (
    <div ref={boxRef} className="relative mx-2 w-full max-w-xl flex-1">
      <div className="relative">
        <svg
          className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          aria-hidden
        >
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.3-4.3" />
        </svg>
        <input
          type="search"
          value={term}
          onChange={(e) => onChange(e.target.value)}
          onFocus={() => setOpen(true)}
          placeholder="Search furniture…"
          aria-label="Search products"
          className="h-10 w-full rounded-full border border-border bg-surface pl-9 pr-4 text-sm outline-none transition focus:border-accent focus:ring-2 focus:ring-accent/30"
        />
      </div>

      {showPanel && (
        <div className="absolute left-0 right-0 top-12 z-50 overflow-hidden rounded-2xl border border-border bg-background shadow-xl">
          {loading && results.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-muted">Searching…</p>
          ) : results.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-muted">
              No products found for “{term.trim()}”.
            </p>
          ) : (
            <ul className="max-h-[70vh] divide-y divide-border overflow-auto">
              {results.map((product) => {
                const unit = product.discount_price ?? product.price;
                const productUrl = `${config.siteUrl}/product/${product.slug}`;
                const inquiryHref = whatsappInquiry(product, productUrl, whatsapp);

                return (
                  <li key={product.id} className="flex items-center gap-3 px-3 py-2.5">
                    <Link
                      href={`/product/${product.slug}`}
                      onClick={() => setOpen(false)}
                      className="flex min-w-0 flex-1 items-center gap-3"
                    >
                      <SafeImage
                        src={imageUrl(product.main_image)}
                        alt={product.title}
                        className="size-12 shrink-0 rounded-lg border border-border object-cover"
                      />
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium text-foreground">
                          {product.title}
                        </span>
                        <span className="mt-0.5 flex items-center gap-1.5 text-sm">
                          <span className="font-bold text-accent">{unit.formatted}</span>
                          {product.discount_price && (
                            <span className="text-xs text-muted line-through">
                              {product.price.formatted}
                            </span>
                          )}
                        </span>
                      </span>
                    </Link>

                    <div className="flex shrink-0 items-center gap-1.5">
                      {inquiryEnabled && (
                        <a
                          href={inquiryHref}
                          target="_blank"
                          rel="noopener noreferrer"
                          aria-label={`Inquiry about ${product.title} on WhatsApp`}
                          onClick={() =>
                            trackLead({
                              sku: product.sku,
                              name: product.title,
                              price: unit.display,
                            })
                          }
                          className="flex size-9 items-center justify-center rounded-full bg-[#25D366] text-white transition hover:brightness-110"
                        >
                          <WhatsAppIcon size={16} />
                        </a>
                      )}
                      <Link
                        href={`/checkout/${product.slug}?qty=1`}
                        onClick={() => {
                          setOpen(false);
                          trackInitiateCheckout({
                            sku: product.sku,
                            name: product.title,
                            price: unit.display,
                            qty: 1,
                          });
                        }}
                        className="rounded-full bg-accent px-4 py-2 text-xs font-bold whitespace-nowrap text-white transition hover:bg-accent-hover"
                      >
                        Order
                      </Link>
                    </div>
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
