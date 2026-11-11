import type { Metadata } from "next";

import { PlatformHome } from "@/components/public/platform-home";
import { getStores } from "@/lib/public-api";

export const metadata: Metadata = {
  title: "ArmorHub — trusted services, quoted by experts near you",
  description:
    "A marketplace connecting customers with trusted service providers across automotive, home services, beauty and devices.",
};

// Depends on live data: render per request instead of freezing it at build time.
export const dynamic = "force-dynamic";

export default async function HomePage() {
  const list = await getStores();

  return <PlatformHome stores={list?.stores ?? []} />;
}
