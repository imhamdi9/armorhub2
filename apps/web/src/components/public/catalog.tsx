"use client";

import Link from "next/link";

import type { ShellStore } from "@/components/public/shell";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { ShopSearch } from "@/components/shop/shop-search";
import { ShopShell } from "@/components/shop/shop-shell";
import { categoryLabel } from "@/lib/category";
import { formatNumber, tr } from "@/lib/i18n";
import type { PublicProducts } from "@/types/public";

const SORTS = [["", "Featured"], ["price_asc", "Price: low to high"], ["price_desc", "Price: high to low"], ["name", "Name"]] as const;

/** Search and browse a store's products. All filtering and sorting is server-side, so it works without JavaScript. */
export function Catalog({
  store, result, categories, counts = {}, industries = [], industryCounts = {}, industry = "", q, category, sort = "",
}: {
  store: ShellStore;
  result: PublicProducts | null;
  categories: string[];
  counts?: Record<string, number>;
  industries?: { key: string; name: string }[];
  industryCounts?: Record<string, number>;
  industry?: string;
  q: string;
  category: string;
  sort?: string;
}) {
  const base = `/s/${store.slug}/products`;
  const link = (params: Record<string, string | number>) => {
    const merged: Record<string, string | number> = { ...params, ...(industry ? { industry } : {}), ...(sort && !("sort" in params) ? { sort } : {}) };
    const query = new URLSearchParams(Object.entries(merged).filter(([, v]) => v !== "" && v !== 0).map(([k, v]) => [k, String(v)])).toString();
    return query ? `${base}?${query}` : base;
  };
  const total = Object.values(counts).reduce((a, b) => a + b, 0);
  const pill = (on: boolean) => `rounded-full px-4 py-2 text-sm font-semibold transition ${on ? "btn-brand" : "border border-border bg-white hover:border-obsidian/30"}`;
  const side = (on: boolean) => `flex items-center justify-between rounded-xl px-3 py-2 text-sm ${on ? "bg-brand-wash font-semibold text-brand-ink" : "hover:bg-fog"}`;

  return (
    <ShopShell store={store} active="products">
      <div className="pt-8">
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="display display-md">{category ? categoryLabel(category) : tr("Products")}</h1>
            {result && <p className="mt-1 text-sm text-slate" data-testid="result-count">{tr("{n} products", { n: formatNumber(result.total ?? result.data.length) })}</p>}
          </div>
          <div className="w-full max-w-md"><ShopSearch action={base} q={q} category={category} /></div>
        </div>

        {/* On small screens the filters are pills; from large screens they are a column on the side. */}
        <div className="mb-6 flex flex-wrap gap-2 lg:hidden">
          <Link href={link({ q })} className={pill(category === "")}>{tr("All")}</Link>
          {categories.map((c) => <Link key={c} href={link({ q, category: c })} className={pill(category === c)}>{categoryLabel(c)} <span className="text-xs opacity-70">{counts[c] ?? ""}</span></Link>)}
        </div>

        <div className="grid gap-8 lg:grid-cols-[230px_1fr]">
          <aside className="hidden lg:block" aria-label={tr("Filters")}>
            <div className="sticky top-24 space-y-6">
              {categories.length > 0 && (
                <nav aria-label={tr("Categories")}>
                  <p className="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-slate">{tr("Categories")}</p>
                  <Link href={link({ q })} className={side(category === "")}>{tr("All")} <span className="text-xs text-slate">{total || ""}</span></Link>
                  {categories.map((c) => <Link key={c} href={link({ q, category: c })} className={side(category === c)}>{categoryLabel(c)} <span className="text-xs text-slate">{counts[c] ?? ""}</span></Link>)}
                </nav>
              )}
              {industries.length > 1 && (
                <nav aria-label={tr("Industries")} data-testid="industry-pills">
                  <p className="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-slate">{tr("Industries")}</p>
                  <Link href={`${base}?${new URLSearchParams({ ...(q ? { q } : {}) }).toString()}`} aria-current={industry === "" ? "true" : undefined} className={side(industry === "")}>{tr("All industries")}</Link>
                  {industries.map((i) => (
                    <Link key={i.key} href={`${base}?${new URLSearchParams({ ...(q ? { q } : {}), industry: i.key }).toString()}`} aria-current={industry === i.key ? "true" : undefined} className={side(industry === i.key)}>{tr(i.name)} <span className="text-xs text-slate">{industryCounts[i.key] ?? ""}</span></Link>
                  ))}
                </nav>
              )}
            </div>
          </aside>

          <div>
            <nav aria-label={tr("Sort")} className="mb-5 flex flex-wrap items-center gap-2 text-sm">
              <span className="text-slate">{tr("Sort")}:</span>
              {SORTS.map(([key, label]) => (
                <Link key={key} href={link({ q, category, sort: key })} aria-current={sort === key ? "true" : undefined} className={`rounded-full px-3 py-1.5 font-medium ${sort === key ? "bg-obsidian text-white" : "bg-fog hover:bg-fog/70"}`}>{tr(label)}</Link>
              ))}
            </nav>

            {!result || result.data.length === 0 ? (
              <p className="rounded-[28px] bg-brand-wash p-10 text-center text-slate">{tr("No products found.")}</p>
            ) : (
              <>
                {/* Grouped by category when the list is in its default order; one flat grid when it is sorted by price or name. */}
                {(sort ? [{ cat: "", items: result.data }] : result.data.reduce<{ cat: string; items: typeof result.data }[]>((groups, p) => {
                  const cat = p.category ?? "general";
                  const last = groups[groups.length - 1];
                  if (last && last.cat === cat) last.items.push(p); else groups.push({ cat, items: [p] });
                  return groups;
                }, [])).map((g) => (
                  <section key={g.cat || "all"} className="mb-10" aria-label={g.cat ? categoryLabel(g.cat) : tr("Products")}>
                    {!category && g.cat && <h2 className="font-display mb-4 text-xl font-semibold">{categoryLabel(g.cat)}</h2>}
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-3">
                      {g.items.map((p) => <ShopProductCard key={p.id} product={p} slug={store.slug} headingLevel={category ? 2 : 3} />)}
                    </div>
                  </section>
                ))}

                {result.last_page > 1 && (
                  <nav className="mt-8 flex items-center justify-center gap-4" aria-label="Pagination">
                    {result.current_page > 1 && <Link className="rounded-full border border-border bg-white px-5 py-2" href={link({ q, category, page: result.current_page - 1 })}>{tr("Previous")}</Link>}
                    <span className="text-sm">{tr("Page {page} of {total}", { page: result.current_page, total: result.last_page })}</span>
                    {result.current_page < result.last_page && <Link className="rounded-full border border-border bg-white px-5 py-2" href={link({ q, category, page: result.current_page + 1 })}>{tr("Next")}</Link>}
                  </nav>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </ShopShell>
  );
}
