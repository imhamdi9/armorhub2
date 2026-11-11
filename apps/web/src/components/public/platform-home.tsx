"use client";

import Link from "next/link";

import { Banner, Bento, Compatibility, DashboardFeature, DevRow, factsOf, Hero, IndustryTabs, Markets, PartnerStrip, QuoteFeature, UseCases, ValueProps, WhySplit, COUNTRY_NAMES } from "@/components/public/landing";
import { Faq } from "@/components/public/faq";
import { PartnersShowcase } from "@/components/public/showcase";
import { PublicShell } from "@/components/public/shell";
import { tr } from "@/lib/i18n";
import type { PublicShowcase, PublicStoreCard } from "@/types/public";

const STEPS = [
  ["1", "Choose what you need", "Pick a service and the options you want. You see a price straight away."],
  ["2", "Get quotes", "Nearby, vetted professionals send you their price for the job."],
  ["3", "Pick and pay securely", "Compare, accept the quote you like and pay online."],
  ["4", "Get it done", "Your professional schedules the work and you follow every step. Warranty included."],
];

/** The platform's front door: what it is, who it serves, and every open store. */
export function PlatformHome({ stores, showcase }: { stores: PublicStoreCard[]; showcase: PublicShowcase }) {
  const facts = factsOf(stores, showcase);

  return (
    <PublicShell>
      <Hero facts={facts} firstStore={stores[0]} />
      <PartnerStrip showcase={showcase} facts={facts} />
      <IndustryTabs showcase={showcase} />

      <section id="stores" className="mx-auto max-w-[1200px] scroll-mt-20 px-4 py-14">
        <h2 className="display display-md">{tr("Stores")}</h2>
        {stores.length === 0 ? (
          <p className="mt-6 rounded-[20px] border border-dashed border-fog p-8 text-center text-slate">{tr("No stores are open yet. Please check back soon.")}</p>
        ) : (
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {stores.map((s) => (
              <Link key={s.slug} href={`/s/${s.slug}`} className="hover-lift rounded-[20px] border border-fog bg-white p-6">
                <div className="mb-4 h-2 w-14 rounded-full" style={{ backgroundColor: s.primary_color }} />
                <div className="flex items-center gap-2">
                  {s.logo_url && (
                    // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
                    <img src={s.logo_url} alt="" className="h-8 w-auto max-w-[6rem] object-contain" />
                  )}
                  <h3 className="text-xl font-bold text-obsidian">{s.name}</h3>
                </div>
                {s.industry && <p className="mt-1 text-xs font-bold uppercase tracking-wider text-spruce">{tr(s.industry.name)}{s.market ? ` · ${tr(COUNTRY_NAMES[s.market] ?? s.market)}` : ""}</p>}
                {s.headline && <p className="mt-3 text-sm text-slate">{s.headline}</p>}
              </Link>
            ))}
          </div>
        )}
      </section>

      <QuoteFeature />
      <DashboardFeature />
      <ValueProps />
      <PartnersShowcase data={showcase} />
      <UseCases showcase={showcase} />
      <WhySplit />

      <section id="how" className="scroll-mt-20 bg-white py-14">
        <div className="mx-auto max-w-[1200px] px-4">
          <h2 className="display display-md text-center">{tr("How it works")}</h2>
          <ol className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {STEPS.map(([n, title, text]) => (
              <li key={n} className="hover-lift rounded-[20px] border border-fog bg-white p-6">
                <span className="btn-brand inline-flex h-9 w-9 items-center justify-center text-sm font-black">{n}</span>
                <h3 className="mt-4 font-bold text-obsidian">{tr(title)}</h3>
                <p className="mt-1 text-sm text-slate">{tr(text)}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <Bento facts={facts} />
      <DevRow />
      <Compatibility />
      <Markets />
      <Faq />
      <Banner title="Run a business? Open your own store." text="Get a branded storefront, receive quote requests from nearby customers and get paid securely." href="/login" label="For businesses: sign in" secondary={{ href: "/signup", label: "Create an account" }} />
    </PublicShell>
  );
}
