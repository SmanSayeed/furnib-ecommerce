import type { Metadata } from "next";
import { Geist } from "next/font/google";
import "./globals.css";
import { CategoryDrawer } from "@/components/CategoryDrawer";
import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { MobileTabBar } from "@/components/MobileTabBar";
import { ThemeProvider } from "@/components/ThemeProvider";
import { getCategories, getSettings } from "@/lib/api";
import { config } from "@/lib/config";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

export async function generateMetadata(): Promise<Metadata> {
  const settings = await getSettings();
  const name = settings?.site_name || config.siteName;
  const tagline = settings?.tagline || config.tagline;

  return {
    title: {
      default: `${name} — ${tagline}`,
      template: `%s — ${name}`,
    },
    description:
      "Elegant and refined furniture for modern living and professional spaces. Ready stock with fast delivery.",
    icons: { icon: settings?.favicon || "/logo/furnib-favicon.png" },
    openGraph: {
      title: `${name} — ${tagline}`,
      siteName: name,
      type: "website",
    },
  };
}

export default async function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  const [categories, settings] = await Promise.all([
    getCategories(),
    getSettings(),
  ]);

  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={`${geistSans.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">
        <ThemeProvider>
          <Header
            logoLight={settings?.logo_light}
            logoDark={settings?.logo_dark}
            whatsapp={settings?.whatsapp}
          />
          <main className="flex-1 pb-16 md:pb-0">{children}</main>
          <Footer />
          <CategoryDrawer categories={categories} />
          <MobileTabBar whatsapp={settings?.whatsapp} />
        </ThemeProvider>
      </body>
    </html>
  );
}
