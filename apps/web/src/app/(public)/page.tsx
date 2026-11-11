import type { Metadata } from "next";

import { PlatformHome } from "@/components/public/platform-home";
import { getShowcase, getStores } from "@/lib/public-api";

export const metadata: Metadata = {
  title: "ArmorHub — trusted services, quoted by experts near you",
  description:
    "A marketplace connecting customers with trusted service providers across automotive, home services, beauty and devices.",
};

// Depends on live data: render per request instead of freezing it at build time.
export const dynamic = "force-dynamic";

export default async function HomePage() {
  const [list, showcase] = await Promise.all([getStores(), getShowcase()]);

  return <PlatformHome stores={list?.stores ?? []} showcase={showcase ?? { industries: [] }} />;
}
