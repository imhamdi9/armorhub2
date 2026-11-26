import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";

import { CartScreen } from "@/components/shop/cart-screen";
import { ShopShell } from "@/components/shop/shop-shell";
import { getStore } from "@/lib/public-api";

// A cart is personal: nothing to find in a search engine.
export const metadata: Metadata = { title: "Your cart", robots: { index: false, follow: false } };

type Props = { params: Promise<{ slug: string }> };

/** Check the cart and buy everything in it, signed in or not. */
export default async function PublicCartPage({ params }: Props) {
  const { slug } = await params;
  const data = await getStore(slug);
  if (data?.merged_into) redirect(`/s/${data.merged_into}/cart`);
  if (!data) notFound();

  return (
    <ShopShell store={{ ...data.store, market: data.market }}>
      <div className="mx-auto max-w-5xl px-4 py-8"><CartScreen slug={slug} /></div>
    </ShopShell>
  );
}
