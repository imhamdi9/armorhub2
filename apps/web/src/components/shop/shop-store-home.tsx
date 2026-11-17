"use client";

import Link from "next/link";

import { HeroArt } from "@/components/public/art";
import { FAQS } from "@/components/public/faq";
import { ProductTile, ShopProductCard } from "@/components/shop/shop-product-card";
import { ShopSearch } from "@/components/shop/shop-search";
import { price } from "@/components/public/product-card";
import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { formatNumber, tr } from "@/lib/i18n";
import { categoryLabel } from "@/lib/category";
import { imageSrc } from "@/lib/surfaces";
import type { PublicStore } from "@/types/public";

const STEPS = [
  ["1", "Choose a product", "Pick a service and your options. You see the price immediately."],
  ["2", "Compare quotes", "Nearby professionals send you their price for the job."],
  ["3", "Pay and relax", "Accept a quote and pay online. We follow the work through to the warranty."],
];
const card = "rounded-[28px] bg-white p-6";
const pillCls = "rounded-full border border-[#ebebeb] bg-white px-4 py-2 text-base";
const pillShadow = { boxShadow: "0 2px 8px rgba(0,0,0,.06)" };

function Heading({ id, children, href, more }: { id?: string; children: React.ReactNode; href?: string; more?: string }) {
  return (
    <div className="mb-6 flex items-end justify-between gap-4">
      <h2 id={id} className="text-xl tracking-[-0.05em]">{children}</h2>
      {href && more && <Link href={href} className="text-sm underline underline-offset-4">{more}</Link>}
    </div>
  );
}

/** A market's front page in the Shop style, in the store's own accent colour. Any industry or market uses the same layout. */
export function ShopStoreHome({ data }: { data: PublicStore }) {
  const { store, industry, industries = [], featured, categories, partners, sections = [], category_counts: counts = {} } = data;
  const products = `/s/${store.slug}/products`;
  const stats = [
    { v: partners.count, l: "Vetted professionals" },
    { v: partners.cities.length, l: "Cities covered" },
    { v: Math.max(0, ...featured.map((p) => p.warranty_years ?? 0)), l: "Years of warranty", suffix: "+" },
  ].filter((s) => s.v > 0);
  const floating = featured.slice(0, 4);
  const faq = { "@context": "https://schema.org", "@type": "FAQPage", mainEntity: FAQS.map((i) => ({ "@type": "Question", name: tr(i.q), acceptedAnswer: { "@type": "Answer", text: tr(i.a) } })) };

  return (
    <ShopShell store={{ ...store, market: data.market }} active="home">
      <header className="grid items-center gap-10 pt-8 md:pt-12 lg:grid-cols-[1fr_1fr] lg:gap-12">
        <div>
          <p className="text-sm font-semibold text-brand-ink">
            {industry ? tr(industry.name) : store.name}{data.market ? ` · ${data.market.code}` : ""}
          </p>
          {industries.length > 1 && (
            <ul className="mt-4 flex flex-wrap gap-2" aria-label={tr("Industries")} data-testid="market-industries">
              {industries.map((i) => (
                <li key={i.key}><Link href={`${products}?industry=${encodeURIComponent(i.key)}`} className="rounded-full bg-white px-4 py-1.5 text-sm text-[#5f5c5b]" style={pillShadow}>{tr(i.name)}</Link></li>
              ))}
            </ul>
          )}
          <h1 className="display mt-3 text-5xl md:text-[64px]">
            {store.headline ?? tr("Quality {service} services, quoted by experts near you", { service: industry ? tr(industry.name) : "" })}
          </h1>
          {store.subheadline && <p className="mt-5 max-w-lg text-lg leading-relaxed text-[#5f5c5b]">{store.subheadline}</p>}
          <div className="mt-8 max-w-xl [&_form]:mx-0 [&_form]:max-w-none"><ShopSearch action={products} /></div>
          {categories.length > 0 && (
            <div className="mt-5 flex flex-wrap gap-2">
              {categories.map((c) => <Link key={c} href={`${products}?category=${encodeURIComponent(c)}`} className={pillCls} style={pillShadow}>{categoryLabel(c)} <span className="text-xs text-[#5f5c5b]">{counts[c] ?? ""}</span></Link>)}
            </div>
          )}
        </div>

        <div className="bg-dark bg-grid-dark relative min-h-[420px] overflow-hidden rounded-[32px] p-6 md:min-h-[500px]" aria-label={store.name}>
          {store.hero_image_url ? (
            <>
              {/* eslint-disable-next-line @next/next/no-img-element -- an arbitrary https photo chosen by the store owner */}
              <img src={imageSrc(store.hero_image_url) ?? ""} alt="" className="absolute inset-0 h-full w-full object-cover" />
              <div className="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent" aria-hidden="true" />
            </>
          ) : (
            <HeroArt kind={industry?.key} className="mx-auto mt-6 max-h-[18rem] w-full opacity-95" />
          )}
          {floating[0] && (
            <Link href={`${products}/${floating[0].id}`} className="shadow-lift absolute inset-x-6 bottom-6 flex items-center gap-4 rounded-2xl bg-white p-4 transition hover:-translate-y-0.5">
              <span className="size-[62px] shrink-0"><ProductTile product={floating[0]} /></span>
              <span className="min-w-0">
                <span className="font-display block truncate text-xl font-semibold text-black">{floating[0].name}</span>
                <span className="block truncate text-sm text-[#5f5c5b]">{floating[0].brand ?? floating[0].seller ?? ""}{floating[0].category ? ` · ${categoryLabel(floating[0].category)}` : ""}</span>
              </span>
              <span className="font-display ms-auto text-end text-xl font-bold leading-none text-black">{price(floating[0])}</span>
            </Link>
          )}
        </div>
      </header>

      {stats.length > 0 && (
        <ul className="mt-8 flex flex-wrap gap-2">
          {stats.map((s) => (
            <li key={s.l} className={pillCls} style={pillShadow}><strong className="font-normal text-brand-ink">{formatNumber(s.v)}{s.suffix ?? ""}</strong> <span className="text-[#5f5c5b]">{tr(s.l)}</span></li>
          ))}
        </ul>
      )}

      {sections.map((sec) => (
        <section key={sec.category} className="mt-16" aria-labelledby={`cat-${sec.category}`}>
          <Heading id={`cat-${sec.category}`} href={`${products}?category=${encodeURIComponent(sec.category)}`} more={sec.count > sec.products.length ? tr("See all") : undefined}>
            {categoryLabel(sec.category)} <span className="text-sm text-[#5f5c5b]">· {sec.count}</span>
          </Heading>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">{sec.products.map((p) => <ShopProductCard key={p.id} product={p} slug={store.slug} />)}</div>
        </section>
      ))}

      <section id="how" className="mt-16 scroll-mt-6" aria-labelledby="how-h">
        <Heading id="how-h">{tr("How it works")}</Heading>
        <ol className="grid gap-3 md:grid-cols-3">
          {STEPS.map(([n, t, d]) => (
            <li key={n} className={card} style={{ boxShadow: SHOP_SHADOW }}>
              <span className="grid size-10 place-items-center rounded-full bg-brand-wash text-brand-ink" aria-hidden="true">{n}</span>
              <p className="mt-4 text-base">{tr(t)}</p>
              <p className="mt-1 text-sm text-[#5f5c5b]">{tr(d)}</p>
            </li>
          ))}
        </ol>
      </section>

      {partners.count > 0 && (
        <section className="mt-16" aria-labelledby="pros">
          <div className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <h2 id="pros" className="text-xl tracking-[-0.05em]">{tr("Professionals near you")}</h2>
            <p className="mt-2 text-[#5f5c5b]">{tr("{n} vetted professionals are ready to quote your job.", { n: formatNumber(partners.count) })}</p>
            {partners.cities.length > 0 && <p className="mt-2 text-sm text-[#5f5c5b]">{tr("Available in")}: {partners.cities.join(" · ")}</p>}
          </div>
        </section>
      )}

      {(store.about || store.contact_email || store.contact_phone) && (
        <section className="mt-16 grid gap-3 md:grid-cols-2" aria-label={tr("About us")}>
          {store.about && <div className={card} style={{ boxShadow: SHOP_SHADOW }}><h2 className="mb-2 text-xl tracking-[-0.05em]">{tr("About us")}</h2><p className="whitespace-pre-line text-[#5f5c5b]">{store.about}</p></div>}
          {(store.contact_email || store.contact_phone) && (
            <div className={card} style={{ boxShadow: SHOP_SHADOW }}>
              <h2 className="mb-2 text-xl tracking-[-0.05em]">{tr("Contact")}</h2>
              {store.contact_email && <p><a className="underline" href={`mailto:${store.contact_email}`}>{store.contact_email}</a></p>}
              {store.contact_phone && <p dir="ltr" className="text-start">{store.contact_phone}</p>}
            </div>
          )}
        </section>
      )}

      <section id="faq" className="mt-16 scroll-mt-6" aria-labelledby="faq-h">
        <Heading id="faq-h">{tr("Frequently asked questions")}</Heading>
        <div className="grid gap-3">
          {FAQS.map((i) => (
            <details key={i.q} className="group rounded-[28px] bg-white px-6 py-4" style={{ boxShadow: SHOP_SHADOW }}>
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-base [&::-webkit-details-marker]:hidden">
                {tr(i.q)}
                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-[#f2f4f5] text-lg leading-none transition group-open:rotate-45" aria-hidden="true">+</span>
              </summary>
              <p className="mt-2 text-sm text-[#5f5c5b]">{tr(i.a)}</p>
            </details>
          ))}
        </div>
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(faq).replace(/</g, "\\u003c") }} />
      </section>

      <section className="bg-dark bg-grid-dark mt-16 rounded-[32px] p-8 text-center text-white md:p-14" aria-labelledby="cta">
        <h2 id="cta" className="display text-3xl !text-white md:text-4xl">{tr("Ready to get started?")}</h2>
        <p className="mx-auto mt-3 max-w-md text-white/70">{tr("Create a free account, choose a service and receive quotes from professionals near you.")}</p>
        <Link href={`/register?store=${encodeURIComponent(store.slug)}`} className="btn-brand mt-6 inline-flex h-11 items-center rounded-full px-6">{tr("Create an account")}</Link>
      </section>
    </ShopShell>
  );
}
