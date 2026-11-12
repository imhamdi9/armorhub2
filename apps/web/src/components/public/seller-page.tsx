"use client";

import Link from "next/link";

import { PublicShell } from "@/components/public/shell";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { tr } from "@/lib/i18n";
import type { PublicSellerPage } from "@/types/public";

export function SellerPageView({ data }: { data: PublicSellerPage }) {
  const { seller, store, products } = data;
  const current = products.current_page;

  return (
    <PublicShell store={{ name: store.name, slug: store.slug }}>
      <main className="mx-auto max-w-6xl px-4 py-10">
        <nav aria-label="Breadcrumb" className="mb-4 flex gap-2 text-sm text-slate">
          <Link href={`/s/${store.slug}`} className="hover:text-forest">{store.name}</Link><span aria-hidden="true">/</span>
          <span className="text-obsidian">{seller.name}</span>
        </nav>
        <h1 className="display display-md">{seller.name}</h1>
        <p className="mt-1 text-sm text-slate">
          {[seller.city, seller.since ? tr("Selling on {market} since {date}", { market: store.name, date: seller.since }) : null].filter(Boolean).join(" · ")}
        </p>

        {products.data.length === 0 ? (
          <p className="mt-8 text-slate">{tr("This seller has no products yet.")}</p>
        ) : (
          <div className="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
            {products.data.map((p) => <ShopProductCard key={p.id} product={p} slug={store.slug} />)}
          </div>
        )}

        {products.last_page > 1 && (
          <nav aria-label="Pages" className="mt-8 flex justify-center gap-3 text-sm">
            {current > 1 && <Link className="text-forest hover:underline" href={`/s/${store.slug}/sellers/${seller.id}?page=${current - 1}`}>{tr("Previous")}</Link>}
            <span>{current} / {products.last_page}</span>
            {current < products.last_page && <Link className="text-forest hover:underline" href={`/s/${store.slug}/sellers/${seller.id}?page=${current + 1}`}>{tr("Next")}</Link>}
          </nav>
        )}
      </main>
    </PublicShell>
  );
}
