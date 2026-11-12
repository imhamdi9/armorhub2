import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";

import { GuestCheckout } from "@/components/shop/guest-checkout";
import { getStore, getStoreProduct } from "@/lib/public-api";

// A checkout page is not something to find in a search engine.
export const metadata: Metadata = { title: "Checkout", robots: { index: false, follow: false } };

type Props = { params: Promise<{ slug: string }>; searchParams: Promise<{ product?: string }> };

export default async function CheckoutPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const { product } = await searchParams;
  if (!product || !/^\d+$/.test(product)) notFound();
  const store = await getStore(slug);
  if (store?.merged_into) redirect(`/s/${store.merged_into}/checkout?product=${product}`);
  const data = await getStoreProduct(slug, product);
  // Only goods sold by a seller can be bought without an account; services need quotes, so they go through signup.
  if (!store || !data || !data.product.requires_shipping) notFound();
  const dial = store.market?.phone;

  return <GuestCheckout store={{ ...store.store, market: store.market }} data={data} phoneHint={dial ? `+${dial.dial} ${dial.example}` : undefined} />;
}
