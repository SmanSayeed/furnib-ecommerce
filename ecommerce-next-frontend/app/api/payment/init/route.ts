import { NextRequest, NextResponse } from "next/server";
import { config } from "@/lib/config";

/**
 * Server-side proxy to start an SSLCommerz payment session. Returns the gateway
 * redirect URL the browser should navigate to.
 */
export async function POST(request: NextRequest) {
  const body = await request.json().catch(() => null);

  if (body === null) {
    return NextResponse.json({ error: { code: "bad_request", message: "Invalid body." } }, { status: 400 });
  }

  const res = await fetch(`${config.apiBaseUrl}/payment/ssl/init`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
    cache: "no-store",
  });

  const data = await res.json().catch(() => ({}));

  return NextResponse.json(data, { status: res.status });
}
