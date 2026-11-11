"use client";

import Link from "next/link";

import { formatMoney, tr } from "@/lib/i18n";
import type { PublicProduct } from "@/types/public";

export const price = (p: { from_price: number; currency: string }) =>
  formatMoney(p.from_price, p.currency, true);

export function ProductCard({ product, slug }: { product: PublicProduct; slug: string }) {
  return (
    <Link href={`/s/${slug}/products/${product.id}`} className="hover-lift flex flex-col rounded-[20px] border border-fog bg-white p-5">
      {product.category && <span className="mb-1 text-xs font-bold uppercase tracking-wider text-spruce">{product.category}</span>}
      <h3 className="text-lg font-bold text-obsidian">{product.name}</h3>
      {product.brand && <p className="text-sm text-slate">{product.brand}</p>}
      {product.description && <p className="mt-2 line-clamp-3 text-sm text-slate">{product.description}</p>}
      <div className="mt-auto flex items-end justify-between pt-4">
        <p className="text-sm">
          {tr("From")} <strong className="text-base">{price(product)}</strong>
        </p>
        {product.warranty_years ? (
          <span className="rounded-full bg-linen px-2.5 py-1 text-xs font-medium text-forest">{tr("{n}-year warranty", { n: product.warranty_years })}</span>
        ) : null}
      </div>
    </Link>
  );
}
