import type { Metadata } from "next";
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

export const metadata: Metadata = {
  title: "ArmorHub",
  description: "Service marketplace platform for automotive, home services, beauty and devices",
};

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