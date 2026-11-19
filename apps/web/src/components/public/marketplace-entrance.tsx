"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { countryName } from "@/components/public/landing";
import { useEditable, useSiteText, useSiteValue } from "@/components/public/site-content";
import { Rail } from "@/components/shop/rail";
import { tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import type { PublicProduct, PublicShowcase, PublicStoreCard } from "@/types/public";

/** Shown when the server could not be reached (a sleeping instance takes up to a minute), instead of claiming there are no markets. Tries again by itself. */
function WakingUp() {
  const router = useRouter();
  useEffect(() => { const id = setTimeout(() => router.refresh(), 6000); return () => clearTimeout(id); }, [router]);

  return (
    <div className="mt-4 rounded-2xl border border-dashed border-border p-8 text-center text-slate" role="status">
      <p className="font-semibold text-obsidian">{tr("The service is waking up.")}</p>
      <p className="mt-1 text-sm">{tr("This can take up to a minute the first time. This page will refresh by itself.")}</p>
      <button type="button" onClick={() => router.refresh()} className="btn-brand mt-4 px-5 py-2.5 text-sm">{tr("Try again")}</button>
    </div>
  );
}

/** The platform's banner: what it is and the way to a market. The words and picture are the owners' to change in the theme editor. */
export function PlatformBanner() {
  const badge = useSiteText("home", "badge_text", "Saudi Arabia · Egypt");
  const title = useSiteText("home", "hero_title", "Find what you need. Compare prices. Buy from sellers you trust.");
  const subtitle = useSiteText("home", "hero_subtitle", "A marketplace platform where any industry can open its own market: customers find trusted sellers and service providers, and every market works the same way.");
  const cta = useSiteText("home", "primary_cta", "Choose your market");
  const image = useSiteValue("home", "hero_image_url");
  const ed = useEditable();

  return (
    <section className="mx-auto max-w-[1240px] px-4 pt-4 md:px-10" aria-label={tr("Welcome")} data-testid="platform-banner">
      <div className="relative overflow-hidden rounded-2xl bg-brand-wash">
        {image && (
          <>
            {/* eslint-disable-next-line @next/next/no-img-element -- an image chosen by the platform's owners */}
            <img src={imageSrc(image) ?? ""} alt="" className="absolute inset-0 h-full w-full object-cover" />
            <div className="absolute inset-0 bg-gradient-to-l from-black/55 via-black/20 to-transparent rtl:bg-gradient-to-r" aria-hidden="true" />
          </>
        )}
        <div className={`relative flex min-h-[230px] flex-col justify-center gap-3 px-6 py-9 sm:min-h-[300px] sm:px-12 ${image ? "text-white" : "text-obsidian"}`}>
          <span {...ed("badge_text")} className="text-sm font-semibold opacity-90">{badge}</span>
          <h1 {...ed("hero_title")} className="max-w-3xl text-3xl font-bold leading-tight tracking-tight sm:text-5xl">{title}</h1>
          <p {...ed("hero_subtitle")} className="max-w-2xl text-base opacity-90 sm:text-lg">{subtitle}</p>
          <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
            <a href="#stores" {...ed("primary_cta")} className="btn-brand inline-flex h-11 items-center rounded-full px-7 text-base font-bold">{cta}</a>
            <Link href="/seller-signup" className="text-sm font-semibold underline underline-offset-4">{tr("Sell with us")}</Link>
            <Link href="/partner-signup" className="text-sm underline underline-offset-4 opacity-90">{tr("Become a partner")}</Link>
          </div>
        </div>
      </div>
    </section>
  );
}

/** Every open market as a card (like choosing a country on a big marketplace), then a row of products from each. */
export function MarketShelf({ stores, showcase, unavailable, title }: { stores: PublicStoreCard[]; showcase: PublicShowcase; unavailable: boolean; title: string }) {
  // Products per market, from every industry the market runs.
  const bySlug = new Map<string, PublicProduct[]>();
  for (const i of showcase.industries) for (const s of i.stores) bySlug.set(s.slug, [...(bySlug.get(s.slug) ?? []), ...s.products]);

  return (
    <section id="stores" className="mx-auto max-w-[1240px] scroll-mt-20 px-4 pb-6 pt-6 md:px-10" aria-labelledby="markets-h">
      <h2 id="markets-h" className="mb-3 text-xl font-bold">{title}</h2>
      {stores.length === 0 && unavailable ? <WakingUp /> : stores.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-border p-8 text-center text-slate">{tr("No markets are open yet. Please check back soon.")}</p>
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-testid="market-cards">
            {stores.map((s) => (
              <Link key={s.slug} href={`/s/${s.slug}`} className="group flex flex-col rounded-2xl border border-border bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                <div className="flex items-center gap-3">
                  {s.logo_url
                    // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
                    ? <img src={imageSrc(s.logo_url) ?? ""} alt="" className="size-11 rounded-xl object-cover" />
                    : <span className="grid size-11 place-items-center rounded-xl text-lg font-bold" style={{ backgroundColor: s.primary_color, color: "#1a1a1a" }} aria-hidden="true">{s.name.slice(0, 1)}</span>}
                  <div className="min-w-0">
                    <h3 className="truncate text-lg font-bold text-obsidian">{s.name}</h3>
                    {s.market && <p className="text-xs text-slate">{countryName(s.market)}</p>}
                  </div>
                </div>
                {s.headline && <p className="mt-3 line-clamp-2 text-sm text-slate">{s.headline}</p>}
                {s.industry && <p className="mt-2 text-xs font-semibold text-brand-ink">{tr(s.industry.name)}</p>}
                <span className="btn-brand mt-4 inline-flex h-10 items-center justify-center self-start px-5 text-sm font-bold group-hover:brightness-95">{tr("Shop now")}</span>
              </Link>
            ))}
          </div>
          {stores.map((s) => {
            const products = (bySlug.get(s.slug) ?? []).slice(0, 10);
            return <Rail key={s.slug} id={`rail-${s.slug}`} title={tr("Popular in {name}", { name: s.name })} href={`/s/${s.slug}/products`} more={tr("See all")} products={products} slug={s.slug} />;
          })}
        </>
      )}
    </section>
  );
}
