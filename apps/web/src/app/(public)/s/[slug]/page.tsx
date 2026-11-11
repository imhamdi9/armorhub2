import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { StoreHome } from "@/components/public/store-home";
import { getStore } from "@/lib/public-api";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const data = await getStore(slug);

  if (!data) return { title: "Store not found" };

  return {
    title: data.store.name,
    description: data.store.headline ?? data.store.subheadline ?? `Services from ${data.store.name}`,
    openGraph: { title: data.store.name, description: data.store.headline ?? undefined, type: "website" },
  };
}

export default async function StorePage({ params }: Props) {
  const { slug } = await params;
  const data = await getStore(slug);

  if (!data) notFound();

  return <StoreHome data={data} />;
}
