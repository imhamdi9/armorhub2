import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";

import { Catalog } from "@/components/public/catalog";
import { getStore, getStoreProducts } from "@/lib/public-api";

type Props = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ q?: string; category?: string; page?: string; industry?: string; sort?: string; vehicle_model_id?: string; year?: string; compatible?: string; min_price?: string; max_price?: string; brand?: string; min_rating?: string }>;
};

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { slug } = await params;
  const { q, category, page, sort } = await searchParams;
  const data = await getStore(slug);
  if (data?.merged_into) return { title: "ArmorHub" };
  if (!data) return { title: "Store not found" };

  const filtered = !!(q || category || sort || (page && page !== "1"));
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
  const { q = "", category = "", page = "", industry = "", sort: rawSort = "", vehicle_model_id: carModel = "", year: carYear = "", compatible = "", min_price: minPrice = "", max_price: maxPrice = "", brand = "", min_rating: minRating = "" } = await searchParams;
  const filters = {
    min_price: /^\d+(\.\d+)?$/.test(minPrice) ? minPrice : "", max_price: /^\d+(\.\d+)?$/.test(maxPrice) ? maxPrice : "",
    brand: brand.slice(0, 200), min_rating: /^[1-5]$/.test(minRating) ? minRating : "",
  };
  // "Only what fits my car": the model and year travel in the address, so the list stays a plain server-rendered page.
  const fit = /^\d+$/.test(carModel) && /^\d{4}$/.test(carYear) && compatible === "1" ? { vehicle_model_id: carModel, year: carYear, compatible: "1" } : undefined;
  const sort = ["price_asc", "price_desc", "name", "rating"].includes(rawSort) ? rawSort : "";
  const first = await getStore(slug);
  if (first?.merged_into) redirect(`/s/${first.merged_into}/products${industry ? `?industry=${encodeURIComponent(industry)}` : ""}`);
  const [store, result] = await Promise.all([Promise.resolve(first), getStoreProducts(slug, { q, category, page, industry, sort, ...filters, ...(fit ?? {}) })]);

  if (!store) notFound();

  return <Catalog store={store.store} result={result} categories={store.categories} counts={store.category_counts} industries={store.industries ?? []} industryCounts={store.industry_counts ?? {}} industry={industry} q={q} category={category} sort={sort} fit={fit} filters={filters} />;
}
