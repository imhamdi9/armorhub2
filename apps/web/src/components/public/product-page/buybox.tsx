"use client";

import Link from "next/link";
import { useState } from "react";

import { CartPanel } from "@/components/public/product-page/cart-panel";
import type { ProductModel } from "@/components/public/product-page/model";
import { BuyButtons } from "@/components/public/product-page/parts";
import { ProductTile } from "@/components/shop/shop-product-card";
import { errorMessage } from "@/lib/errors";
import { ensureShopperSession } from "@/lib/guest";
import { formatMoney, tr } from "@/lib/i18n";
import { track } from "@/lib/marketing";
import { imageSrc } from "@/lib/surfaces";
import { addToCart, type CartView } from "@/services/cart.service";
import type { PublicProduct } from "@/types/public";

/** Goods that can sit in a cart as they are: not services, not quote-only, not sold with an installer, not needing a car check. */
const cartable = (p: PublicProduct) => !!p.requires_shipping && p.sale_mode !== "quote" && p.installation !== "required" && !p.fitment_required && p.in_stock !== false;

/** The box on the right of a product page: price, stock, delivery, the buying buttons and who ships and sells it. Stays in view while the shopper reads. */
export function BuyBox({ m }: { m: ProductModel }) {
  const { p, store } = m;
  const out = p.in_stock === false;
  const row = "flex justify-between gap-3 py-0.5";

  return (
    <aside className="rounded-2xl border border-border bg-white p-4 lg:sticky lg:top-4" data-testid="buy-box" aria-label={tr("Buy")}>
      {p.sale_mode !== "quote" && <p className="text-2xl font-bold text-obsidian">{formatMoney(m.shown, p.currency, true)}</p>}
      {m.goods && p.sale_mode !== "quote" && (out ? <p className="mt-1 text-lg font-semibold text-[#a32d2d]">{tr("Out of stock")}</p> : <p className="mt-1 text-lg font-semibold text-[#0f6e3a]">{tr("In stock")}</p>)}
      <div className="mt-3"><BuyButtons m={m} big={false} /></div>
      {m.goods && (
        <dl className="mt-4 border-t border-border pt-3 text-xs text-slate" data-testid="buy-box-facts">
          <div className={row}><dt>{tr("Ships from")}</dt><dd className="text-end text-obsidian">{p.fulfilled_by_armorhub ? tr("ArmorHub") : p.seller ?? store.name}</dd></div>
          {p.seller && <div className={row}><dt>{tr("Sold by")}</dt><dd className="text-end">{p.seller_id ? <Link href={`/s/${store.slug}/sellers/${p.seller_id}`} className="text-[var(--brand-ink)] hover:underline">{p.seller}</Link> : <span className="text-obsidian">{p.seller}</span>}</dd></div>}
          <div className={row}><dt>{tr("Payment")}</dt><dd className="text-end text-obsidian">{tr("Secure online payment")}</dd></div>
          <div className={row}><dt>{tr("Returns")}</dt><dd className="text-end"><Link href="/policies/returns" className="text-[var(--brand-ink)] hover:underline">{tr("See the return policy")}</Link></dd></div>
          {p.warranty_years ? <div className={row}><dt>{tr("Warranty")}</dt><dd className="text-end text-obsidian">{tr("{n}-year warranty", { n: p.warranty_years })}</dd></div> : null}
        </dl>
      )}
    </aside>
  );
}

/** "Frequently bought together": this product and up to two that go with it, each with a tick, the total of the ticked ones and one button that adds them all. */
export function BoughtTogether({ m }: { m: ProductModel }) {
  const { p, store } = m;
  const others = (m.pairs.length > 0 ? m.pairs : m.others).filter(cartable).slice(0, 2);
  const canMain = cartable(p) && m.mode !== "required";
  const [off, setOff] = useState<number[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [panel, setPanel] = useState<CartView | null>(null);
  if (!canMain || others.length === 0) return null;

  const items: PublicProduct[] = [p, ...others];
  const picked = items.filter((i) => !off.includes(i.id));
  const total = picked.reduce((s, i) => s + (i.id === p.id ? m.shown : i.from_price), 0);

  async function addAll() {
    setBusy(true); setError(null);
    try {
      await ensureShopperSession(store.slug);
      let cart: CartView | null = null;
      for (const i of picked) {
        cart = await addToCart(i.id, i.id === p.id ? m.chosen?.target_id : undefined, 1);
        track("add_to_cart", { id: i.id, name: i.name, value: i.from_price, currency: i.currency, quantity: 1 });
      }
      if (cart) setPanel(cart);
    } catch (e) { setError(errorMessage(e)); } finally { setBusy(false); }
  }

  return (
    <section className="mt-10 border-t border-border pt-6" aria-label={tr("Frequently bought together")} data-testid="bought-together">
      <h2 className="mb-4 text-xl font-bold">{tr("Frequently bought together")}</h2>
      <div className="flex flex-wrap items-center gap-6">
        <div className="flex items-center gap-3">
          {items.map((i, n) => (
            <div key={i.id} className="flex items-center gap-3">
              {n > 0 && <span aria-hidden="true" className="text-2xl text-slate">+</span>}
              <Link href={`/s/${store.slug}/products/${i.id}`} className={`block size-24 sm:size-28 ${off.includes(i.id) ? "opacity-40" : ""}`} aria-label={i.name}><ProductTile product={i} /></Link>
            </div>
          ))}
        </div>
        <div className="min-w-[220px] flex-1">
          <p className="text-sm text-slate">{tr("Total price")}:</p>
          <p className="text-xl font-bold">{formatMoney(total, p.currency, true)}</p>
          <button type="button" onClick={addAll} disabled={busy || picked.length === 0} data-testid="add-all" className="btn-brand mt-2 inline-flex h-10 items-center justify-center rounded-full px-6 text-sm font-bold">{tr("Add {n} to cart", { n: picked.length })}</button>
          {error && <p role="alert" className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
      </div>
      <ul className="mt-4 grid gap-1.5 text-sm">
        {items.map((i) => (
          <li key={i.id}>
            <label className="flex items-start gap-2">
              <input type="checkbox" checked={!off.includes(i.id)} onChange={(e) => setOff((o) => (e.target.checked ? o.filter((x) => x !== i.id) : [...o, i.id]))} className="mt-1" />
              <span><b className="font-semibold">{i.id === p.id ? tr("This item") : ""}</b> {i.name} <span className="font-bold">{formatMoney(i.id === p.id ? m.shown : i.from_price, i.currency, true)}</span></span>
            </label>
          </li>
        ))}
      </ul>
      {panel && <CartPanel cart={panel} slug={store.slug} onClose={() => setPanel(null)} suggestions={[]} added={{ name: p.name, image: imageSrc(p.images?.[0] ?? null), price: m.shown, currency: p.currency }} />}
    </section>
  );
}
