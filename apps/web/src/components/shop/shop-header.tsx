"use client";

import Link from "next/link";
import { useCallback, useEffect, useRef, useState } from "react";

import type { ShellStore } from "@/components/public/shell";
import { ShopSearch } from "@/components/shop/shop-search";
import { useDepartments, type Department } from "@/lib/shop-categories";
import { setLocale, tr, useLocale } from "@/lib/i18n";
import { useMarketOffers } from "@/lib/market-offers";
import { CART_CHANGED, getCart } from "@/services/cart.service";
import { getOpenStores } from "@/services/auth.service";
import type { User } from "@/types/auth";

const stroke = { fill: "none", stroke: "currentColor", strokeWidth: 1.9, strokeLinecap: "round", strokeLinejoin: "round" } as const;
const Icon = ({ d, className = "size-5" }: { d: string; className?: string }) => <svg viewBox="0 0 24 24" className={className} aria-hidden="true" {...stroke}><path d={d} /></svg>;
const I = {
  menu: "M4 6h16M4 12h16M4 18h16", pin: "M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Zm0-8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z", cart: "M3 4h2l2.4 11h10.2L20 7H6.2M9 20a1 1 0 1 0 0-.01M17 20a1 1 0 1 0 0-.01",
  user: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0", chevron: "m6 9 6 6 6-6", close: "M6 6l12 12M18 6 6 18", back: "M15 6l-6 6 6 6", next: "M9 6l6 6-6 6", globe: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z",
};

/** How many items are in the cart, kept current when anything is added or removed. 0 for someone with no cart yet. */
function useCartCount(): number {
  const [n, setN] = useState(0);
  const load = useCallback(() => { getCart().then((c) => setN(c.items.reduce((s, l) => s + l.quantity, 0))).catch(() => setN(0)); }, []);
  useEffect(() => { load(); window.addEventListener(CART_CHANGED, load); return () => window.removeEventListener(CART_CHANGED, load); }, [load]);

  return n;
}

/** A small popover that closes on outside click and Escape. */
function usePopover() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!open) return;
    const down = (e: MouseEvent) => { if (!ref.current?.contains(e.target as Node)) setOpen(false); };
    const key = (e: KeyboardEvent) => { if (e.key === "Escape") setOpen(false); };
    document.addEventListener("mousedown", down); document.addEventListener("keydown", key);
    return () => { document.removeEventListener("mousedown", down); document.removeEventListener("keydown", key); };
  }, [open]);

  return { open, setOpen, ref };
}

/** Language, the market you shop in (and with it the currency) in one place. */
function SettingsFlyout({ store }: { store: ShellStore }) {
  const { open, setOpen, ref } = usePopover();
  const locale = useLocale();
  const [markets, setMarkets] = useState<{ name: string; slug: string; market: string | null }[]>([]);
  useEffect(() => { if (open && markets.length === 0) getOpenStores().then(setMarkets).catch(() => undefined); }, [open, markets.length]);
  const row = "flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start text-sm hover:bg-fog";
  const dot = (on: boolean) => <span aria-hidden="true" className={`grid size-4 place-items-center rounded-full border-2 ${on ? "border-obsidian" : "border-slate/50"}`}>{on && <span className="size-2 rounded-full bg-obsidian" />}</span>;

  return (
    <div ref={ref} className="relative">
      <button type="button" onClick={() => setOpen((v) => !v)} aria-expanded={open} aria-haspopup="dialog" data-testid="settings-flyout"
        className="inline-flex h-10 items-center gap-1.5 rounded-full bg-fog px-3 text-sm font-semibold hover:bg-[#e7ebec]">
        <Icon d={I.globe} className="size-4" />
        <span dir="ltr">{locale === "ar" ? "ع" : "EN"}{store.market ? ` · ${store.market.currency}` : ""}</span>
        <Icon d={I.chevron} className="size-3.5" />
      </button>
      {open && (
        <div role="dialog" aria-label={tr("Language, currency and market")} className="absolute end-0 top-full z-50 mt-2 w-72 rounded-2xl border border-border bg-white p-3 text-obsidian shadow-xl">
          <p className="px-2 pb-1 text-xs font-bold text-slate">{tr("Language")}</p>
          <button type="button" className={row} onClick={() => { setLocale("ar"); setOpen(false); }} aria-pressed={locale === "ar"}>{dot(locale === "ar")}العربية</button>
          <button type="button" className={row} onClick={() => { setLocale("en"); setOpen(false); }} aria-pressed={locale === "en"}>{dot(locale === "en")}English</button>
          <div className="my-2 border-t border-border" />
          <p className="px-2 pb-1 text-xs font-bold text-slate">{tr("Currency")}</p>
          <p className="px-2 text-sm"><span dir="ltr" className="font-semibold">{store.market?.currency ?? "-"}</span></p>
          <p className="px-2 pb-1 text-xs text-slate">{tr("Prices are in the currency of the market you shop in. To change the currency, change the market.")}</p>
          <div className="my-2 border-t border-border" />
          <p className="px-2 pb-1 text-xs font-bold text-slate">{tr("You are shopping in")}</p>
          {(markets.length ? markets : [{ name: store.name, slug: store.slug, market: store.market?.code ?? null }]).map((m) => (
            <Link key={m.slug} href={`/s/${m.slug}`} onClick={() => setOpen(false)} className={row} aria-current={m.slug === store.slug ? "true" : undefined}>{dot(m.slug === store.slug)}<span className="flex-1">{m.name}</span>{m.market && <span dir="ltr" className="text-xs text-slate">{m.market}</span>}</Link>
          ))}
        </div>
      )}
    </div>
  );
}

/** "All": every department, then its categories (like a shop's side drawer), plus the places that are not products. */
function AllMenu({ store, departments, user, onClose }: { store: ShellStore; departments: Department[]; user: User | null | undefined; onClose: () => void }) {
  const [group, setGroup] = useState<Department | null>(null);
  const offers = useMarketOffers();
  const base = `/s/${store.slug}/products`;
  const item = "flex w-full items-center justify-between gap-2 px-5 py-2.5 text-start text-[15px] hover:bg-fog";
  useEffect(() => { const key = (e: KeyboardEvent) => { if (e.key === "Escape") onClose(); }; document.addEventListener("keydown", key); return () => document.removeEventListener("keydown", key); }, [onClose]);

  return (
    <div className="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label={tr("All departments")} data-testid="all-menu">
      <button type="button" aria-label={tr("Close")} className="absolute inset-0 bg-black/45" onClick={onClose} />
      <div className="absolute inset-y-0 start-0 w-[320px] max-w-[88vw] overflow-y-auto bg-white text-obsidian shadow-2xl">
        <div className="flex items-center justify-between bg-[var(--brand)] px-5 py-3 text-[var(--on-brand)]">
          <span className="flex items-center gap-2 font-semibold"><Icon d={I.user} />{user && !user.is_guest ? `${tr("Hello")}, ${user.name}` : tr("Hello, sign in")}</span>
          <button type="button" aria-label={tr("Close")} onClick={onClose}><Icon d={I.close} /></button>
        </div>
        {group ? (
          <div>
            <button type="button" onClick={() => setGroup(null)} className="flex w-full items-center gap-2 border-b border-border px-5 py-3 text-start text-sm font-semibold text-slate hover:bg-fog"><Icon d={I.back} className="size-4 rtl:-scale-x-100" />{tr("All departments")}</button>
            <p className="px-5 pb-1 pt-4 text-lg font-bold">{group.name}</p>
            <Link href={`${base}?category=${encodeURIComponent(group.key)}`} onClick={onClose} className={item}>{tr("All of")} {group.name}<span className="text-xs text-slate">{group.count}</span></Link>
            {group.children.map((c) => <Link key={c.key} href={`${base}?category=${encodeURIComponent(c.key)}`} onClick={onClose} className={item}>{c.name}<span className="text-xs text-slate">{c.count}</span></Link>)}
          </div>
        ) : (
          <div className="pb-6">
            <p className="px-5 pb-1 pt-4 text-lg font-bold">{tr("Shop by department")}</p>
            {departments.map((d) => (
              d.children.length > 0
                ? <button key={d.key} type="button" onClick={() => setGroup(d)} className={item}>{d.name}<Icon d={I.next} className="size-4 text-slate rtl:-scale-x-100" /></button>
                : <Link key={d.key} href={`${base}?category=${encodeURIComponent(d.key)}`} onClick={onClose} className={item}>{d.name}<span className="text-xs text-slate">{d.count}</span></Link>
            ))}
            <Link href={base} onClick={onClose} className={`${item} text-[var(--brand-ink)]`}>{tr("See all products")}</Link>
            <div className="my-2 border-t border-border" />
            <p className="px-5 pb-1 pt-2 text-lg font-bold">{tr("Services and programs")}</p>
            <Link href={`${base}?sort=rating`} onClick={onClose} className={item}>{tr("Top rated")}</Link>
            {(offers.installationStores.includes(store.slug) || !offers.ready) && <Link href={user && !user.is_guest ? "/dashboard/installations/new" : "/login"} onClick={onClose} className={item}>{tr("Installation services")}</Link>}
            {offers.selling && <Link href="/seller-signup" onClick={onClose} className={item}>{tr("Sell with us")}</Link>}
            {(offers.installationStores.includes(store.slug) || !offers.ready) && <Link href={`/partner-signup?store=${encodeURIComponent(store.slug)}`} onClick={onClose} className={item}>{tr("Become a partner")}</Link>}
            <div className="my-2 border-t border-border" />
            <p className="px-5 pb-1 pt-2 text-lg font-bold">{tr("Help and settings")}</p>
            <Link href={user && !user.is_guest ? "/dashboard" : "/login"} onClick={onClose} className={item}>{tr("Your account")}</Link>
            <Link href={`/s/${store.slug}/cart`} onClick={onClose} className={item}>{tr("Cart")}</Link>
          </div>
        )}
      </div>
    </div>
  );
}

/**
 * The store's header: logo, where you shop, one search box with a department choice, language and market, account, cart;
 * and under it the "All" button with the main departments. White, with the store's own colour as the accent.
 */
export function ShopHeader({ store, user, active }: { store: ShellStore; user: User | null | undefined; active?: "home" | "products" }) {
  const home = `/s/${store.slug}`;
  const departments = useDepartments(store.slug);
  const count = useCartCount();
  const [menu, setMenu] = useState(false);
  const offers = useMarketOffers();
  const signedIn = !!user && !user.is_guest;
  const close = useCallback(() => setMenu(false), []);
  const pill = "inline-flex h-10 items-center gap-1.5 rounded-full bg-fog px-3 text-sm font-semibold hover:bg-[#e7ebec]";

  return (
    <div className="border-b border-border bg-white" data-testid="shop-header">
      <header className="mx-auto flex max-w-[1280px] flex-wrap items-center gap-x-3 gap-y-2 px-4 py-2.5 lg:flex-nowrap">
        <button type="button" onClick={() => setMenu(true)} aria-label={tr("All departments")} className="grid size-10 place-items-center rounded-full bg-fog lg:hidden"><Icon d={I.menu} /></button>
        <Link href={home} className="font-display flex shrink-0 items-center gap-2 text-[20px] font-bold tracking-tight">
          {store.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
            <img src={store.logo_url} alt="" className="size-9 rounded-xl object-cover" />
          ) : (
            <span className="btn-brand grid size-9 place-items-center rounded-xl"><svg viewBox="0 0 24 24" className="size-5" fill="currentColor" aria-hidden="true"><path d="M12 2 4 5v6c0 5 3.4 9.3 8 11 4.6-1.7 8-6 8-11V5l-8-3Z" /></svg></span>
          )}
          <span className="max-w-[9rem] truncate">{store.name}</span>
        </Link>
        <Link href={`${home}`} className="hidden h-10 shrink-0 items-center gap-1.5 rounded-full px-2 text-start text-xs leading-tight hover:bg-fog xl:flex">
          <Icon d={I.pin} className="size-5 text-slate" />
          <span><span className="block text-slate">{tr("Deliver to")}</span><span className="block text-sm font-bold">{store.market?.code ? store.market.code : store.name}</span></span>
        </Link>
        <div className="order-last w-full lg:order-none lg:w-auto lg:flex-1">
          <ShopSearch action={`${home}/products`} slug={store.slug} compact departments={departments} />
        </div>
        <div className="ms-auto flex items-center gap-2 lg:ms-0">
          <SettingsFlyout store={store} />
          <Link href={signedIn ? "/dashboard" : "/login"} className={`${pill} hidden sm:inline-flex`} data-testid="shop-account">
            <Icon d={I.user} className="size-4" />
            <span className="leading-tight"><span className="hidden text-[11px] font-normal text-slate md:block">{signedIn ? tr("Hello") + ", " + (user?.name ?? "") : tr("Hello, sign in")}</span><span className="hidden md:block">{tr("Account")}</span><span className="md:hidden">{tr("Account")}</span></span>
          </Link>
          <Link href={`${home}/cart`} className="relative inline-flex h-10 items-center gap-2 rounded-full bg-[var(--brand)] px-4 text-sm font-bold text-[var(--on-brand)] hover:brightness-95" data-testid="shop-cart">
            <Icon d={I.cart} className="size-5" />{tr("Cart")}
            <span className="grid min-w-5 place-items-center rounded-full bg-obsidian px-1.5 text-[11px] font-bold text-white" data-testid="cart-count">{count}</span>
          </Link>
        </div>
      </header>
      <nav aria-label={tr("Departments")} className="hidden border-t border-border lg:block">
        <div className="mx-auto flex max-w-[1280px] items-center gap-1 overflow-x-auto px-4 py-1.5 text-sm font-semibold">
          <button type="button" onClick={() => setMenu(true)} className="inline-flex h-9 shrink-0 items-center gap-2 rounded-full bg-obsidian px-4 text-white" data-testid="all-button"><Icon d={I.menu} className="size-4" />{tr("All departments")}</button>
          <Link href={`${home}/products`} aria-current={active === "products" ? "page" : undefined} className="shrink-0 rounded-full px-3 py-1.5 hover:bg-fog">{tr("Products")}</Link>
          {departments.slice(0, 6).map((d) => <Link key={d.key} href={`${home}/products?category=${encodeURIComponent(d.key)}`} className="shrink-0 rounded-full px-3 py-1.5 hover:bg-fog">{d.name}</Link>)}
          <Link href={`${home}/products?sort=rating`} className="shrink-0 rounded-full px-3 py-1.5 hover:bg-fog">{tr("Top rated")}</Link>
          {offers.selling && <Link href="/seller-signup" className="ms-auto shrink-0 rounded-full px-3 py-1.5 text-slate hover:bg-fog">{tr("Sell with us")}</Link>}
        </div>
      </nav>
      {menu && <AllMenu store={store} departments={departments} user={user} onClose={close} />}
    </div>
  );
}
