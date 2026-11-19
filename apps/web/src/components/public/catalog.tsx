"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";

import type { ShellStore } from "@/components/public/shell";
import { FitFilter } from "@/components/shop/fit-filter";
import { ForYou, RecentlyViewed } from "@/components/shop/memory";
import { ShopResultRow } from "@/components/shop/shop-result-row";
import { ShopShell } from "@/components/shop/shop-shell";
import { categoryLabel } from "@/lib/category";
import { formatMoney, formatNumber, tr } from "@/lib/i18n";
import type { Department } from "@/lib/shop-categories";
import type { PublicProducts } from "@/types/public";

const SORTS = [["", "Featured"], ["price_asc", "Price: low to high"], ["price_desc", "Price: high to low"], ["rating", "Top rated"], ["name", "Name"]] as const;

type Filters = { min_price: string; max_price: string; brand: string; min_rating: string; stored: string; deals: string };
const NO_FILTERS: Filters = { min_price: "", max_price: "", brand: "", min_rating: "", stored: "", deals: "" };

/** The pages worth showing under a numbered pager: the first, the last, and a few around the current one. */
function pageList(current: number, last: number): (number | "…")[] {
  const set = new Set([1, last, current - 1, current, current + 1].filter((n) => n >= 1 && n <= last));
  const out: (number | "…")[] = [];
  [...set].sort((a, b) => a - b).forEach((n, i, all) => { if (i > 0 && n - all[i - 1] > 1) out.push("…"); out.push(n); });

  return out;
}

/** Search and browse a store's products: departments, ratings, price and delivery on the side; one row per result. Filtering and sorting happen on the server, so it works without JavaScript. */
export function Catalog({
  store, result, categories, tree = [], labels = {}, counts = {}, industries = [], industryCounts = {}, industry = "", q, category, sort = "", fit, filters = NO_FILTERS,
}: {
  filters?: Filters;
  fit?: { vehicle_model_id: string; year: string; compatible: string };
  store: ShellStore;
  result: PublicProducts | null;
  categories: string[];
  tree?: Department[];
  labels?: Record<string, string>;
  counts?: Record<string, number>;
  industries?: { key: string; name: string }[];
  industryCounts?: Record<string, number>;
  industry?: string;
  q: string;
  category: string;
  sort?: string;
}) {
  const router = useRouter();
  const base = `/s/${store.slug}/products`;
  const link = (params: Record<string, string | number>) => {
    const merged: Record<string, string | number> = { ...params, ...(industry ? { industry } : {}), ...(sort && !("sort" in params) ? { sort } : {}), ...(fit ?? {}), ...Object.fromEntries(Object.entries(filters).filter(([k, v]) => v && !(k in params))) };
    const query = new URLSearchParams(Object.entries(merged).filter(([, v]) => v !== "" && v !== 0).map(([k, v]) => [k, String(v)])).toString();
    return query ? `${base}?${query}` : base;
  };
  const active = Object.values(filters).filter(Boolean).length;
  const name = (key: string) => labels[key] ?? tree.flatMap((d) => [d, ...d.children]).find((c) => c.key === key)?.name ?? categoryLabel(key);
  const group = tree.find((d) => d.key === category || d.children.some((c) => c.key === category));
  const total = result?.total ?? result?.data.length ?? 0;
  const perPage = result?.data.length ? Math.max(result.data.length, result.last_page > 1 ? Math.ceil(total / result.last_page) : result.data.length) : 0;
  const from = result && result.data.length ? (result.current_page - 1) * perPage + 1 : 0;
  const to = result && result.data.length ? from + result.data.length - 1 : 0;
  const heading = q ? `“${result?.search?.corrected ?? q}”` : category ? name(category) : tr("Products");
  const side = (on: boolean) => `flex items-center justify-between gap-2 rounded-md px-2 py-1 text-sm ${on ? "font-bold text-obsidian" : "text-[#3c3a39] hover:text-[var(--brand-ink)] hover:underline"}`;
  const sectionTitle = "mb-1.5 mt-5 text-sm font-bold text-obsidian";
  const cur = store.market?.currency ?? result?.data[0]?.currency ?? "";
  const range = result?.facets?.price;
  const brackets = range ? [[0, 200], [200, 500], [500, 1000], [1000, 0]].filter(([lo, hi]) => (hi === 0 ? range.max > lo : range.min < hi && range.max >= lo)) : [];

  const filterPanel = (
    <div className="text-sm">
      <p className="mb-1.5 text-sm font-bold text-obsidian">{tr("Department")}</p>
      {tree.length > 0 ? (
        <nav aria-label={tr("Categories")}>
          {category ? (
            <>
              <Link href={link({ q, category: "" })} className={side(false)}>‹ {tr("All departments")}</Link>
              {group && <Link href={link({ q, category: group.key })} className={`${side(category === group.key)} ms-2`}>{group.name}</Link>}
              {group?.children.map((c) => <Link key={c.key} href={link({ q, category: c.key })} className={`${side(category === c.key)} ms-5`} aria-current={category === c.key ? "true" : undefined}>{c.name}<span className="text-xs text-slate">{c.count}</span></Link>)}
            </>
          ) : tree.map((d) => <Link key={d.key} href={link({ q, category: d.key })} className={side(false)}>{d.name}<span className="text-xs text-slate">{d.count}</span></Link>)}
        </nav>
      ) : (
        <nav aria-label={tr("Categories")}>
          <Link href={link({ q, category: "" })} className={side(category === "")}>{tr("All")}</Link>
          {categories.map((c) => <Link key={c} href={link({ q, category: c })} className={side(category === c)}>{name(c)}<span className="text-xs text-slate">{counts[c] ?? ""}</span></Link>)}
        </nav>
      )}

      <p className={sectionTitle}>{tr("Customer reviews")}</p>
      {[4, 3, 2].map((n) => (
        <Link key={n} href={link({ q, category, min_rating: filters.min_rating === String(n) ? "" : n })} className={side(filters.min_rating === String(n))} aria-current={filters.min_rating === String(n) ? "true" : undefined}>
          <span><span className="text-[#f5a000]" dir="ltr">{"★".repeat(n)}<span className="text-[#d5cfc0]">{"★".repeat(5 - n)}</span></span> {tr("& up")}</span>
        </Link>
      ))}

      <p className={sectionTitle}>{tr("Price")}</p>
      {brackets.map(([lo, hi]) => {
        const on = filters.min_price === (lo ? String(lo) : "") && filters.max_price === (hi ? String(hi) : "") && (lo > 0 || hi > 0);
        return (
          <Link key={`${lo}-${hi}`} href={link({ q, category, min_price: on ? "" : lo || "", max_price: on ? "" : hi || "" })} className={side(on)}>
            {hi === 0 ? `${formatMoney(lo, cur, true)}+` : lo === 0 ? `${tr("Up to")} ${formatMoney(hi, cur, true)}` : `${formatMoney(lo, cur, true)} – ${formatMoney(hi, cur, true)}`}
          </Link>
        );
      })}
      <form action={base} method="get" className="mt-2 flex items-center gap-1.5">
        {q && <input type="hidden" name="q" value={result?.search?.corrected ?? q} />}
        {category && <input type="hidden" name="category" value={category} />}
        {industry && <input type="hidden" name="industry" value={industry} />}
        {sort && <input type="hidden" name="sort" value={sort} />}
        {Object.entries({ brand: filters.brand, min_rating: filters.min_rating, stored: filters.stored, deals: filters.deals }).filter(([, v]) => v).map(([k, v]) => <input key={k} type="hidden" name={k} value={v} />)}
        {fit && Object.entries(fit).map(([k, v]) => <input key={k} type="hidden" name={k} value={v} />)}
        <input name="min_price" type="number" min={0} inputMode="decimal" defaultValue={filters.min_price} placeholder={tr("Min")} aria-label={tr("Minimum price")} className="h-8 w-full min-w-0 rounded-md border border-border px-2 text-sm" />
        <input name="max_price" type="number" min={0} inputMode="decimal" defaultValue={filters.max_price} placeholder={tr("Max")} aria-label={tr("Maximum price")} className="h-8 w-full min-w-0 rounded-md border border-border px-2 text-sm" />
        <button type="submit" className="h-8 shrink-0 rounded-md border border-obsidian px-3 text-xs font-bold hover:bg-fog">{tr("Go")}</button>
      </form>

      <p className={sectionTitle}>{tr("Delivery and offers")}</p>
      <Link href={link({ q, category, stored: filters.stored ? "" : "1" })} className={side(!!filters.stored)}><span>{filters.stored ? "☑" : "☐"} {tr("Shipped by ArmorHub")}</span></Link>
      <Link href={link({ q, category, deals: filters.deals ? "" : "1" })} className={side(!!filters.deals)}><span>{filters.deals ? "☑" : "☐"} {tr("Deals and discounts")}</span></Link>

      {(result?.facets?.brands ?? []).length > 0 && (
        <>
          <p className={sectionTitle}>{tr("Brand")}</p>
          {result!.facets!.brands.slice(0, 8).map((b) => (
            <Link key={b.name} href={link({ q, category, brand: filters.brand === b.name ? "" : b.name })} className={side(filters.brand === b.name)}><span>{filters.brand === b.name ? "☑" : "☐"} {b.name}</span><span className="text-xs text-slate">{b.count}</span></Link>
          ))}
        </>
      )}

      {industries.length > 1 && (
        <nav aria-label={tr("Industries")} data-testid="industry-pills">
          <p className={sectionTitle}>{tr("Industries")}</p>
          <Link href={`${base}?${new URLSearchParams({ ...(q ? { q } : {}) }).toString()}`} aria-current={industry === "" ? "true" : undefined} className={side(industry === "")}>{tr("All industries")}</Link>
          {industries.map((i) => (
            <Link key={i.key} href={`${base}?${new URLSearchParams({ ...(q ? { q } : {}), industry: i.key }).toString()}`} aria-current={industry === i.key ? "true" : undefined} className={side(industry === i.key)}>{tr(i.name)}<span className="text-xs text-slate">{industryCounts[i.key] ?? ""}</span></Link>
          ))}
        </nav>
      )}

      {active > 0 && <Link href={link({ q, category, min_price: "", max_price: "", brand: "", min_rating: "", stored: "", deals: "" })} className="mt-5 inline-block text-sm font-semibold text-[var(--brand-ink)] underline" data-testid="clear-filters">{tr("Clear all filters")}</Link>}
    </div>
  );

  return (
    <ShopShell store={store} active="products">
      <div className="pt-4">
        <nav aria-label={tr("Breadcrumb")} className="mb-2 text-xs text-slate">
          <Link href={`/s/${store.slug}`} className="hover:underline">{store.name}</Link> › <Link href={base} className="hover:underline">{tr("Products")}</Link>
          {group && <> › <Link href={link({ q, category: group.key })} className="hover:underline">{group.name}</Link></>}
          {category && group && category !== group.key && <> › <span className="text-obsidian">{name(category)}</span></>}
        </nav>

        <div className="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-white px-4 py-2.5">
          <p className="text-sm" data-testid="result-count">
            {result && total > 0 ? <>{tr("{from}–{to} of {total} results", { from: formatNumber(from), to: formatNumber(to), total: formatNumber(total) })}</> : tr("{n} products", { n: formatNumber(0) })}
            {(q || category) && <> {tr("for")} <b className="font-bold">{heading}</b></>}
          </p>
          <label className="flex items-center gap-2 text-sm">
            <span className="text-slate">{tr("Sort by")}:</span>
            <select value={sort} onChange={(e) => router.push(link({ q, category, sort: e.target.value }))} aria-label={tr("Sort")} data-testid="sort-select" className="h-9 rounded-lg border border-border bg-fog px-3 text-sm font-semibold">
              {SORTS.map(([key, label]) => <option key={key} value={key}>{tr(label)}</option>)}
            </select>
          </label>
        </div>

        <details className="mb-3 rounded-lg border border-border bg-white lg:hidden" open={active > 0} data-testid="filters">
          <summary className="cursor-pointer px-4 py-2.5 text-sm font-bold">{tr("Filters")}{active > 0 && <span className="ms-2 rounded-full bg-brand-wash px-2 py-0.5 text-xs text-brand-ink">{active}</span>}</summary>
          <div className="border-t border-border p-4">{filterPanel}</div>
        </details>

        <div className="grid gap-6 lg:grid-cols-[230px_1fr]">
          <aside className="hidden lg:block" aria-label={tr("Filters")}><div className="sticky top-4 max-h-[calc(100vh-2rem)] overflow-y-auto pe-2">{filterPanel}</div></aside>

          <div className="min-w-0">
            <FitFilter base={base} q={q} category={category} sort={sort} industry={industry} fit={fit} />
            {result?.search?.corrected && (
              <p className="mb-4 rounded-lg bg-brand-wash px-4 py-3 text-sm" data-testid="corrected">
                {tr("Showing results for")} <b>{result.search.corrected}</b>. <span className="text-slate">{tr("You searched for")} “{q}”.</span>
              </p>
            )}

            {!result || result.data.length === 0 ? (
              <p className="rounded-lg bg-brand-wash p-10 text-center text-slate">{tr("No products found.")}{active > 0 && <> <Link className="underline" href={link({ q, category, min_price: "", max_price: "", brand: "", min_rating: "", stored: "", deals: "" })}>{tr("Clear the filters")}</Link></>}</p>
            ) : (
              <>
                <div className="rounded-lg border border-border bg-white px-4" data-testid="results">
                  {result.data.map((p) => <ShopResultRow key={p.id} product={p} slug={store.slug} headingLevel={category || q ? 2 : 3} />)}
                </div>

                {result.last_page > 1 && (
                  <nav className="mt-6 flex flex-wrap items-center justify-center gap-1.5 text-sm" aria-label={tr("Pagination")} data-testid="pager">
                    {result.current_page > 1 && <Link className="rounded-lg border border-border bg-white px-4 py-2 font-semibold hover:bg-fog" href={link({ q, category, page: result.current_page - 1 })}>‹ {tr("Previous")}</Link>}
                    {pageList(result.current_page, result.last_page).map((n, i) => n === "…"
                      ? <span key={`gap${i}`} className="px-2 text-slate">…</span>
                      : <Link key={n} href={link({ q, category, page: n })} aria-current={n === result.current_page ? "page" : undefined} className={`grid size-9 place-items-center rounded-lg border ${n === result.current_page ? "border-obsidian bg-obsidian font-bold text-white" : "border-border bg-white hover:bg-fog"}`}>{formatNumber(n)}</Link>)}
                    {result.current_page < result.last_page && <Link className="rounded-lg border border-border bg-white px-4 py-2 font-semibold hover:bg-fog" href={link({ q, category, page: result.current_page + 1 })}>{tr("Next")} ›</Link>}
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
