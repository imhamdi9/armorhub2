import type { Metadata, Viewport } from "next";
import { Geist_Mono, Inter, Noto_Sans_Arabic } from "next/font/google";
import "./globals.css";

// Inter carries the whole interface; weight 900 is the big display voice.
const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700", "900"],
});

// Geist has no Arabic glyphs; the browser falls back to this per character.
const notoArabic = Noto_Sans_Arabic({
  variable: "--font-arabic",
  subsets: ["arabic"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

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

export const viewport: Viewport = { themeColor: "#163300" };

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html
      lang="en"
      className={`${inter.variable} ${geistMono.variable} ${notoArabic.variable}`}
      suppressHydrationWarning
    >
      <body className="min-h-screen">
        {children}
      </body>
    </html>
  );
}