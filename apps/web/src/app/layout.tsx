import type { Metadata, Viewport } from "next";
import "@fontsource-variable/inter";
import "@fontsource-variable/noto-sans-arabic";
import "./globals.css";
import { getSurface } from "@/lib/public-api";

// Fonts are self-hosted (npm packages), so builds and pages never depend on reaching Google at runtime.
// Inter carries the interface and headings, Noto Sans Arabic covers Arabic.

// Relative addresses in metadata (images, canonical links) resolve against the real public address when SITE_URL is set.
const SITE = (process.env.SITE_URL ?? "").replace(/\/$/, "");

/** The platform's name is whatever its owners set in the theme editor (ArmorHub until they change it). */
export async function generateMetadata(): Promise<Metadata> {
  const brand = ((await getSurface("site.brand").catch(() => ({}))) as Record<string, Record<string, unknown>>).en?.name;
  const name = typeof brand === "string" && brand.trim() ? brand.trim() : "ArmorHub";

  return {
    ...(SITE ? { metadataBase: new URL(SITE) } : {}),
    title: name,
    description: "A marketplace platform: any industry can open its own market",
    applicationName: name,
    openGraph: { siteName: name, type: "website" },
    twitter: { card: "summary_large_image" },
  };
}

export const viewport: Viewport = { themeColor: "#0b1b3a" };

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body className="min-h-screen">
        {children}
      </body>
    </html>
  );
}