"use client";

import Link from "next/link";
import { useEffect, useState, type CSSProperties } from "react";

import { themeVars, type ShellStore } from "@/components/public/shell";
import { LanguageSwitcher, tr } from "@/lib/i18n";
import { me } from "@/services/auth.service";
import type { User } from "@/types/auth";

export const SHOP_SHADOW = "0 4px 6px -1px rgba(0,0,0,.1), 0 2px 4px -2px rgba(0,0,0,.1)";

const icon = (d: string) => (
  <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={d} /></svg>
);
const ICONS = {
  home: icon("M3 11.5 12 4l9 7.5M5 10v10h5v-6h4v6h5V10"),
  grid: icon("M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"),
  user: icon("M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21a8 8 0 0 1 16 0"),
};

/** Signed-in state without ever redirecting a visitor. */
function useSession() {
  const [user, setUser] = useState<User | null | undefined>(undefined);
  useEffect(() => { me().then(setUser).catch(() => setUser(null)); }, []);
  return user;
}

/**
 * The "Shop" look for a market's storefront: grey canvas, a slim icon rail, white rounded cards, pill controls and ONE accent,
 * which is the store's own colour. The platform's home page keeps its own SaaS look; only /s/<slug> pages use this.
 */
export function ShopShell({ store, active, children }: { store: ShellStore; active?: "home" | "products"; children: React.ReactNode }) {
  const user = useSession();
  const home = `/s/${store.slug}`;
  const nav = [
    { key: "home", href: home, label: tr("Home"), icon: ICONS.home },
    { key: "products", href: `${home}/products`, label: tr("Products"), icon: ICONS.grid },
    { key: "account", href: user ? "/dashboard" : "/login", label: tr("Account"), icon: ICONS.user },
  ] as const;
  const signup = `/register?store=${encodeURIComponent(store.slug)}`;
  const link = "block py-1 text-sm text-white/70 hover:text-white hover:underline";

  return (
    <div style={themeVars(store) as CSSProperties} className="min-h-screen bg-[#f2f4f5] tracking-[-0.02em] text-black">
      <nav aria-label={tr("Main")} className="fixed inset-x-0 bottom-0 z-30 flex justify-around bg-white py-1 shadow-[0_-2px_8px_rgba(0,0,0,.06)] md:inset-y-0 md:end-auto md:start-0 md:w-16 md:flex-col md:items-center md:justify-start md:gap-1 md:py-4 md:shadow-none">
        {nav.map((n) => (
          <Link key={n.key} href={n.href} aria-label={n.label} aria-current={n.key === active ? "page" : undefined} title={n.label}
            className={`grid size-12 place-items-center rounded-[20px] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-black ${n.key === active ? "bg-[#f2f4f5]" : "hover:bg-[#f2f4f5]"} ${n.key === "account" ? "md:mt-auto" : ""}`}>
            {n.icon}
          </Link>
        ))}
      </nav>

      <div className="md:ps-16">
        <div className="mx-auto flex max-w-[1200px] items-center justify-end gap-2 px-4 pt-3">
          {store.market && <span dir="ltr" className="me-auto hidden rounded-full bg-white px-3 py-1 text-xs text-[#787574] sm:inline">{store.market.code} · {store.market.currency}</span>}
          <LanguageSwitcher />
          {user ? (
            <Link href="/dashboard" className="btn-brand inline-flex h-10 items-center rounded-full px-5 text-sm">{tr("Dashboard")}</Link>
          ) : (
            <>
              <Link href="/login" className="inline-flex h-10 items-center rounded-full bg-white px-4 text-sm" style={{ boxShadow: "0 2px 8px rgba(0,0,0,.06)" }}>{tr("Sign in")}</Link>
              <Link href={signup} className="btn-brand inline-flex h-10 items-center rounded-full px-5 text-sm">{tr("Sign up")}</Link>
            </>
          )}
        </div>

        <main className="mx-auto max-w-[1200px] px-4 pb-28 md:pb-16">{children}</main>

        <footer className="bg-black text-white">
          <div className="mx-auto grid max-w-[1200px] gap-8 px-6 py-12 sm:grid-cols-3">
            <div>
              <p className="text-lg tracking-[-0.04em]">{store.name}</p>
              <p className="mt-2 max-w-xs text-sm text-white/70">{tr("Book trusted professionals, compare quotes and pay securely. Every job is followed through to the warranty.")}</p>
            </div>
            <div>
              <p className="mb-2 text-sm">{tr("Explore")}</p>
              <Link className={link} href={`${home}/products`}>{tr("Products")}</Link>
              <Link className={link} href={`${home}#how`}>{tr("How it works")}</Link>
              <Link className={link} href={`${home}#faq`}>{tr("FAQ")}</Link>
            </div>
            <div>
              <p className="mb-2 text-sm">{tr("Account")}</p>
              <Link className={link} href="/login">{tr("Sign in")}</Link>
              <Link className={link} href={signup}>{tr("Create an account")}</Link>
              <Link className={link} href={`/partner-signup?store=${encodeURIComponent(store.slug)}`}>{tr("Become a partner")}</Link>
              {store.contact_email && <a className={link} href={`mailto:${store.contact_email}`}>{store.contact_email}</a>}
              {store.contact_phone && <p dir="ltr" className="py-1 text-start text-sm text-white/70">{store.contact_phone}</p>}
            </div>
          </div>
          <p className="border-t border-white/15 px-6 py-4 text-center text-xs text-white/60">© {new Date().getFullYear()} {store.name} · {tr("Powered by")} <Link href="/" className="underline">{tr("ArmorHub")}</Link></p>
        </footer>
      </div>
    </div>
  );
}
