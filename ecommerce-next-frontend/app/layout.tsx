import type { Metadata } from "next";
import { Geist } from "next/font/google";
import "./globals.css";
import { Analytics } from "@/components/analytics/Analytics";
import { ConsentBanner } from "@/components/analytics/ConsentBanner";
import { CategoryDrawer } from "@/components/CategoryDrawer";
import { FloatingActions } from "@/components/FloatingActions";
import { Footer } from "@/components/Footer";
import { Header } from "@/components/Header";
import { MobileTabBar } from "@/components/MobileTabBar";
import { ThemeProvider } from "@/components/ThemeProvider";
import { getCategories, getSettings } from "@/lib/api";
import { config } from "@/lib/config";
import { getMarketing } from "@/lib/marketing";

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
  const [categories, settings, marketing] = await Promise.all([
    getCategories(),
    getSettings(),
    getMarketing(),
  ]);
  const analyticsEnabled = Boolean(marketing.gtm_id);

  return (
    <html
      lang="en"
      suppressHydrationWarning
      className={`${geistSans.variable} h-full antialiased`}
    >
      <body className="flex min-h-full flex-col">
        <Analytics marketing={marketing} />
        <ThemeProvider>
          <Header
            logoLight={settings?.logo_light}
            logoDark={settings?.logo_dark}
          />
          <main className="mx-auto w-full max-w-[1600px] flex-1 px-4 pb-16 sm:px-6 lg:px-8 md:pb-0">
            {children}
          </main>
          <Footer settings={settings} />
          <CategoryDrawer
            categories={categories}
            logoLight={settings?.logo_light}
            logoDark={settings?.logo_dark}
          />
          <MobileTabBar whatsapp={settings?.whatsapp} />
          <FloatingActions whatsapp={settings?.whatsapp} />
          <ConsentBanner enabled={analyticsEnabled} />
        </ThemeProvider>
      </body>
    </html>
  );
}
