import type { Metadata, Viewport } from "next";
import "@fontsource-variable/inter";
import "@fontsource-variable/noto-sans-arabic";
import "./globals.css";

// Fonts are self-hosted (npm packages), so builds and pages never depend on reaching Google at runtime.
// Inter carries the interface and headings, Noto Sans Arabic covers Arabic.

// Relative addresses in metadata (images, canonical links) resolve against the real public address when SITE_URL is set.
const SITE = (process.env.SITE_URL ?? "").replace(/\/$/, "");

export const metadata: Metadata = {
  ...(SITE ? { metadataBase: new URL(SITE) } : {}),
  title: "ArmorHub",
  description: "Service marketplace platform for automotive, home services, beauty and devices",
  applicationName: "ArmorHub",
  openGraph: { siteName: "ArmorHub", type: "website" },
  twitter: { card: "summary_large_image" },
};

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