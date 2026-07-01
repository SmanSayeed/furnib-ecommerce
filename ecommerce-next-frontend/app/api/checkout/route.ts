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

  // Real visitor IP — Cloudflare's CF-Connecting-IP is authoritative; fall back
  // to x-real-ip, then the first hop of x-forwarded-for. Forwarded as a single
  // clean value so the backend records the customer's IP, not an edge IP.
  const forwardedFor =
    request.headers.get("cf-connecting-ip") ??
    request.headers.get("x-real-ip") ??
    request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ??
    "";

  // Forward first-party attribution cookies so the server-side conversions
  // (Meta CAPI, TikTok Events API, GA4 Measurement Protocol) can match the
  // browser pixels — improves match quality, incl. for admin-confirmed COD.
  const fbp = request.cookies.get("_fbp")?.value;
  const fbc = request.cookies.get("_fbc")?.value;
  const ttp = request.cookies.get("_ttp")?.value;
  const ttclid = request.cookies.get("ttclid")?.value;
  // GA4 client id lives inside the _ga cookie: "GA1.1.<id1>.<id2>" → "<id1>.<id2>".
  const ga = request.cookies.get("_ga")?.value;
  const gaClientId = ga ? ga.split(".").slice(-2).join(".") : undefined;
  const referer = request.headers.get("referer") ?? "";

  // Compliance: the customer must accept the legal terms at checkout. We forward
  // the whole body verbatim (so terms_accepted, items, customer, etc. all pass
  // through) but pin terms_accepted explicitly so it can never be dropped —
  // Laravel's StoreOrderRequest enforces it (rule `accepted`, 422 if missing).
  const forwardedBody = {
    ...body,
    terms_accepted: body?.terms_accepted === true,
  };

  const res = await fetch(`${config.apiBaseUrl}/orders`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      "X-Forwarded-For": forwardedFor,
      ...(referer ? { Referer: referer } : {}),
      ...(fbp ? { "X-Fbp": fbp } : {}),
      ...(fbc ? { "X-Fbc": fbc } : {}),
      ...(ttp ? { "X-Ttp": ttp } : {}),
      ...(ttclid ? { "X-Ttclid": ttclid } : {}),
      ...(gaClientId ? { "X-Ga-Client-Id": gaClientId } : {}),
    },
    body: JSON.stringify(forwardedBody),
    cache: "no-store",
  });

  const data = await res.json().catch(() => ({}));

  return NextResponse.json(data, { status: res.status });
}
