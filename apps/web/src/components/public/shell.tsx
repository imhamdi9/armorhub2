"use client";

import Link from "next/link";
import { useEffect, useState, type CSSProperties } from "react";

import { Icon, LogoMark } from "@/components/public/art";
import { LanguageSwitcher, tr } from "@/lib/i18n";
import { textOn } from "@/lib/public-api";
import { me } from "@/services/auth.service";
import type { User } from "@/types/auth";

export interface ShellStore {
  name: string;
  slug: string;
  logo_url?: string | null;
  primary_color?: string;
  contact_email?: string | null;
  contact_phone?: string | null;
  market?: { code: string; currency: string } | null;
}

const DEFAULT_COLOR = "#111827";

/** Whether the visitor is signed in, without ever redirecting them. */
function useSession() {
  const [user, setUser] = useState<User | null | undefined>(undefined);

  useEffect(() => {
    me().then(setUser).catch(() => setUser(null));
  }, []);

  return user;
}

/** Sign in / sign up, or a Dashboard link for people who are already signed in. */
export function AuthLinks({ store }: { store?: ShellStore }) {
  const user = useSession();
  const signup = store ? `/register?store=${encodeURIComponent(store.slug)}` : "/register";

  if (user) {
    return (
      <Link href="/dashboard" className="btn-brand rounded-lg px-4 py-2 text-sm font-medium">
        {tr("Dashboard")}
      </Link>
    );
  }

  return (
    <div className="flex items-center gap-2">
      <Link href="/login" className="rounded-lg px-3 py-2 text-sm hover:bg-black/5">{tr("Sign in")}</Link>
      <Link href={signup} className="btn-brand rounded-lg px-4 py-2 text-sm font-medium">{tr("Sign up")}</Link>
    </div>
  );
}

const COUNTRIES = [["SA", "Saudi Arabia"], ["EG", "Egypt"]];

function Header({ store }: { store?: ShellStore }) {
  const [open, setOpen] = useState(false);
  const home = store ? `/s/${store.slug}` : "/";
  const links: [string, string][] = store
    ? [[`/s/${store.slug}/products`, "Products"], [`${home}#how`, "How it works"], [`${home}#faq`, "FAQ"]]
    : [["/#stores", "Stores"], ["/#partners", "Partners"], ["/#how", "How it works"], ["/#faq", "FAQ"]];

  return (
    <header className="sticky top-0 z-40 border-b bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
        <Link href={home} className="flex items-center gap-2 text-lg font-bold tracking-tight">
          {store?.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
            <img src={store.logo_url} alt="" className="h-9 w-auto max-w-[9rem] object-contain" />
          ) : (
            <LogoMark />
          )}
          <span>{store?.name ?? tr("ArmorHub")}</span>
        </Link>

        <nav className="hidden items-center gap-1 text-sm md:flex" aria-label={tr("Main")}>
          {links.map(([href, label]) => (
            <Link key={label} href={href} className="rounded-lg px-3 py-2 font-medium text-gray-700 hover:bg-black/5">{tr(label)}</Link>
          ))}
        </nav>

        <div className="flex items-center gap-1">
          {store?.market && (
            <span className="hidden rounded-full border px-2.5 py-1 text-xs font-medium text-gray-600 lg:inline" dir="ltr">{store.market.code} · {store.market.currency}</span>
          )}
          <LanguageSwitcher className="mx-1" />
          <div className="hidden sm:block"><AuthLinks store={store} /></div>
          <button type="button" className="grid h-10 w-10 place-items-center rounded-lg border md:hidden" aria-expanded={open} aria-label={tr("Menu")} onClick={() => setOpen((v) => !v)}>
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              {open ? <path d="M6 6l12 12M18 6 6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
            </svg>
          </button>
        </div>
      </div>

      {open && (
        <div className="border-t bg-white px-4 py-3 md:hidden">
          <nav className="flex flex-col" aria-label={tr("Main")}>
            {links.map(([href, label]) => (
              <Link key={label} href={href} onClick={() => setOpen(false)} className="rounded-lg px-3 py-3 font-medium hover:bg-black/5">{tr(label)}</Link>
            ))}
          </nav>
          <div className="mt-2 border-t pt-3"><AuthLinks store={store} /></div>
        </div>
      )}
    </header>
  );
}

function Footer({ store }: { store?: ShellStore }) {
  const home = store ? `/s/${store.slug}` : "/";
  const name = store?.name ?? tr("ArmorHub");
  const col = "text-sm font-semibold text-gray-900";
  const link = "block py-1 text-sm text-gray-600 hover:text-gray-900 hover:underline";

  return (
    <footer className="border-t bg-gray-50">
      <div className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <div className="flex items-center gap-2 text-lg font-bold">
            {store?.logo_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
              <img src={store.logo_url} alt="" className="h-8 w-auto max-w-[8rem] object-contain" />
            ) : (
              <LogoMark className="h-7 w-7" />
            )}
            {name}
          </div>
          <p className="mt-3 max-w-xs text-sm text-gray-600">
            {store ? tr("Book trusted professionals, compare quotes and pay securely. Every job is followed through to the warranty.") : tr("A marketplace that connects customers with trusted service providers, across automotive, home services, beauty, devices and more.")}
          </p>
        </div>

        <div>
          <h3 className={col}>{tr("Explore")}</h3>
          <div className="mt-2">
            {store ? <Link className={link} href={`${home}/products`}>{tr("Products")}</Link> : <Link className={link} href="/#stores">{tr("Stores")}</Link>}
            <Link className={link} href={`${home}#how`}>{tr("How it works")}</Link>
            <Link className={link} href={`${home}#faq`}>{tr("FAQ")}</Link>
          </div>
        </div>

        <div>
          <h3 className={col}>{store ? tr("Contact") : tr("Countries")}</h3>
          <div className="mt-2 text-sm text-gray-600">
            {store ? (
              <>
                {store.contact_email && <a className={link} href={`mailto:${store.contact_email}`}>{store.contact_email}</a>}
                {store.contact_phone && <p dir="ltr" className="py-1 text-start">{store.contact_phone}</p>}
                {!store.contact_email && !store.contact_phone && <p className="py-1">{tr("Use your account to message the store about an order.")}</p>}
              </>
            ) : (
              COUNTRIES.map(([code, label]) => <p key={code} className="py-1">{tr(label)}</p>)
            )}
          </div>
        </div>

        <div>
          <h3 className={col}>{tr("Account")}</h3>
          <div className="mt-2">
            <Link className={link} href="/login">{tr("Sign in")}</Link>
            <Link className={link} href={store ? `/register?store=${encodeURIComponent(store.slug)}` : "/register"}>{tr("Create an account")}</Link>
            {!store && <Link className={link} href="/login">{tr("For businesses: sign in")}</Link>}
          </div>
        </div>
      </div>

      <div className="border-t">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-gray-500">
          <span>© {new Date().getFullYear()} {name}</span>
          <span className="flex items-center gap-4">
            <span className="inline-flex items-center gap-1"><Icon name="lock" className="h-4 w-4" /> {tr("Secure payments")}</span>
            <span className="inline-flex items-center gap-1"><Icon name="shield" className="h-4 w-4" /> {tr("Warranty on every job")}</span>
          </span>
          {store && <span>{tr("Powered by")} <Link href="/" className="underline">{tr("ArmorHub")}</Link></span>}
        </div>
      </div>
    </footer>
  );
}

/** Header, branded colours and footer shared by every public page. */
export function PublicShell({ store, children }: { store?: ShellStore; children: React.ReactNode }) {
  const color = store?.primary_color ?? DEFAULT_COLOR;
  // Both values are validated hex colours (see the editor API), never free text.
  const vars = { "--brand": color, "--on-brand": textOn(color) } as CSSProperties;

  return (
    <div style={vars} className="flex min-h-screen flex-col bg-white text-gray-900">
      <Header store={store} />
      <main className="flex-1">{children}</main>
      <Footer store={store} />
    </div>
  );
}
