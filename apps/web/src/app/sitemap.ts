import type { MetadataRoute } from "next";

import { getStore, getStoreProducts, getStores } from "@/lib/public-api";

const site = () => (process.env.SITE_URL ?? "http://localhost:3000").replace(/\/$/, "");

// Depends on live data: render per request instead of freezing it at build time.
export const dynamic = "force-dynamic";

/** Every open store and its products, so search engines can find them. */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const entries: MetadataRoute.Sitemap = [{ url: `${site()}/`, changeFrequency: "weekly", priority: 1 }];

  for (const s of (await getStores())?.stores ?? []) {
    entries.push({ url: `${site()}/s/${s.slug}`, changeFrequency: "weekly", priority: 0.8 });

    if (await getStore(s.slug)) {
      for (const p of (await getStoreProducts(s.slug, {}))?.data ?? []) {
        entries.push({ url: `${site()}/s/${s.slug}/products/${p.id}`, changeFrequency: "weekly", priority: 0.6 });
      }
    }
  }

  return entries;
}
