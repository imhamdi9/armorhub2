"use client";

import Link from "next/link";

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
export function PlatformHome({ stores }: { stores: PublicStoreCard[] }) {
  return (
    <PublicShell>
      <section className="bg-gradient-to-b from-gray-50 to-white">
        <div className="mx-auto max-w-4xl px-4 py-16 text-center md:py-24">
          <h1 className="text-4xl font-bold tracking-tight md:text-5xl">{tr("Trusted services, quoted by experts near you")}</h1>
          <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">
            {tr("A marketplace that connects customers with trusted service providers, across automotive, home services, beauty, devices and more.")}
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <a href="#stores" className="btn-brand rounded-lg px-6 py-3 font-medium">{tr("Browse stores")}</a>
            <Link href="/login" className="rounded-lg border px-6 py-3 font-medium hover:bg-gray-50">{tr("Sign in")}</Link>
          </div>
        </div>
      </section>

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
                {s.industry && <p className="text-xs uppercase tracking-wide text-gray-500">{tr(s.industry.name)}</p>}
                {s.headline && <p className="mt-2 text-sm text-gray-600">{s.headline}</p>}
              </Link>
            ))}
          </div>
        )}
      </section>

      <section className="bg-gray-50">
        <div className="mx-auto max-w-6xl px-4 py-12">
          <h2 className="mb-6 text-2xl font-bold">{tr("How it works")}</h2>
          <ol className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {STEPS.map(([n, title, text]) => (
              <li key={n} className="rounded-xl bg-white p-5 shadow-sm">
                <span className="btn-brand inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">{n}</span>
                <h3 className="mt-3 font-semibold">{tr(title)}</h3>
                <p className="mt-1 text-sm text-gray-600">{tr(text)}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>
    </PublicShell>
  );
}
