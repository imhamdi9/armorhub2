"use client";

import Link from "next/link";

import { ProductCard } from "@/components/public/product-card";
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
    <PublicShell store={store}>
      <section className="bg-gray-50">
        <div className="mx-auto max-w-4xl px-4 py-14 text-center md:py-20">
          <h1 className="text-4xl font-bold tracking-tight md:text-5xl">
            {store.headline ?? tr("Quality {service} services, quoted by experts near you", { service: industry ? tr(industry.name) : "" })}
          </h1>
          {store.subheadline && <p className="mx-auto mt-4 max-w-2xl text-lg text-gray-600">{store.subheadline}</p>}
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link href={`/s/${store.slug}/products`} className="btn-brand rounded-lg px-6 py-3 font-medium">{tr("Browse products")}</Link>
            <Link href={signup} className="rounded-lg border bg-white px-6 py-3 font-medium hover:bg-gray-50">{tr("Create an account")}</Link>
          </div>
        </div>
      </section>

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

      <section className="bg-gray-50">
        <div className="mx-auto max-w-6xl px-4 py-12">
          <h2 className="mb-6 text-2xl font-bold">{tr("How it works")}</h2>
          <ol className="grid gap-4 md:grid-cols-3">
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
    </PublicShell>
  );
}
