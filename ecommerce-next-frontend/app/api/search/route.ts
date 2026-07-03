import { NextRequest, NextResponse } from "next/server";
import { config } from "@/lib/config";

/**
 * Server-side proxy for the storefront header typeahead. Forwards the query to
 * the Laravel search endpoint and returns the (already public, image/name/price)
 * product list. Kept behind our own origin so the backend URL/keys never reach
 * the browser and the response can be cached at the edge if desired.
 */
export async function GET(request: NextRequest) {
  const q = (request.nextUrl.searchParams.get("q") ?? "").slice(0, 100);
  const limit = request.nextUrl.searchParams.get("limit") ?? "8";

  if (q.trim().length < 2) {
    return NextResponse.json({ data: [] });
  }

  const url = `${config.apiBaseUrl}/products?q=${encodeURIComponent(q)}&limit=${encodeURIComponent(limit)}`;

  const res = await fetch(url, {
    headers: { Accept: "application/json" },
    cache: "no-store",
  });

  const data = await res.json().catch(() => ({ data: [] }));

  return NextResponse.json(data, { status: res.ok ? 200 : res.status });
}
