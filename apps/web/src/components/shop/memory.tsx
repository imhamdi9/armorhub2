"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";

import { ShopProductCard } from "@/components/shop/shop-product-card";
import { LIMITS, rememberView, toggleCompare, useIds } from "@/lib/shop-memory";
import { tr } from "@/lib/i18n";
import { api } from "@/services/api";
import type { PublicProduct } from "@/types/public";

/** Records that this product was looked at (renders nothing). */
export function RememberView({ slug, id }: { slug: string; id: number }) {
  useEffect(() => { rememberView(slug, id); }, [slug, id]);
  return null;
}

/** A row of product cards under a heading; renders nothing when there is nothing to show. */
export function Shelf({ title, products, slug, testId }: { title: string; products: PublicProduct[]; slug: string; testId?: string }) {
  if (products.length === 0) return null;
  return (
    <section className="my-10" aria-label={title} data-testid={testId}>
      <h2 className="font-display mb-4 text-xl font-semibold">{title}</h2>
      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        {products.slice(0, 4).map((p) => <ShopProductCard key={p.id} product={p} slug={slug} />)}
      </div>
    </section>
  );
}

function useProducts(slug: string, path: string, ids: number[], extra = ""): PublicProduct[] | null {
  const key = ids.join(",");
  const [list, setList] = useState<PublicProduct[] | null>(null);
  useEffect(() => {
    if (!key) return;
    let live = true;
    api.get<{ products: PublicProduct[] }>(`/public/storefronts/${encodeURIComponent(slug)}/${path}?ids=${key}${extra}`)
      .then((r) => live && setList(r.data.products)).catch(() => live && setList([]));
    return () => { live = false; };
  }, [slug, path, key, extra]);
  // Nothing to look up: an empty list, without waiting for (or setting) anything.
  return key ? list : [];
}

/** What this shopper looked at lately, most recent first (without the product being viewed). */
export function RecentlyViewed({ slug, exclude }: { slug: string; exclude?: number }) {
  const ids = useIds(slug, "viewed");
  const list = useProducts(slug, "products-by-ids", ids.filter((n) => n !== exclude));
  return <Shelf title={tr("Recently viewed")} products={list ?? []} slug={slug} testId="recently-viewed" />;
}

/** Picks from the categories this shopper has been looking at. */
export function ForYou({ slug }: { slug: string }) {
  const ids = useIds(slug, "viewed");
  const list = useProducts(slug, "recommendations", ids);
  return <Shelf title={tr("Picked for you")} products={list ?? []} slug={slug} testId="for-you" />;
}

type Detailed = PublicProduct & { highlights?: string[]; specs?: { label: string; value: string }[]; rating?: { average: number | null; count: number } };

/** The side-by-side table: one column per product, one row per thing worth comparing. */
export function CompareTable({ slug }: { slug: string }) {
  const ids = useIds(slug, "compare");
  const loaded = useProducts(slug, "products-by-ids", ids, "&detail=1") as Detailed[] | null;
  const labels = useMemo(() => Array.from(new Set((loaded ?? []).flatMap((p) => (p.specs ?? []).map((s) => s.label)))), [loaded]);

  if (loaded === null) return <p className="py-10 text-center text-slate">…</p>;
  if (ids.length === 0 || loaded.length === 0) {
    return (
      <p className="rounded-[28px] bg-brand-wash p-10 text-center text-slate">
        {tr("Pick up to {n} products with the Compare button and they will appear here side by side.", { n: LIMITS.compare })}{" "}
        <Link className="underline" href={`/s/${slug}/products`}>{tr("Browse products")}</Link>
      </p>
    );
  }

  const list: Detailed[] = loaded;
  const row = (label: string, cell: (p: Detailed) => React.ReactNode) => (
    <tr className="border-t border-border align-top">
      <th scope="row" className="w-36 py-3 pe-3 text-start text-sm font-semibold text-slate">{label}</th>
      {list.map((p) => <td key={p.id} className="px-3 py-3 text-sm">{cell(p)}</td>)}
    </tr>
  );
  const best = Math.min(...list.map((p) => p.from_price));

  return (
    <div className="overflow-x-auto rounded-[28px] bg-white p-4 md:p-6" data-testid="compare-table">
      <table className="w-full min-w-[34rem] border-collapse">
        <thead>
          <tr>
            <td />
            {list.map((p) => (
              <th key={p.id} scope="col" className="px-3 pb-3 text-start align-top font-normal">
                <Link href={`/s/${slug}/products/${p.id}`} className="font-semibold hover:underline">{p.name}</Link>
                <button type="button" className="mt-1 block text-xs text-slate underline" onClick={() => toggleCompare(slug, p.id)}>{tr("Remove")}</button>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {row(tr("Price"), (p) => <span className={p.from_price === best && list.length > 1 ? "font-bold text-green-700" : ""}>{new Intl.NumberFormat(undefined, { style: "currency", currency: p.currency, maximumFractionDigits: 2 }).format(p.from_price)}{p.from_price === best && list.length > 1 ? ` · ${tr("Lowest")}` : ""}</span>)}
          {row(tr("Brand"), (p) => p.brand ?? "—")}
          {row(tr("Rating"), (p) => (p.rating?.average ? `${p.rating.average} ★ (${p.rating.count})` : tr("No reviews yet")))}
          {row(tr("Warranty"), (p) => (p.warranty_years ? tr("{n}-year warranty", { n: p.warranty_years }) : "—"))}
          {row(tr("Delivery"), (p) => (p.requires_shipping ? tr("Shipped to you") : tr("A service at your place")))}
          {row(tr("In stock"), (p) => (p.in_stock === false ? tr("Out of stock") : tr("Available")))}
          {row(tr("Sold by"), (p) => p.seller ?? "—")}
          {row(tr("Key features"), (p) => ((p.highlights ?? []).length ? <ul className="list-disc ps-4">{p.highlights!.map((h) => <li key={h}>{h}</li>)}</ul> : "—"))}
          {labels.map((l) => row(l, (p) => p.specs?.find((s) => s.label === l)?.value ?? "—"))}
        </tbody>
      </table>
    </div>
  );
}
