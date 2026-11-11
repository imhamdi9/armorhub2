"use client";

import Link from "next/link";

import { HeroArt, Icon } from "@/components/public/art";
import { Faq } from "@/components/public/faq";
import { CallToAction, StatsBand, Steps, WhyUs } from "@/components/public/sections";
import { PublicShell } from "@/components/public/shell";
import { tr } from "@/lib/i18n";
import type { PublicStoreCard } from "@/types/public";

const INDUSTRIES = [
  { icon: "🚗", name: "Automotive", text: "Paint protection film, ceramic coating, window tint and detailing." },
  { icon: "🏠", name: "Home Services", text: "Cleaning, AC, plumbing, painting and window film for homes and buildings." },
  { icon: "💆", name: "Beauty & Wellness", text: "Salons, spas and clinics, booked with trusted professionals." },
  { icon: "📱", name: "Devices", text: "Screen protection, repair and care for phones, laptops and tablets." },
];

const STEPS = [
  ["1", "Choose what you need", "Pick a service and the options you want. You see a price straight away."],
  ["2", "Get quotes", "Nearby, vetted professionals send you their price for the job."],
  ["3", "Pick and pay securely", "Compare, accept the quote you like and pay online."],
  ["4", "Get it done", "Your professional schedules the work and you follow every step. Warranty included."],
];

/** The platform's front door: what it is, who it serves, and every open store. */
const COUNTRY_NAMES: Record<string, string> = { SA: "Saudi Arabia", EG: "Egypt" };

export function PlatformHome({ stores }: { stores: PublicStoreCard[] }) {
  return (
    <PublicShell>
      <section className="overflow-hidden" style={{ background: "linear-gradient(180deg, #f3f4f6, #fff)" }}>
        <div className="mx-auto grid max-w-6xl items-center gap-8 px-4 py-14 md:grid-cols-2 md:py-20">
          <div>
            <span className="inline-flex items-center gap-1.5 rounded-full border bg-white px-3 py-1 text-xs font-medium text-gray-600"><Icon name="globe" className="h-3.5 w-3.5" /> {tr("Saudi Arabia · Egypt")}</span>
            <h1 className="mt-4 text-4xl font-extrabold tracking-tight md:text-5xl">{tr("Trusted services, quoted by experts near you")}</h1>
            <p className="mt-4 max-w-xl text-lg text-gray-600">
              {tr("A marketplace that connects customers with trusted service providers, across automotive, home services, beauty, devices and more.")}
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <a href="#stores" className="btn-brand rounded-lg px-6 py-3 font-medium shadow-sm">{tr("Browse stores")}</a>
              <Link href="/login" className="rounded-lg border bg-white px-6 py-3 font-medium hover:bg-gray-50">{tr("Sign in")}</Link>
            </div>
          </div>
          <HeroArt kind="automotive" />
        </div>
      </section>

      <StatsBand
        items={[
          { value: stores.length, label: "Open stores" },
          { value: new Set(stores.map((s) => s.market).filter(Boolean)).size, label: "Countries" },
          { value: INDUSTRIES.length, label: "Industries" },
          { value: "AR · EN", label: "Languages" },
        ]}
      />

      <section className="mx-auto max-w-6xl px-4 py-12">
        <h2 className="mb-6 text-2xl font-bold">{tr("What you can get done")}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {INDUSTRIES.map((i) => (
            <div key={i.name} className="rounded-xl border p-5">
              <div className="text-3xl" aria-hidden="true">{i.icon}</div>
              <h3 className="mt-2 font-semibold">{tr(i.name)}</h3>
              <p className="mt-1 text-sm text-gray-600">{tr(i.text)}</p>
            </div>
          ))}
        </div>
      </section>

      <section id="stores" className="mx-auto max-w-6xl scroll-mt-4 px-4 py-12">
        <h2 className="mb-6 text-2xl font-bold">{tr("Stores")}</h2>
        {stores.length === 0 ? (
          <p className="rounded-xl border border-dashed p-8 text-center text-gray-500">{tr("No stores are open yet. Please check back soon.")}</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {stores.map((s) => (
              <Link key={s.slug} href={`/s/${s.slug}`} className="rounded-xl border p-5 transition hover:shadow-md">
                <div className="mb-3 h-1.5 w-12 rounded-full" style={{ backgroundColor: s.primary_color }} />
                <div className="flex items-center gap-2">
                  {s.logo_url && (
                    // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
                    <img src={s.logo_url} alt="" className="h-8 w-auto max-w-[6rem] object-contain" />
                  )}
                  <h3 className="text-lg font-semibold">{s.name}</h3>
                </div>
                {s.industry && <p className="text-xs uppercase tracking-wide text-gray-500">{tr(s.industry.name)}{s.market ? ` · ${tr(COUNTRY_NAMES[s.market] ?? s.market)}` : ""}</p>}
                {s.headline && <p className="mt-2 text-sm text-gray-600">{s.headline}</p>}
              </Link>
            ))}
          </div>
        )}
      </section>

      <WhyUs />
      <Steps steps={STEPS} columns={4} />
      <Faq />
      <CallToAction title="Run a business? Open your own store." text="Get a branded storefront, receive quote requests from nearby customers and get paid securely." href="/login" label="For businesses: sign in" />
    </PublicShell>
  );
}
