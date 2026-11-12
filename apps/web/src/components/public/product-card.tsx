"use client";

import Link from "next/link";

import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import type { PublicProduct } from "@/types/public";

export const price = (p: { from_price: number; currency: string }) =>
  formatMoney(p.from_price, p.currency, true);

/** The percentage knocked off the was-price, or null when there is no real saving. */
export const savingPercent = (now: number, was?: number | null) => (was && was > now ? Math.round((1 - now / was) * 100) : null);

export function ProductCard({ product, slug, headingLevel = 3 }: { product: PublicProduct; slug: string; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const image = imageSrc(product.images?.[0] ?? null);
  const saving = savingPercent(product.from_price, product.compare_at_price);

  return (
    <Link href={`/s/${slug}/products/${product.id}`} className="hover-lift group flex flex-col overflow-hidden rounded-[20px] border border-fog bg-white">
      {image && (
        <div className="relative aspect-square overflow-hidden bg-linen">
          {/* eslint-disable-next-line @next/next/no-img-element -- an https or uploaded image chosen by the seller */}
          <img src={image} alt={product.name} loading="lazy" className="h-full w-full object-cover transition duration-300 group-hover:scale-105" />
          {saving && <span className="absolute start-3 top-3 rounded-full bg-terra px-2.5 py-1 text-xs font-bold text-white" style={{ backgroundColor: "#b91c1c" }}>−{saving}%</span>}
        </div>
      )}
      <div className="flex flex-1 flex-col p-5">
        {product.category && <span className="mb-1 text-xs font-bold uppercase tracking-wider text-spruce">{product.category.replace(/-/g, " ")}</span>}
        <Heading className="text-lg font-bold text-obsidian">{product.name}</Heading>
        {product.brand && <p className="text-sm text-slate">{product.brand}</p>}
        {!image && product.description && <p className="mt-2 line-clamp-3 text-sm text-slate">{product.description}</p>}
        <div className="mt-auto flex items-end justify-between pt-4">
          <p className="text-sm">
            {product.compare_at_price && product.compare_at_price > product.from_price ? null : <>{tr("From")} </>}
            <strong className="text-base">{price(product)}</strong>
            {product.compare_at_price && product.compare_at_price > product.from_price ? (
              <span className="ms-2 text-xs text-slate line-through">{formatMoney(product.compare_at_price, product.currency, true)}</span>
            ) : null}
          </p>
          {product.warranty_years ? (
            <span className="rounded-full bg-linen px-2.5 py-1 text-xs font-medium text-forest">{tr("{n}-year warranty", { n: product.warranty_years })}</span>
          ) : null}
        </div>
      </div>
    </Link>
  );
}
