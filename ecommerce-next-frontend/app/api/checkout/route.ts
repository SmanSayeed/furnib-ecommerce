import { NextRequest, NextResponse } from "next/server";
import { config } from "@/lib/config";

/**
 * Server-side proxy for placing an order. Keeps the browser same-origin (no
 * CORS) and forwards the real client IP so the backend can record it.
 */
export async function POST(request: NextRequest) {
  const body = await request.json().catch(() => null);

  if (body === null) {
    return NextResponse.json({ error: { code: "bad_request", message: "Invalid body." } }, { status: 400 });
  }

  const forwardedFor =
    request.headers.get("x-forwarded-for") ??
    request.headers.get("x-real-ip") ??
    "";

  // Forward Meta first-party cookies so the server-side Purchase (CAPI) can
  // match the browser Pixel — improves Event Match Quality for COD orders.
  const fbp = request.cookies.get("_fbp")?.value;
  const fbc = request.cookies.get("_fbc")?.value;
  const referer = request.headers.get("referer") ?? "";

  const res = await fetch(`${config.apiBaseUrl}/orders`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Forwarded-For": forwardedFor,
      ...(referer ? { Referer: referer } : {}),
      ...(fbp ? { "X-Fbp": fbp } : {}),
      ...(fbc ? { "X-Fbc": fbc } : {}),
    },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  const data = await res.json().catch(() => ({}));

  return NextResponse.json(data, { status: res.status });
}
