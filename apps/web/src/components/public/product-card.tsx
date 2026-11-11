"use client";

import Link from "next/link";

import { formatNumber, tr } from "@/lib/i18n";
import type { PublicProduct } from "@/types/public";

export const price = (p: { from_price: number; currency: string }) =>
  `${formatNumber(p.from_price, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${p.currency}`;

export function ProductCard({ product, slug }: { product: PublicProduct; slug: string }) {
  return (
    <Link href={`/s/${slug}/products/${product.id}`} className="flex flex-col rounded-xl border p-4 transition hover:shadow-md">
      {product.category && <span className="mb-1 text-xs uppercase tracking-wide text-gray-500">{product.category}</span>}
      <h3 className="text-lg font-semibold">{product.name}</h3>
      {product.brand && <p className="text-sm text-gray-500">{product.brand}</p>}
      {product.description && <p className="mt-2 line-clamp-3 text-sm text-gray-600">{product.description}</p>}
      <div className="mt-auto flex items-end justify-between pt-4">
        <p className="text-sm">
          {tr("From")} <strong className="text-base">{price(product)}</strong>
        </p>
        {product.warranty_years ? (
          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{tr("{n}-year warranty", { n: product.warranty_years })}</span>
        ) : null}
      </div>
    </Link>
  );
}
