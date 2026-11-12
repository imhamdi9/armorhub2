"use client";

import Link from "next/link";
import { useEffect, useState, type CSSProperties } from "react";

import { Icon, LogoMark } from "@/components/public/art";
import { useSite, useSiteText, useSiteValue } from "@/components/public/site-content";
import { LanguageSwitcher, tr, useLocale } from "@/lib/i18n";
import { FONT_STACKS, pickText } from "@/lib/surfaces";
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

/** Whether the visitor is signed in, without ever redirecting them. */
function useSession() {
  const [user, setUser] = useState<User | null | undefined>(undefined);

  useEffect(() => {
    me().then(setUser).catch(() => setUser(null));
  }, []);

  return user;
}

/** Colours for the page: the platform's forest and lime, or a store's own colour with matching dark and wash tones. */
export function themeVars(store?: ShellStore, brand?: { color?: string | null; dark?: string | null }): CSSProperties {
  if (!store?.primary_color) {
    const color = brand?.color ?? "#9fe870";
    const dark = brand?.dark ?? "#163300";

    // The platform's own colours, optionally changed by an admin (colours are validated #rrggbb values).
    if (brand?.color || brand?.dark) {
      return { "--brand": color, "--on-brand": color === "#9fe870" ? "#163300" : textOn(color), "--dark": dark, "--on-dark-accent": color, "--brand-wash": `color-mix(in srgb, ${color} 18%, white)`, "--brand-ink": dark } as CSSProperties;
    }

    return { "--brand": "#9fe870", "--on-brand": "#163300", "--dark": "#163300", "--on-dark-accent": "#9fe870", "--brand-wash": "#e2f6d5", "--brand-ink": "#163300" } as CSSProperties;
  }
  const c = store.primary_color; // a validated #rrggbb (see the editor API), never free text
  return {
    "--brand": c,
    "--on-brand": textOn(c),
    "--dark": `color-mix(in srgb, ${c} 30%, #0e0f0c)`,
    "--on-dark-accent": `color-mix(in srgb, ${c} 55%, white)`,
    "--brand-wash": `color-mix(in srgb, ${c} 14%, white)`,
    "--brand-ink": `color-mix(in srgb, ${c} 60%, #0e0f0c)`,
  } as CSSProperties;
}

/** Sign in / sign up, or a Dashboard link for people who are already signed in. */
export function AuthLinks({ store }: { store?: ShellStore }) {
  const user = useSession();
  const signup = store ? `/register?store=${encodeURIComponent(store.slug)}` : "/signup";

  if (user) {
    return <Link href="/dashboard" className="btn-brand px-5 py-2.5 text-sm">{tr("Dashboard")}</Link>;
  }

  return (
    <div className="flex items-center gap-1">
      <Link href="/login" className="rounded-full px-4 py-2.5 text-sm font-medium text-obsidian hover:bg-fog">{tr("Sign in")}</Link>
      <Link href={signup} className="btn-brand px-5 py-2.5 text-sm">{tr("Sign up")}</Link>
    </div>
  );
}

function Logo({ store, size = "h-9" }: { store?: ShellStore; size?: string }) {
  return store?.logo_url ? (
    // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
    <img src={store.logo_url} alt="" className={`${size} w-auto max-w-[9rem] object-contain`} />
  ) : (
    <LogoMark className={size === "h-9" ? "h-9 w-9" : "h-8 w-8"} />
  );
}

function Header({ store }: { store?: ShellStore }) {
  const [open, setOpen] = useState(false);
  const home = store ? `/s/${store.slug}` : "/";
  // Platform pages can carry an admin-edited name, logo and menu wording; stores always use their own.
  const siteName = useSiteText("brand", "name", "ArmorHub");
  const siteLogo = useSiteValue("brand", "logo_url");
  const navMarkets = useSiteText("nav", "nav_markets", "ArmorHub Markets");
  const navPartners = useSiteText("nav", "nav_partners", "Partners");
  const navHow = useSiteText("nav", "nav_how", "How it works");
  const navFaq = useSiteText("nav", "nav_faq", "FAQ");
  const links: [string, string][] = store
    ? [[`/s/${store.slug}/products`, tr("Products")], [`${home}#how`, tr("How it works")], [`${home}#faq`, tr("FAQ")]]
    : [["/#stores", navMarkets], ["/#partners", navPartners], ["/#how", navHow], ["/#faq", navFaq]];
  const shownStore = store ?? (siteLogo ? { name: siteName, slug: "", logo_url: siteLogo } : undefined);

  return (
    <header className="sticky top-0 z-40 border-b border-fog bg-white/95 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-[1200px] items-center justify-between gap-3 px-4">
        <Link href={home} className="flex items-center gap-2.5 text-xl font-black tracking-tight text-obsidian">
          <Logo store={shownStore} />
          <span>{store?.name ?? siteName}</span>
        </Link>

        <nav className="hidden items-center gap-1 rounded-full bg-fog p-1 text-sm md:flex" aria-label={tr("Main")}>
          {links.map(([href, label]) => (
            <Link key={label} href={href} className="rounded-full px-4 py-1.5 font-medium text-forest transition hover:bg-white">{label}</Link>
          ))}
        </nav>

        <div className="flex items-center gap-1">
          {store?.market && (
            <span className="hidden rounded-full bg-fog px-3 py-1 text-xs font-semibold text-forest lg:inline" dir="ltr">{store.market.code} · {store.market.currency}</span>
          )}
          <LanguageSwitcher className="mx-1" />
          <div className="hidden sm:block"><AuthLinks store={store} /></div>
          <button type="button" className="grid h-10 w-10 place-items-center rounded-full border border-forest md:hidden" aria-expanded={open} aria-label={tr("Menu")} onClick={() => setOpen((v) => !v)}>
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
              {open ? <path d="M6 6l12 12M18 6 6 18" /> : <path d="M4 7h16M4 12h16M4 17h16" />}
            </svg>
          </button>
        </div>
      </div>

      {open && (
        <div className="border-t border-fog bg-white px-4 py-3 md:hidden">
          <nav className="flex flex-col" aria-label={tr("Main")}>
            {links.map(([href, label]) => (
              <Link key={label} href={href} onClick={() => setOpen(false)} className="rounded-xl px-3 py-3 font-semibold text-forest hover:bg-fog">{label}</Link>
            ))}
          </nav>
          <div className="mt-2 border-t border-fog pt-3"><AuthLinks store={store} /></div>
        </div>
      )}
    </header>
  );
}

const COUNTRIES = [["SA", "Saudi Arabia"], ["EG", "Egypt"]];

function Footer({ store }: { store?: ShellStore }) {
  const home = store ? `/s/${store.slug}` : "/";
  const siteName = useSiteText("brand", "name", "ArmorHub");
  const siteLogo = useSiteValue("brand", "logo_url");
  const blurb = useSiteText("home", "footer_blurb", "A marketplace that connects customers with trusted service providers, across automotive, home services, beauty, devices and more.");
  const name = store?.name ?? siteName;
  const shownStore = store ?? (siteLogo ? { name: siteName, slug: "", logo_url: siteLogo } : undefined);
  const col = "text-sm font-bold text-on-dark-accent";
  const link = "block py-1 text-sm text-white/75 hover:text-white hover:underline";

  return (
    <footer className="bg-dark">
      <div className="mx-auto grid max-w-[1200px] gap-10 px-4 py-14 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <div className="flex items-center gap-2.5 text-xl font-black tracking-tight text-white">
            <Logo store={shownStore} size="h-8" />
            {name}
          </div>
          <p className="mt-4 max-w-xs text-sm leading-relaxed text-white/75">
            {store ? tr("Book trusted professionals, compare quotes and pay securely. Every job is followed through to the warranty.") : blurb}
          </p>
        </div>

        <div>
          <h3 className={col}>{tr("Explore")}</h3>
          <div className="mt-3">
            {store ? <Link className={link} href={`${home}/products`}>{tr("Products")}</Link> : <><Link className={link} href="/#stores">{tr("ArmorHub Markets")}</Link><Link className={link} href="/#partners">{tr("Partners")}</Link></>}
            <Link className={link} href={`${home}#how`}>{tr("How it works")}</Link>
            <Link className={link} href={`${home}#faq`}>{tr("FAQ")}</Link>
          </div>
        </div>

        <div>
          <h3 className={col}>{store ? tr("Contact") : tr("Countries")}</h3>
          <div className="mt-3 text-sm text-white/75">
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
          <div className="mt-3">
            <Link className={link} href="/login">{tr("Sign in")}</Link>
            <Link className={link} href={store ? `/register?store=${encodeURIComponent(store.slug)}` : "/signup"}>{tr("Create an account")}</Link>
            <Link className={link} href={store ? `/partner-signup?store=${encodeURIComponent(store.slug)}` : "/partner-signup"}>{tr("Become a partner")}</Link>
            <Link className={link} href="/seller-signup">{tr("Sell on ArmorHub")}</Link>
            {!store && <Link className={link} href="/login">{tr("For businesses: sign in")}</Link>}
          </div>
        </div>
      </div>

      <div className="border-t border-white/15">
        <div className="mx-auto flex max-w-[1200px] flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-white/65">
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

/** Header, themed colours and footer shared by every public page. */
export function PublicShell({ store, children }: { store?: ShellStore; children: React.ReactNode }) {
  const site = useSite();
  const locale = useLocale();
  const brand = store ? undefined : { color: pickText(site.brand, "en", "brand_color"), dark: pickText(site.brand, "en", "dark_color") };
  void locale;
  // Fonts apply to the platform's own pages only, chosen from a fixed list.
  const headingFont = store ? undefined : FONT_STACKS[pickText(site.brand, "en", "font_heading") ?? ""];
  const bodyFont = store ? undefined : FONT_STACKS[pickText(site.brand, "en", "font_body") ?? ""];
  const style = { ...themeVars(store, brand), ...(headingFont ? { "--theme-heading": headingFont } : {}), ...(bodyFont ? { fontFamily: bodyFont } : {}) } as CSSProperties;

  return (
    <div style={style} className="flex min-h-screen flex-col bg-white text-charcoal">
      <Header store={store} />
      <main className="flex-1">{children}</main>
      <Footer store={store} />
    </div>
  );
}
