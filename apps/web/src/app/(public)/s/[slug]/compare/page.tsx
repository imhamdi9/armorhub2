import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";

import { CompareView } from "@/components/shop/compare-view";
import { getStore } from "@/lib/public-api";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const data = await getStore(slug);

  // What is compared lives in the visitor's own browser, so this page has nothing to index.
  return { title: data ? `Compare — ${data.store.name}` : "Store not found", robots: { index: false, follow: true } };
}

export default async function ComparePage({ params }: Props) {
  const { slug } = await params;
  const data = await getStore(slug);
  if (data?.merged_into) redirect(`/s/${data.merged_into}/compare`);
  if (!data) notFound();

  return <CompareView store={data.store} />;
}
