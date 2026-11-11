import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { Catalog } from "@/components/public/catalog";
import { getStore, getStoreProducts } from "@/lib/public-api";

type Props = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ q?: string; category?: string; page?: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const data = await getStore(slug);

  return { title: data ? `Products — ${data.store.name}` : "Store not found" };
}

export default async function ProductsPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const { q = "", category = "", page = "" } = await searchParams;
  const [store, result] = await Promise.all([getStore(slug), getStoreProducts(slug, { q, category, page })]);

  if (!store) notFound();

  return <Catalog store={store.store} result={result} categories={store.categories} q={q} category={category} />;
}
