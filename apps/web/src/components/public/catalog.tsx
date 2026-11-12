"use client";

import Link from "next/link";

import type { ShellStore } from "@/components/public/shell";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { ShopSearch } from "@/components/shop/shop-search";
import { ShopShell } from "@/components/shop/shop-shell";
import { tr } from "@/lib/i18n";
import type { PublicProducts } from "@/types/public";

/** Search and browse a store's products. All filtering is server-side, so it works without JavaScript. */
export function Catalog({
  store, result, categories, q, category,
}: {
  store: ShellStore;
  result: PublicProducts | null;
  categories: string[];
  q: string;
  category: string;
}) {
  const base = `/s/${store.slug}/products`;
  const link = (params: Record<string, string | number>) => {
    const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== "" && v !== 0).map(([k, v]) => [k, String(v)])).toString();
    return query ? `${base}?${query}` : base;
  };

  return (
    <ShopShell store={store} active="products">
      <div className="pt-10">
        <h1 className="mb-6 text-center text-4xl tracking-[-0.05em]">{tr("Products")}</h1>
        <ShopSearch action={base} q={q} category={category} />

        {categories.length > 0 && (
          <div className="mb-8 mt-6 flex flex-wrap justify-center gap-2">
            <Link href={link({ q })} className={`rounded-full px-4 py-2 text-base ${category === "" ? "btn-brand" : "border border-[#ebebeb] bg-white"}`}>{tr("All")}</Link>
            {categories.map((c) => (
              <Link key={c} href={link({ q, category: c })} className={`rounded-full px-4 py-2 text-base ${category === c ? "btn-brand" : "border border-[#ebebeb] bg-white"}`}>{c}</Link>
            ))}
          </div>
        )}

        {!result || result.data.length === 0 ? (
          <p className="rounded-[28px] bg-white p-10 text-center text-[#787574]">{tr("No products found.")}</p>
        ) : (
          <>
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
              {result.data.map((p) => <ShopProductCard key={p.id} product={p} slug={store.slug} headingLevel={2} />)}
            </div>

            {result.last_page > 1 && (
              <nav className="mt-8 flex items-center justify-center gap-4" aria-label="Pagination">
                {result.current_page > 1 && <Link className="rounded-full border border-[#ebebeb] bg-white px-5 py-2" href={link({ q, category, page: result.current_page - 1 })}>{tr("Previous")}</Link>}
                <span className="text-sm">{tr("Page {page} of {total}", { page: result.current_page, total: result.last_page })}</span>
                {result.current_page < result.last_page && <Link className="rounded-full border border-[#ebebeb] bg-white px-5 py-2" href={link({ q, category, page: result.current_page + 1 })}>{tr("Next")}</Link>}
              </nav>
            )}
          </>
        )}
      </div>
    </ShopShell>
  );
}
