import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { SellerPageView } from "@/components/public/seller-page";
import { getSeller } from "@/lib/public-api";

type Props = { params: Promise<{ slug: string; id: string }>; searchParams: Promise<{ page?: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug, id } = await params;
  const data = await getSeller(slug, id);

  return data ? { title: `${data.seller.name} — ${data.store.name}`, alternates: { canonical: `/s/${slug}/sellers/${id}` } } : { title: "Seller not found" };
}

export default async function SellerPage({ params, searchParams }: Props) {
  const { slug, id } = await params;
  const { page } = await searchParams;
  const data = await getSeller(slug, id, page);
  if (!data) notFound();


  return <SellerPageView data={data} />;
}
