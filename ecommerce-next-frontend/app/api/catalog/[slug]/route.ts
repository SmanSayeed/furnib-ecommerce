import { NextRequest, NextResponse } from "next/server";
import { getCategory } from "@/lib/api";

export async function GET(
  request: NextRequest,
  context: { params: Promise<{ slug: string }> },
) {
  const { slug } = await context.params;
  const page = Number(request.nextUrl.searchParams.get("page") ?? "1");
  const data = await getCategory(slug, Number.isFinite(page) ? page : 1);

  if (!data) {
    return NextResponse.json({ error: "not_found" }, { status: 404 });
  }

  return NextResponse.json({ products: data.products, meta: data.meta });
}
