"use client";

import { imageSrc } from "@/lib/surfaces";
import Link from "next/link";

import { ProductCard } from "@/components/public/product-card";
import { HeroArt } from "@/components/public/art";
import { Faq } from "@/components/public/faq";
import { Banner } from "@/components/public/landing";
import { Steps, StatsBand, WhyUs } from "@/components/public/sections";
import { PublicShell } from "@/components/public/shell";
import { formatNumber, tr } from "@/lib/i18n";
import type { PublicStore } from "@/types/public";

const STEPS = [
  ["1", "Choose a product", "Pick a service and your options. You see the price immediately."],
  ["2", "Compare quotes", "Nearby professionals send you their price for the job."],
  ["3", "Pay and relax", "Accept a quote and pay online. We follow the work through to the warranty."],
];

/** One store's front page, in its own colours. */
export function StoreHome({ data }: { data: PublicStore }) {
  const { store, industry, featured, categories, partners } = data;
  const signup = `/register?store=${encodeURIComponent(store.slug)}`;

  return (
    <PublicShell store={{ ...store, market: data.market }}>
      <section className="overflow-hidden bg-white">
        <div className="mx-auto max-w-[1200px] px-4 pb-6 pt-14 text-center md:pt-20">
          {industry && <span className="inline-flex items-center gap-2 rounded-full bg-brand-wash px-4 py-1.5 text-sm font-semibold text-brand-ink"><span className="h-2 w-2 rounded-full" style={{ backgroundColor: "var(--brand)" }} aria-hidden="true" />{tr(industry.name)}</span>}
          <h1 className="display display-xl mx-auto mt-6 max-w-5xl uppercase">
            {store.headline ?? tr("Quality {service} services, quoted by experts near you", { service: industry ? tr(industry.name) : "" })}
          </h1>
          {store.subheadline && <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-charcoal">{store.subheadline}</p>}
          <div className="mt-8 flex flex-wrap items-center justify-center gap-5">
            <Link href={`/s/${store.slug}/products`} className="btn-brand inline-flex items-center gap-2 px-7 py-3.5 text-base">{tr("Browse products")} <span aria-hidden="true">→</span></Link>
            <Link href={signup} className="text-base font-semibold text-brand-ink underline underline-offset-4">{tr("Create an account")}</Link>
          </div>
          <p className="mt-4 text-sm text-slate">{tr("Free to request quotes. No obligation.")}</p>
        </div>
        <div className="mx-auto max-w-[1000px] px-4 pb-12">
          {store.hero_image_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https photo chosen by the store owner
            <img src={imageSrc(store.hero_image_url) ?? ""} alt="" className="aspect-[16/8] w-full rounded-[28px] object-cover shadow-lift" />
          ) : (
            <div className="rounded-[28px] bg-brand-wash p-6"><HeroArt kind={industry?.key} className="mx-auto max-h-[22rem] w-full" /></div>
          )}
        </div>
      </section>

      <StatsBand
        items={[
          { value: partners.count, label: "Vetted professionals" },
          { value: partners.cities.length, label: "Cities covered" },
          { value: Math.max(0, ...featured.map((p) => p.warranty_years ?? 0)), label: "Years of warranty", suffix: "+" },
          { value: data.market && data.market.prices_include_vat ? `${formatNumber(data.market.vat_rate)}%` : null, label: "VAT included in prices" },
        ]}
      />

      {featured.length > 0 && (
        <section className="mx-auto max-w-[1200px] px-4 py-12">
          <div className="mb-6 flex items-end justify-between">
            <h2 className="display display-md">{tr("Popular services")}</h2>
            <Link href={`/s/${store.slug}/products`} className="text-sm font-semibold text-brand-ink underline underline-offset-4">{tr("See all")}</Link>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {featured.map((p) => <ProductCard key={p.id} product={p} slug={store.slug} />)}
          </div>
        </section>
      )}

      {categories.length > 0 && (
        <section className="mx-auto max-w-[1200px] px-4 pb-8">
          <h2 className="mb-3 text-lg font-bold text-obsidian">{tr("Categories")}</h2>
          <div className="flex flex-wrap gap-2">
            {categories.map((c) => (
              <Link key={c} href={`/s/${store.slug}/products?category=${encodeURIComponent(c)}`} className="rounded-full bg-fog px-4 py-1.5 text-sm font-medium text-forest hover:bg-linen">{c}</Link>
            ))}
          </div>
        </section>
      )}

      <WhyUs />
      <Steps steps={STEPS} />

      {partners.count > 0 && (
        <section className="mx-auto max-w-[1200px] px-4 py-12">
          <h2 className="display display-md mb-2">{tr("Professionals near you")}</h2>
          <p className="text-slate">
            {tr("{n} vetted professionals are ready to quote your job.", { n: formatNumber(partners.count) })}
          </p>
          {partners.cities.length > 0 && (
            <p className="mt-2 text-sm text-slate">{tr("Available in")}: {partners.cities.join(" · ")}</p>
          )}
        </section>
      )}

      {(store.about || store.contact_email || store.contact_phone) && (
        <section className="border-t border-fog">
          <div className="mx-auto grid max-w-[1200px] gap-8 px-4 py-14 md:grid-cols-2">
            {store.about && (
              <div>
                <h2 className="display display-md mb-2">{tr("About us")}</h2>
                <p className="whitespace-pre-line text-charcoal">{store.about}</p>
              </div>
            )}
            {(store.contact_email || store.contact_phone) && (
              <div>
                <h2 className="display display-md mb-2">{tr("Contact")}</h2>
                {store.contact_email && <p><a className="font-semibold text-brand-ink underline" href={`mailto:${store.contact_email}`}>{store.contact_email}</a></p>}
                {store.contact_phone && <p dir="ltr" className="text-start">{store.contact_phone}</p>}
              </div>
            )}
          </div>
        </section>
      )}
      <Faq />
      <Banner title="Ready to get started?" text="Create a free account, choose a service and receive quotes from professionals near you." href={signup} label="Create an account" />
    </PublicShell>
  );
}
