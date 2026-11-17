"use client";

import { useEffect, useRef, useState } from "react";

import { savingPercent } from "@/components/public/product-card";
import type { ShellStore } from "@/components/public/shell";
import { me } from "@/services/auth.service";
import type { PublicProduct, PublicProductPage } from "@/types/public";

/**
 * Where the buttons go. Signed-in customers go straight to ordering; guests go to checkout (goods) or signup (services).
 * Finding installation offers needs an account (centres answer a signed-in customer), so a guest is sent to signup and then to the request.
 */
export function useOrderLinks(slug: string, productId: number, goods: boolean, parts: number[] = []) {
  const [signedIn, setSignedIn] = useState(false);

  useEffect(() => {
    me().then(() => setSignedIn(true)).catch(() => setSignedIn(false));
  }, []);

  // The parts the customer picked on the page travel with them, so the order form opens with them ticked.
  const withParts = parts.length > 0 ? `&parts=${parts.join(",")}` : "";
  const buy = signedIn ? `/dashboard/orders/new?product=${productId}${withParts}` : goods ? `/s/${encodeURIComponent(slug)}/checkout?product=${productId}` : `/register?store=${encodeURIComponent(slug)}&product=${productId}${withParts}`;
  const find = signedIn ? `/dashboard/installations/new?product=${productId}` : `/register?store=${encodeURIComponent(slug)}&product=${productId}&installation=1`;

  const quote = signedIn ? `/dashboard/quotes?product=${productId}` : `/register?store=${encodeURIComponent(slug)}&product=${productId}`;

  return { buy, find, quote, signedIn };
}

export type ProductModel = ReturnType<typeof useProductModel>;

/** Everything every layout needs: the product, how it is sold, the chosen option and the other products worth showing next to it. */
export function useProductModel(store: ShellStore, data: PublicProductPage) {
  const p = data.product;
  const goods = !!p.requires_shipping;
  const [parts, setParts] = useState<number[]>([]);
  const links = useOrderLinks(store.slug, p.id, goods, parts);
  // How the product is sold: on its own, on its own with optional installation offers, or only together with an installation offer.
  const mode = goods ? (p.installation ?? "none") : "none";
  const href = p.sale_mode === "quote" ? links.quote : mode === "required" ? links.find : links.buy;
  const options = p.prices.filter((r) => r.target);
  const [picked, setPickedRaw] = useState<string | null>(null);
  // Picking an option also remembers its part, so the order form can open with it ticked.
  const setPicked = (key: string | null) => {
    setPickedRaw(key);
    const o = p.prices.find((r) => r.target && `${r.target_type}:${r.target}` === key);
    setParts(o?.target_id !== undefined ? [o.target_id] : []);
  };
  const chosen = options.find((o) => `${o.target_type}:${o.target}` === picked) ?? null;
  const shown = chosen ? chosen.price : p.from_price;
  const was = !chosen ? p.compare_at_price : null;
  const saving = savingPercent(shown, was);
  const cta = useRef<HTMLAnchorElement>(null);
  const [stuck, setStuck] = useState(false);
  const paragraphs = (p.description ?? "").split("\n").filter(Boolean);
  const others: PublicProduct[] = (data.related ?? []).filter((r) => r.id !== p.id);

  // Cheaper and pricier products from the same store, nearest price first: the natural down- and upsell.
  const cheaper = others.filter((r) => r.from_price < p.from_price).sort((a, b) => b.from_price - a.from_price);
  const pricier = others.filter((r) => r.from_price > p.from_price).sort((a, b) => a.from_price - b.from_price);
  // Products from other categories: things that go with this one rather than replace it.
  // Products the seller chose to offer with this one come first; otherwise the page suggests the store's other categories.
  const page = p.page ?? {};
  const addons = (p.addons ?? []).filter((r) => r.id !== p.id);
  const pairs = addons.length > 0 ? addons : others.filter((r) => r.category !== p.category);
  const lead = page.headline || (p.description ?? "").split("\n").filter(Boolean)[0] || "";
  const sameCategory = others.filter((r) => r.category === p.category);

  // A bar with the price and button appears once the main button has scrolled out of sight.
  useEffect(() => {
    const el = cta.current;
    if (!el || typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(([entry]) => setStuck(!entry.isIntersecting && entry.boundingClientRect.top < 0));
    io.observe(el);

    return () => io.disconnect();
  }, []);

  const reviews = data.reviews ?? { count: 0, average: null, items: [] };
  // The store's public phone, so a customer who would rather ask first can do it on WhatsApp.
  const phoneDigits = (data.store.contact_phone ?? store.contact_phone ?? "").replace(/\D/g, "");
  const whatsapp = phoneDigits.length >= 8 ? `https://wa.me/${phoneDigits}?text=${encodeURIComponent(`${p.name}`)}` : null;

  return { p, store, page, lead, reviews, whatsapp, feePercent: data.customer_fee_percentage ?? 0, goods, links, parts, setParts, mode, href, options, picked, setPicked, chosen, shown, was, saving, cta, stuck, paragraphs, multiOptions: options.length > 1, others, cheaper, pricier, pairs, sameCategory };
}
