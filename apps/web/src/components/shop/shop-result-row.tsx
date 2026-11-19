"use client";

import Link from "next/link";

import { savingPercent } from "@/components/public/product-card";
import { BundleBadge } from "@/components/shop/bundle-badge";
import { CompareToggle } from "@/components/shop/compare";
import { QuickAdd } from "@/components/shop/quick-add";
import { Stars } from "@/components/shop/stars";
import { ProductTile } from "@/components/shop/shop-product-card";
import { formatMoney, tr } from "@/lib/i18n";
import type { PublicProduct } from "@/types/public";

/**
 * One search result as a row: the photo, the name, who sells it, its rating, the price with what was saved, how it is delivered, and the button to add it,
 * so shoppers can compare without opening every product.
 */
export function ShopResultRow({ product, slug, headingLevel = 2 }: { product: PublicProduct; slug: string; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const href = `/s/${slug}/products/${product.id}`;
  const saving = savingPercent(product.from_price, product.compare_at_price);
  const hasWas = !!product.compare_at_price && product.compare_at_price > product.from_price;
  const quote = product.sale_mode === "quote" || product.installation === "required";
  const out = product.in_stock === false;

  return (
    <article className="relative flex gap-4 border-t border-border py-5 first:border-t-0" data-testid="result-row">
      <Link href={href} className="relative block size-[120px] shrink-0 sm:size-[180px]" aria-label={product.name} tabIndex={-1}>
        <ProductTile product={product} />
        {product.is_bundle && <BundleBadge className="absolute bottom-2 start-2" />}
      </Link>
      <div className="min-w-0 flex-1">
        <Heading className="text-base font-medium leading-snug text-obsidian sm:text-lg"><Link href={href} className="hover:text-[var(--brand-ink)] hover:underline">{product.name}</Link></Heading>
        {(product.seller || product.brand) && <p className="mt-0.5 text-xs text-slate">{tr("by")} {product.brand ?? product.seller}{product.brand && product.seller ? ` · ${product.seller}` : ""}</p>}
        <div className="mt-1"><Stars average={product.rating?.average} count={product.rating?.count} /></div>
        <p className="mt-2 flex flex-wrap items-baseline gap-x-2">
          {quote ? <span className="text-base font-semibold">{tr("Price on request")}</span> : (
            <>
              <strong className="text-xl font-semibold text-obsidian">{formatMoney(product.from_price, product.currency, true)}</strong>
              {hasWas && <s className="text-sm text-slate">{formatMoney(product.compare_at_price!, product.currency, true)}</s>}
              {saving && <span className="rounded bg-[#fdecea] px-1.5 py-0.5 text-xs font-semibold text-[#a32d2d]">{tr("Save {n}%", { n: saving })}</span>}
            </>
          )}
        </p>
        <p className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs">
          {out ? <span className="font-semibold text-[#a32d2d]">{tr("Out of stock")}</span>
            : product.units_left ? <span className="font-semibold text-[#a32d2d]">{tr("Only {n} left", { n: product.units_left })}</span> : null}
          {product.fulfilled_by_armorhub && <span className="text-[#0f6e3a]">{tr("Shipped by ArmorHub")}</span>}
          {product.warranty_years ? <span className="text-slate">{tr("{n}-year warranty", { n: product.warranty_years })}</span> : null}
        </p>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          {quote ? <Link href={href} className="inline-flex h-9 items-center rounded-full border-2 border-obsidian px-4 text-sm font-bold hover:bg-fog">{product.installation === "required" ? tr("See offers") : tr("Request a quote")}</Link> : <div className="w-44"><QuickAdd product={product} slug={slug} wide /></div>}
          <CompareToggle slug={slug} id={product.id} className="static" />
        </div>
      </div>
    </article>
  );
}
