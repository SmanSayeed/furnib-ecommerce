import { NextRequest, NextResponse } from "next/server";
import { config } from "@/lib/config";

/**
 * Server-side proxy for the self-service pay page. Forwards the order_no + signed
 * token to Laravel, which verifies the token (no IDOR) and returns only display
 * fields. The token stays server-verified — the client never sees a secret.
 */
export async function GET(request: NextRequest) {
  const order = request.nextUrl.searchParams.get("order") ?? "";
  const token = request.nextUrl.searchParams.get("t") ?? "";

  if (order === "" || token === "") {
    return NextResponse.json(
      { error: { code: "bad_request", message: "Missing order or token." } },
      { status: 400 },
    );
  }

  const res = await fetch(
    `${config.apiBaseUrl}/pay/${encodeURIComponent(order)}/summary?t=${encodeURIComponent(token)}`,
    {
      method: "GET",
      headers: { Accept: "application/json" },
      cache: "no-store",
    },
  );

  const data = await res.json().catch(() => ({}));

  return NextResponse.json(data, { status: res.status });
}
