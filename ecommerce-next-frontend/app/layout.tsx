import type { Metadata } from "next";
import { Geist } from "next/font/google";
import "./globals.css";
import { Footer } from "@/components/Footer";
import { FloatingNav } from "@/components/FloatingNav";
import { getCategories } from "@/lib/api";
import { config } from "@/lib/config";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: {
    default: `${config.siteName} — ${config.tagline}`,
    template: `%s — ${config.siteName}`,
  },
  description:
    "Elegant and refined furniture for modern living and professional spaces. Ready stock with fast delivery.",
  openGraph: {
    title: `${config.siteName} — ${config.tagline}`,
    siteName: config.siteName,
    type: "website",
  },
};

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const categories = await getCategories();

  return (
    <html lang="en" className={`${geistSans.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col">
        <main className="flex-1">{children}</main>
        <Footer />
        <FloatingNav categories={categories} />
      </body>
    </html>
  );
}
