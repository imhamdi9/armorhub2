"use client";

import Link from "next/link";

import type { ShellStore } from "@/components/public/shell";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { ShopSearch } from "@/components/shop/shop-search";
import { ShopShell } from "@/components/shop/shop-shell";
import { categoryLabel } from "@/lib/category";
import { tr } from "@/lib/i18n";
import type { PublicProducts } from "@/types/public";

/** Search and browse a store's products. All filtering is server-side, so it works without JavaScript. */
export function Catalog({
  store, result, categories, counts = {}, q, category,
}: {
  store: ShellStore;
  result: PublicProducts | null;
  categories: string[];
  counts?: Record<string, number>;
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
            <Link href={link({ q })} className={`rounded-full px-4 py-2 text-base ${category === "" ? "btn-brand" : "border border-[#ebebeb] bg-white"}`}>{tr("All")} <span className="text-xs opacity-70">{Object.values(counts).reduce((a, b) => a + b, 0) || ""}</span></Link>
            {categories.map((c) => (
              <Link key={c} href={link({ q, category: c })} className={`rounded-full px-4 py-2 text-base ${category === c ? "btn-brand" : "border border-[#ebebeb] bg-white"}`}>{categoryLabel(c)} <span className="text-xs opacity-70">{counts[c] ?? ""}</span></Link>
            ))}
          </div>
        )}

        {!result || result.data.length === 0 ? (
          <p className="rounded-[28px] bg-white p-10 text-center text-[#5f5c5b]">{tr("No products found.")}</p>
        ) : (
          <>
            {/* The list is ordered by category, so each run of products gets its own heading. */}
            {result.data.reduce<{ cat: string; items: typeof result.data }[]>((groups, p) => {
              const cat = p.category ?? "general";
              const last = groups[groups.length - 1];
              if (last && last.cat === cat) last.items.push(p); else groups.push({ cat, items: [p] });
              return groups;
            }, []).map((g) => (
              <section key={g.cat} className="mb-10" aria-label={categoryLabel(g.cat)}>
                {!category && <h2 className="mb-4 text-xl tracking-[-0.05em]">{categoryLabel(g.cat)}</h2>}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                  {g.items.map((p) => <ShopProductCard key={p.id} product={p} slug={store.slug} headingLevel={category ? 2 : 3} />)}
                </div>
              </section>
            ))}

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
