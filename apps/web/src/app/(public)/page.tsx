import type { Metadata } from "next";

import { PlatformHome } from "@/components/public/platform-home";
import { getShowcase, getStoresChecked, getSurface } from "@/lib/public-api";

/** The platform's own name (whatever its owners set) and a line that fits any industry. */
export async function generateMetadata(): Promise<Metadata> {
  const brand = ((await getSurface("site.brand").catch(() => ({}))) as Record<string, Record<string, unknown>>).en?.name;
  const name = typeof brand === "string" && brand.trim() ? brand.trim() : "ArmorHub";
  const title = `${name} — shop from trusted sellers in your market`;

  return {
    title: { absolute: title },
    description: "A marketplace platform where any industry can open its own market, with the same trusted way of buying, quoting and booking.",
    alternates: { canonical: "/" },
    openGraph: { title, type: "website", url: "/" },
  };
}

// Depends on live data: render per request instead of freezing it at build time.
export const dynamic = "force-dynamic";

export default async function HomePage() {
  const [list, showcase, home, brand, nav] = await Promise.all([getStoresChecked(), getShowcase(), getSurface("site.home"), getSurface("site.brand"), getSurface("site.navigation")]);

  return <PlatformHome stores={list.stores} unavailable={list.down} showcase={showcase ?? { industries: [] }} site={{ home, brand, nav }} />;
}
