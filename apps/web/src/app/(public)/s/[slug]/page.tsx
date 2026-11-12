import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { ShopStoreHome } from "@/components/shop/shop-store-home";
import { getStore } from "@/lib/public-api";
import { absoluteImage, plain } from "@/lib/seo";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const data = await getStore(slug);

  if (!data) return { title: "Store not found" };

  const description = plain(data.store.subheadline ?? data.store.headline) ?? `Services from ${data.store.name}`;
  const image = absoluteImage(data.store.hero_image_url ?? data.store.logo_url);

  return {
    title: data.store.name,
    description,
    alternates: { canonical: `/s/${slug}` },
    openGraph: { title: data.store.name, description, type: "website", url: `/s/${slug}`, ...(image ? { images: [image] } : {}) },
    twitter: { card: image ? "summary_large_image" : "summary", title: data.store.name, description, ...(image ? { images: [image] } : {}) },
  };
}

export default async function StorePage({ params }: Props) {
  const { slug } = await params;
  const data = await getStore(slug);

  if (!data) notFound();

  return <ShopStoreHome data={data} />;
}
