import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { getPage } from "@/lib/api";

export const revalidate = 60;

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const page = await getPage(slug);
  if (!page) return { title: "Not found" };
  return { title: page.title };
}

export default async function CmsPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const page = await getPage(slug);
  if (!page) notFound();

  return (
    <article className="mx-auto w-full max-w-3xl py-8 sm:py-12">
      <h1 className="text-3xl font-extrabold tracking-tight sm:text-4xl">
        {page.title}
      </h1>

      {page.body_html ? (
        // body_html is sanitised with HTMLPurifier on save (admin side), so the
        // stored markup is already XSS-safe to render here. Styling is applied
        // via scoped child selectors (no typography plugin needed).
        <div
          className="mt-6 leading-relaxed text-foreground [&_a]:text-accent [&_a]:underline [&_blockquote]:my-4 [&_blockquote]:border-l-4 [&_blockquote]:border-border [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-muted [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-bold [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_li]:my-1 [&_ol]:my-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:my-4 [&_strong]:font-semibold [&_ul]:my-4 [&_ul]:list-disc [&_ul]:pl-6"
          dangerouslySetInnerHTML={{ __html: page.body_html }}
        />
      ) : (
        <p className="mt-6 text-muted">This page has no content yet.</p>
      )}
    </article>
  );
}
