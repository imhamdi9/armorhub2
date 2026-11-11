"use client";

import Link from "next/link";

import { ProductCard } from "@/components/public/product-card";
import { PublicShell, type ShellStore } from "@/components/public/shell";
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
    <PublicShell store={store}>
      <div className="mx-auto max-w-6xl px-4 py-10">
        <h1 className="display display-md mb-6">{tr("Products")}</h1>

        <form action={base} method="get" className="mb-4 flex gap-2">
          <input
            name="q"
            defaultValue={q}
            placeholder={tr("Search products")}
            className="h-11 w-full max-w-md rounded-full border border-pebble px-5 outline-none focus:border-forest"
            aria-label={tr("Search products")}
          />
          {category && <input type="hidden" name="category" value={category} />}
          <button className="btn-brand px-6 py-2.5">{tr("Search")}</button>
        </form>

        {categories.length > 0 && (
          <div className="mb-6 flex flex-wrap gap-2">
            <Link href={link({ q })} className={`rounded-full px-4 py-1.5 text-sm font-medium ${category === "" ? "btn-brand" : "bg-fog text-forest hover:bg-linen"}`}>{tr("All")}</Link>
            {categories.map((c) => (
              <Link key={c} href={link({ q, category: c })} className={`rounded-full px-4 py-1.5 text-sm font-medium ${category === c ? "btn-brand" : "bg-fog text-forest hover:bg-linen"}`}>{c}</Link>
            ))}
          </div>
        )}

        {!result || result.data.length === 0 ? (
          <p className="rounded-[20px] border border-dashed border-pebble p-10 text-center text-slate">{tr("No products found.")}</p>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {result.data.map((p) => <ProductCard key={p.id} product={p} slug={store.slug} />)}
            </div>

            {result.last_page > 1 && (
              <nav className="mt-8 flex items-center justify-center gap-4" aria-label="Pagination">
                {result.current_page > 1 && <Link className="rounded-full border border-forest px-5 py-2 text-forest hover:bg-linen" href={link({ q, category, page: result.current_page - 1 })}>{tr("Previous")}</Link>}
                <span className="text-sm">{tr("Page {page} of {total}", { page: result.current_page, total: result.last_page })}</span>
                {result.current_page < result.last_page && <Link className="rounded-full border border-forest px-5 py-2 text-forest hover:bg-linen" href={link({ q, category, page: result.current_page + 1 })}>{tr("Next")}</Link>}
              </nav>
            )}
          </>
        )}
      </div>
    </PublicShell>
  );
}
