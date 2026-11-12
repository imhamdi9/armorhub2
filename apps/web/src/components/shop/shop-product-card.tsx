"use client";

import Link from "next/link";

import { price, savingPercent } from "@/components/public/product-card";
import { SHOP_SHADOW } from "@/components/shop/shop-shell";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import type { PublicProduct } from "@/types/public";

/** A product with a photo, or a plain tinted tile carrying its initial when the seller has not added one. */
export function ProductTile({ product, className = "" }: { product: PublicProduct; className?: string }) {
  const image = imageSrc(product.images?.[0] ?? null);
  return image ? (
    // eslint-disable-next-line @next/next/no-img-element -- an https or uploaded image chosen by the seller
    <img src={image} alt="" loading="lazy" className={`size-full rounded-[20px] object-cover ${className}`} />
  ) : (
    <div aria-hidden="true" className={`grid size-full place-items-center rounded-[20px] bg-brand-wash text-4xl text-brand-ink ${className}`}>{product.name.slice(0, 1).toUpperCase()}</div>
  );
}

/** Shop-style card: 28px white card, the photo inside it with a 20px radius, soft shadow, no border. */
export function ShopProductCard({ product, slug, headingLevel = 3 }: { product: PublicProduct; slug: string; headingLevel?: 2 | 3 }) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const saving = savingPercent(product.from_price, product.compare_at_price);
  const hasWas = !!product.compare_at_price && product.compare_at_price > product.from_price;

  return (
    <Link href={`/s/${slug}/products/${product.id}`} className="group block rounded-[28px] bg-white p-2 transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black" style={{ boxShadow: SHOP_SHADOW }}>
      <div className="relative aspect-square">
        <ProductTile product={product} />
        {saving && <span className="absolute start-2 top-2 rounded-full bg-black px-2.5 py-1 text-xs text-white">−{saving}%</span>}
      </div>
      <div className="px-3 pb-3 pt-3">
        <Heading className="text-[14px] tracking-[-0.014em] text-black">{product.name}</Heading>
        {(product.seller || product.brand) && <p className="mt-0.5 text-xs text-[#5f5c5b]">{product.seller ?? product.brand}</p>}
        <p className="mt-2 flex flex-wrap items-baseline gap-x-2 text-[14px] tracking-[-0.014em]">
          {!hasWas && <span className="text-xs text-[#5f5c5b]">{tr("From")}</span>}
          <strong className="font-normal">{price(product)}</strong>
          {hasWas && <s className="text-xs text-[#5f5c5b]">{formatMoney(product.compare_at_price!, product.currency, true)}</s>}
        </p>
        {product.warranty_years ? <p className="mt-1 text-xs text-[#5f5c5b]">{tr("{n}-year warranty", { n: product.warranty_years })}</p> : null}
      </div>
    </Link>
  );
}
