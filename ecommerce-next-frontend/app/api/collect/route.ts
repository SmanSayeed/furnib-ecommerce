import { NextRequest, NextResponse } from "next/server";
import { config } from "@/lib/config";

/**
 * Same-origin proxy for the server-side tagging beacon. Keeps the browser CORS-
 * free and forwards the real client IP + the Meta first-party cookies so the
 * Laravel CAPI sender can build a high-quality match. Always returns quickly and
 * never throws back to the shopper.
 */
export async function POST(request: NextRequest) {
  const body = await request.json().catch(() => null);

  if (body === null) {
    return NextResponse.json({ recorded: false }, { status: 400 });
  }

  // Real visitor IP — Cloudflare's CF-Connecting-IP is authoritative; fall back
  // to x-real-ip, then the first hop of x-forwarded-for.
  const forwardedFor =
    request.headers.get("cf-connecting-ip") ??
    request.headers.get("x-real-ip") ??
    request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ??
    "";

  // Prefer the cookies the browser actually holds (the body copy is best-effort).
  const fbp = body.fbp ?? request.cookies.get("_fbp")?.value;
  const fbc = body.fbc ?? request.cookies.get("_fbc")?.value;
  const ttp = body.ttp ?? request.cookies.get("_ttp")?.value;
  const ttclid = body.ttclid ?? request.cookies.get("ttclid")?.value;

  try {
    const res = await fetch(`${config.apiBaseUrl}/collect`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Forwarded-For": forwardedFor,
        ...(fbp ? { "X-Fbp": fbp } : {}),
        ...(fbc ? { "X-Fbc": fbc } : {}),
        ...(ttp ? { "X-Ttp": ttp } : {}),
        ...(ttclid ? { "X-Ttclid": ttclid } : {}),
      },
      body: JSON.stringify({ ...body, fbp, fbc, ttp, ttclid }),
      cache: "no-store",
    });
    const data = await res.json().catch(() => ({ recorded: false }));
    return NextResponse.json(data, { status: res.status });
  } catch {
    return NextResponse.json({ recorded: false }, { status: 200 });
  }
}
