"use client";

import Link from "next/link";
import { useEffect } from "react";

import { QuickAdd } from "@/components/shop/quick-add";
import { ProductTile } from "@/components/shop/shop-product-card";
import { formatMoney, formatNumber, tr } from "@/lib/i18n";
import type { CartView } from "@/services/cart.service";
import type { PublicProduct } from "@/types/public";

/**
 * What happens when something is added to the cart: a panel slides in with what was added, the cart total and how far it is from free delivery,
 * a way to go straight to checkout, and a few things that go with it. The shopper stays on the page they were reading.
 */
export function CartPanel({ cart, added, slug, suggestions, onClose }: { cart: CartView; added: { name: string; image?: string | null; price: number; currency: string }; slug: string; suggestions: PublicProduct[]; onClose: () => void }) {
  useEffect(() => { const key = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); }; document.addEventListener("keydown", key); return () => document.removeEventListener("keydown", key); }, [onClose]);
  // Only things that can really go in a cart (not services, quotes or installation-only products).
  suggestions = suggestions.filter((r) => !!r.requires_shipping && r.sale_mode !== "quote" && r.installation !== "required" && !r.fitment_required && r.in_stock !== false);
  const count = cart.items.reduce((s, l) => s + l.quantity, 0);
  const cur = cart.currency ?? added.currency;
  const left = cart.free_shipping_over != null ? Math.max(0, cart.free_shipping_over - cart.items_amount) : null;
  const pct = cart.free_shipping_over ? Math.min(100, Math.round((cart.items_amount / cart.free_shipping_over) * 100)) : 0;

  return (
    <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label={tr("Added to cart")} data-testid="cart-panel">
      <button type="button" aria-label={tr("Close")} className="absolute inset-0 bg-black/40" onClick={onClose} />
      <aside className="absolute inset-y-0 end-0 w-[380px] max-w-[92vw] overflow-y-auto bg-white p-5 text-obsidian shadow-2xl">
        <div className="flex items-start justify-between gap-3">
          <p className="flex items-center gap-2 text-lg font-bold text-[#0f6e3a]"><span aria-hidden="true" className="grid size-6 place-items-center rounded-full bg-[#0f6e3a] text-sm text-white">✓</span>{tr("Added to cart")}</p>
          <button type="button" aria-label={tr("Close")} onClick={onClose} className="grid size-8 place-items-center rounded-full hover:bg-fog">✕</button>
        </div>
        <div className="mt-4 flex gap-3 border-b border-border pb-4">
          <div className="size-16 shrink-0">{added.image ? /* eslint-disable-next-line @next/next/no-img-element -- a seller's product photo */ <img src={added.image} alt="" className="size-full rounded-xl object-cover" /> : <div className="size-full rounded-xl bg-brand-wash" aria-hidden="true" />}</div>
          <div className="min-w-0"><p className="line-clamp-2 text-sm font-medium">{added.name}</p><p className="mt-1 text-base font-bold">{formatMoney(added.price, added.currency, true)}</p></div>
        </div>
        <div className="py-4">
          <p className="text-base">{tr("Cart total")} ({formatNumber(count)} {tr("items")}): <b className="font-bold">{formatMoney(cart.items_amount, cur, true)}</b></p>
          {left !== null && (left > 0
            ? <div className="mt-2"><div className="h-1.5 overflow-hidden rounded-full bg-fog"><div className="h-full rounded-full bg-[#0f6e3a]" style={{ width: `${pct}%` }} /></div><p className="mt-1 text-xs text-slate">{tr("Add {amount} more for free delivery", { amount: formatMoney(left, cur, true) })}</p></div>
            : <p className="mt-2 text-sm font-semibold text-[#0f6e3a]">{tr("Your order qualifies for free delivery")}</p>)}
        </div>
        <Link href={`/s/${slug}/cart`} className="btn-brand flex h-11 items-center justify-center rounded-full text-base font-bold" data-testid="panel-checkout">{tr("Proceed to checkout")}</Link>
        <button type="button" onClick={onClose} className="mt-2 flex h-11 w-full items-center justify-center rounded-full border-2 border-obsidian text-base font-bold hover:bg-fog">{tr("Continue shopping")}</button>
        {suggestions.length > 0 && (
          <div className="mt-6 border-t border-border pt-4">
            <p className="mb-3 text-sm font-bold">{tr("Add these too")}</p>
            <div className="grid gap-3">
              {suggestions.slice(0, 3).map((s) => (
                <div key={s.id} className="flex items-center gap-3">
                  <div className="size-14 shrink-0"><ProductTile product={s} /></div>
                  <div className="min-w-0 flex-1"><p className="line-clamp-2 text-sm">{s.name}</p><p className="text-sm font-bold">{formatMoney(s.from_price, s.currency, true)}</p></div>
                  <div className="w-28 shrink-0"><QuickAdd product={s} slug={slug} wide /></div>
                </div>
              ))}
            </div>
          </div>
        )}
      </aside>
    </div>
  );
}
