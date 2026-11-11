import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { ProductDetail } from "@/components/public/product-detail";
import { getStore, getStoreProduct } from "@/lib/public-api";

type Props = { params: Promise<{ slug: string; id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug, id } = await params;
  const data = await getStoreProduct(slug, id);

  if (!data) return { title: "Product not found" };

  return {
    title: `${data.product.name} — ${data.store.name}`,
    description: data.product.description ?? `${data.product.name} from ${data.store.name}`,
  };
}

export default async function ProductPage({ params }: Props) {
  const { slug, id } = await params;
  const [store, data] = await Promise.all([getStore(slug), getStoreProduct(slug, id)]);

  if (!store || !data) notFound();

  return <ProductDetail store={store.store} data={data} />;
}
