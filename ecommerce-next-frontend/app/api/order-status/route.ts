import { NextRequest, NextResponse } from "next/server";
import { config } from "@/lib/config";

/**
 * Server-side proxy for the "what do I still owe?" lookup. Forwards order_no +
 * the shopper's own mobile to Laravel, which verifies ownership (no IDOR) and
 * returns only non-sensitive money fields.
 */
export async function POST(request: NextRequest) {
  const body = await request.json().catch(() => null);
  const orderNo = typeof body?.order_no === "string" ? body.order_no : "";
  const mobile = typeof body?.mobile === "string" ? body.mobile : "";

  if (orderNo === "" || mobile === "") {
    return NextResponse.json(
      { error: { code: "bad_request", message: "Missing order or mobile." } },
      { status: 400 },
    );
  }

  const res = await fetch(
    `${config.apiBaseUrl}/orders/${encodeURIComponent(orderNo)}/status`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ mobile }),
      cache: "no-store",
    },
  );

  const data = await res.json().catch(() => ({}));

  return NextResponse.json(data, { status: res.status });
}
