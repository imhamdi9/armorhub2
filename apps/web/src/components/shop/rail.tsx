"use client";

import Link from "next/link";

import { savingPercent } from "@/components/public/product-card";
import { Stars } from "@/components/shop/stars";
import { ProductTile } from "@/components/shop/shop-product-card";
import { formatMoney, tr } from "@/lib/i18n";
import type { PublicProduct } from "@/types/public";

const card = "rounded-2xl border border-border bg-white p-5";

/** A row of products that scrolls sideways, with a link to the whole list. */
export function Rail({ title, href, more, products, slug, id }: { title: string; href?: string; more?: string; products: PublicProduct[]; slug: string; id: string }) {
  if (products.length === 0) return null;

  return (
    <section className={`${card} mt-4`} aria-labelledby={id} data-testid="rail">
      <div className="mb-3 flex items-baseline justify-between gap-4">
        <h2 id={id} className="text-xl font-bold">{title}</h2>
        {href && more && <Link href={href} className="text-sm font-semibold text-[var(--brand-ink)] hover:underline">{more}</Link>}
      </div>
      <ul className="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2">
        {products.map((p) => {
          const saving = savingPercent(p.from_price, p.compare_at_price);
          return (
            <li key={p.id} className="w-[170px] shrink-0 snap-start sm:w-[190px]">
              <Link href={`/s/${slug}/products/${p.id}`} className="group block">
                <div className="relative aspect-square"><ProductTile product={p} />{saving && <span className="absolute start-2 top-2 rounded bg-[#a32d2d] px-1.5 py-0.5 text-xs font-bold text-white">−{saving}%</span>}</div>
                <p className="mt-2 line-clamp-2 text-sm leading-snug group-hover:text-[var(--brand-ink)]">{p.name}</p>
                <div className="mt-0.5"><Stars average={p.rating?.average} count={p.rating?.count} /></div>
                <p className="mt-0.5 flex flex-wrap items-baseline gap-x-2"><strong className="text-base">{formatMoney(p.from_price, p.currency, true)}</strong>{p.compare_at_price && p.compare_at_price > p.from_price && <s className="text-xs text-slate">{formatMoney(p.compare_at_price, p.currency, true)}</s>}</p>
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

