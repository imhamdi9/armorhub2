"use client";

import Link from "next/link";
import { useState } from "react";

import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { ensureShopperSession } from "@/lib/guest";
import { track } from "@/lib/marketing";
import { addToCart } from "@/services/cart.service";
import type { PublicProduct } from "@/types/public";

/**
 * A small button beside (or under) a product shown as a suggestion: the customer adds that product to the cart right here, without opening its page.
 * A guest, who has no cart, goes straight to buying it.
 */
export function QuickAdd({ product, slug, wide = false }: { product: PublicProduct; slug: string; wide?: boolean }) {
  const [state, setState] = useState<"idle" | "busy" | "added">("idle");
  const [error, setError] = useState<string | null>(null);
  // Only goods that can sit in a cart: not services, not quote-only products, not ones sold with an installer.
  if (!product.requires_shipping || product.sale_mode === "quote" || product.installation === "required") return null;

  const cls = `inline-flex h-9 ${wide ? "w-full" : ""} shrink-0 items-center justify-center rounded-full border-2 border-obsidian px-3 text-xs font-bold text-obsidian transition hover:bg-obsidian hover:text-white disabled:opacity-60`;

  return (
    <span className={`flex shrink-0 flex-col gap-1 ${wide ? "items-stretch" : "items-end"}`}>
      <button type="button" className={cls} disabled={state !== "idle"} data-testid="quick-add" aria-label={`${tr("Add to cart")}: ${product.name}`} onClick={async () => {
        setState("busy"); setError(null);
        try { await ensureShopperSession(slug); await addToCart(product.id); track("add_to_cart", { id: product.id, name: product.name, value: product.from_price, currency: product.currency, quantity: 1 }); setState("added"); } catch (e) { setError(errorMessage(e)); setState("idle"); }
      }}>{state === "added" ? `✓ ${tr("Added")}` : tr("Add to cart")}</button>
      {state === "added" && <Link href={`/s/${encodeURIComponent(slug)}/cart`} className="text-center text-[11px] font-semibold text-brand-ink underline">{tr("Open the cart")}</Link>}
      {error && <span role="alert" className="text-center text-[11px] text-red-600">{error}</span>}
    </span>
  );
}
