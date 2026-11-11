"use client";

import Link from "next/link";

import { ProductCard } from "@/components/public/product-card";
import { HeroArt } from "@/components/public/art";
import { Faq } from "@/components/public/faq";
import { CallToAction, Steps, StatsBand, WhyUs } from "@/components/public/sections";
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
      <section className="relative overflow-hidden" style={{ background: "linear-gradient(180deg, color-mix(in srgb, var(--brand, #111827) 9%, white), #fff)" }}>
        <div className="mx-auto grid max-w-6xl items-center gap-8 px-4 py-14 md:grid-cols-2 md:py-20">
          <div>
            {industry && <span className="inline-block rounded-full border bg-white px-3 py-1 text-xs font-medium uppercase tracking-wide text-gray-600">{tr(industry.name)}</span>}
            <h1 className="mt-4 text-4xl font-extrabold tracking-tight md:text-5xl">
              {store.headline ?? tr("Quality {service} services, quoted by experts near you", { service: industry ? tr(industry.name) : "" })}
            </h1>
            {store.subheadline && <p className="mt-4 max-w-xl text-lg text-gray-600">{store.subheadline}</p>}
            <div className="mt-8 flex flex-wrap gap-3">
              <Link href={`/s/${store.slug}/products`} className="btn-brand rounded-lg px-6 py-3 font-medium shadow-sm">{tr("Browse products")}</Link>
              <Link href={signup} className="rounded-lg border bg-white px-6 py-3 font-medium hover:bg-gray-50">{tr("Create an account")}</Link>
            </div>
            <p className="mt-5 text-sm text-gray-500">{tr("Free to request quotes. No obligation.")}</p>
          </div>
          <div>
            {store.hero_image_url ? (
              // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https photo chosen by the store owner
              <img src={store.hero_image_url} alt="" className="aspect-[4/3] w-full rounded-3xl object-cover shadow-xl" />
            ) : (
              <HeroArt kind={industry?.key} />
            )}
          </div>
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
        <section className="mx-auto max-w-6xl px-4 py-12">
          <div className="mb-6 flex items-end justify-between">
            <h2 className="text-2xl font-bold">{tr("Popular services")}</h2>
            <Link href={`/s/${store.slug}/products`} className="text-sm underline">{tr("See all")}</Link>
          </div>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {featured.map((p) => <ProductCard key={p.id} product={p} slug={store.slug} />)}
          </div>
        </section>
      )}

      {categories.length > 0 && (
        <section className="mx-auto max-w-6xl px-4 pb-8">
          <h2 className="mb-3 text-lg font-semibold">{tr("Categories")}</h2>
          <div className="flex flex-wrap gap-2">
            {categories.map((c) => (
              <Link key={c} href={`/s/${store.slug}/products?category=${encodeURIComponent(c)}`} className="rounded-full border px-4 py-1.5 text-sm hover:bg-gray-50">{c}</Link>
            ))}
          </div>
        </section>
      )}

      <WhyUs />
      <Steps steps={STEPS} />

      {partners.count > 0 && (
        <section className="mx-auto max-w-6xl px-4 py-12">
          <h2 className="mb-2 text-2xl font-bold">{tr("Professionals near you")}</h2>
          <p className="text-gray-600">
            {tr("{n} vetted professionals are ready to quote your job.", { n: formatNumber(partners.count) })}
          </p>
          {partners.cities.length > 0 && (
            <p className="mt-2 text-sm text-gray-500">{tr("Available in")}: {partners.cities.join(" · ")}</p>
          )}
        </section>
      )}

      {(store.about || store.contact_email || store.contact_phone) && (
        <section className="border-t">
          <div className="mx-auto grid max-w-6xl gap-6 px-4 py-12 md:grid-cols-2">
            {store.about && (
              <div>
                <h2 className="mb-2 text-2xl font-bold">{tr("About us")}</h2>
                <p className="whitespace-pre-line text-gray-700">{store.about}</p>
              </div>
            )}
            {(store.contact_email || store.contact_phone) && (
              <div>
                <h2 className="mb-2 text-2xl font-bold">{tr("Contact")}</h2>
                {store.contact_email && <p><a className="underline" href={`mailto:${store.contact_email}`}>{store.contact_email}</a></p>}
                {store.contact_phone && <p dir="ltr" className="text-start">{store.contact_phone}</p>}
              </div>
            )}
          </div>
        </section>
      )}
      <Faq />
      <CallToAction title="Ready to get started?" text="Create a free account, choose a service and receive quotes from professionals near you." href={signup} label="Create an account" />
    </PublicShell>
  );
}
