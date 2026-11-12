import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { Catalog } from "@/components/public/catalog";
import { getStore, getStoreProducts } from "@/lib/public-api";

type Props = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ q?: string; category?: string; page?: string }>;
};

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { slug } = await params;
  const { q, category, page } = await searchParams;
  const data = await getStore(slug);
  if (!data) return { title: "Store not found" };

  const filtered = !!(q || category || (page && page !== "1"));
  const title = `Products — ${data.store.name}`;

  return {
    title,
    description: `Browse everything ${data.store.name} offers, with prices.`,
    // Every search, filter and page 2+ is a different view of the same catalogue: only the plain list is worth indexing.
    alternates: { canonical: `/s/${slug}/products` },
    ...(filtered ? { robots: { index: false, follow: true } } : {}),
    openGraph: { title, type: "website", url: `/s/${slug}/products` },
  };
}

export default async function ProductsPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const { q = "", category = "", page = "" } = await searchParams;
  const [store, result] = await Promise.all([getStore(slug), getStoreProducts(slug, { q, category, page })]);

  if (!store) notFound();

  return <Catalog store={store.store} result={result} categories={store.categories} counts={store.category_counts} q={q} category={category} />;
}
