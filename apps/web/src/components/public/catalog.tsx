"use client";

import Link from "next/link";

import type { ShellStore } from "@/components/public/shell";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { FitFilter } from "@/components/shop/fit-filter";
import { ForYou, RecentlyViewed } from "@/components/shop/memory";
import { ShopSearch } from "@/components/shop/shop-search";
import { ShopShell } from "@/components/shop/shop-shell";
import { categoryLabel } from "@/lib/category";
import { formatNumber, tr } from "@/lib/i18n";
import type { PublicProducts } from "@/types/public";

const SORTS = [["", "Featured"], ["price_asc", "Price: low to high"], ["price_desc", "Price: high to low"], ["rating", "Top rated"], ["name", "Name"]] as const;

type Filters = { min_price: string; max_price: string; brand: string; min_rating: string };

/** Search and browse a store's products. All filtering and sorting is server-side, so it works without JavaScript. */
export function Catalog({
  store, result, categories, counts = {}, industries = [], industryCounts = {}, industry = "", q, category, sort = "", fit, filters = { min_price: "", max_price: "", brand: "", min_rating: "" },
}: {
  filters?: Filters;
  fit?: { vehicle_model_id: string; year: string; compatible: string };
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
    const merged: Record<string, string | number> = { ...params, ...(industry ? { industry } : {}), ...(sort && !("sort" in params) ? { sort } : {}), ...(fit ?? {}), ...Object.fromEntries(Object.entries(filters).filter(([k, v]) => v && !(k in params))) };
    const query = new URLSearchParams(Object.entries(merged).filter(([, v]) => v !== "" && v !== 0).map(([k, v]) => [k, String(v)])).toString();
    return query ? `${base}?${query}` : base;
  };
  const active = Object.values(filters).filter(Boolean).length;
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
          <div className="w-full max-w-md"><ShopSearch action={base} q={q} category={category} slug={store.slug} /></div>
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
            <FitFilter base={base} q={q} category={category} sort={sort} industry={industry} fit={fit} />
            {/* Narrow the list by price, brand and rating. A plain GET form: it works without JavaScript, and the address can be shared. */}
            <details className="mb-5 rounded-2xl border border-border bg-white" open={active > 0} data-testid="filters">
              <summary className="flex cursor-pointer items-center justify-between gap-3 px-5 py-3 text-sm font-semibold">
                <span>{tr("Filter by price, brand and rating")}{active > 0 && <span className="ms-2 rounded-full bg-brand-wash px-2 py-0.5 text-xs text-brand-ink">{active}</span>}</span>
              </summary>
              <form action={base} method="get" className="grid gap-4 border-t border-border p-5 sm:grid-cols-2 lg:grid-cols-4">
                {q && <input type="hidden" name="q" value={result?.search?.corrected ?? q} />}
                {category && <input type="hidden" name="category" value={category} />}
                {industry && <input type="hidden" name="industry" value={industry} />}
                {sort && <input type="hidden" name="sort" value={sort} />}
                {fit && Object.entries(fit).map(([k, v]) => <input key={k} type="hidden" name={k} value={v} />)}
                <fieldset>
                  <legend className="mb-1 text-xs font-semibold text-slate">{tr("Price")}</legend>
                  <div className="flex items-center gap-2">
                    <input name="min_price" type="number" min={0} inputMode="decimal" defaultValue={filters.min_price} placeholder={result?.facets ? String(Math.floor(result.facets.price.min)) : tr("From")} aria-label={tr("From")} className="h-10 w-full rounded-xl border border-border px-3 text-sm" />
                    <span aria-hidden="true">–</span>
                    <input name="max_price" type="number" min={0} inputMode="decimal" defaultValue={filters.max_price} placeholder={result?.facets ? String(Math.ceil(result.facets.price.max)) : tr("To")} aria-label={tr("To")} className="h-10 w-full rounded-xl border border-border px-3 text-sm" />
                  </div>
                </fieldset>
                <label className="text-xs font-semibold text-slate">{tr("Brand")}
                  <select name="brand" defaultValue={filters.brand} className="mt-1 h-10 w-full rounded-xl border border-border bg-white px-3 text-sm font-normal text-obsidian">
                    <option value="">{tr("All brands")}</option>
                    {(result?.facets?.brands ?? []).map((b) => <option key={b.name} value={b.name}>{b.name} ({b.count})</option>)}
                  </select>
                </label>
                <label className="text-xs font-semibold text-slate">{tr("Rating")}
                  <select name="min_rating" defaultValue={filters.min_rating} className="mt-1 h-10 w-full rounded-xl border border-border bg-white px-3 text-sm font-normal text-obsidian">
                    <option value="">{tr("Any rating")}</option>
                    {[4, 3, 2].map((n) => <option key={n} value={n}>{tr("{n} stars and up", { n })}</option>)}
                  </select>
                </label>
                <div className="flex items-end gap-2">
                  <button type="submit" className="btn-brand h-10 rounded-full px-5 text-sm font-semibold">{tr("Apply")}</button>
                  {active > 0 && <Link href={link({ q: result?.search?.corrected ?? q, category, min_price: "", max_price: "", brand: "", min_rating: "" })} className="h-10 content-center px-3 text-sm underline">{tr("Clear")}</Link>}
                </div>
              </form>
            </details>
            {result?.search?.corrected && (
              <p className="mb-4 rounded-2xl bg-brand-wash px-5 py-3 text-sm" data-testid="corrected">
                {tr("Showing results for")} <b>{result.search.corrected}</b>. <span className="text-slate">{tr("You searched for")} “{q}”.</span>
              </p>
            )}
            <nav aria-label={tr("Sort")} className="mb-5 flex flex-wrap items-center gap-2 text-sm">
              <span className="text-slate">{tr("Sort")}:</span>
              {SORTS.map(([key, label]) => (
                <Link key={key} href={link({ q, category, sort: key })} aria-current={sort === key ? "true" : undefined} className={`rounded-full px-3 py-1.5 font-medium ${sort === key ? "bg-obsidian text-white" : "bg-fog hover:bg-fog/70"}`}>{tr(label)}</Link>
              ))}
            </nav>

            {!result || result.data.length === 0 ? (
              <p className="rounded-[28px] bg-brand-wash p-10 text-center text-slate">{tr("No products found.")}{active > 0 && <> <Link className="underline" href={link({ q, category, min_price: "", max_price: "", brand: "", min_rating: "" })}>{tr("Clear the filters")}</Link></>}</p>
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
        <RecentlyViewed slug={store.slug} />
        <ForYou slug={store.slug} />
      </div>
    </ShopShell>
  );
}
