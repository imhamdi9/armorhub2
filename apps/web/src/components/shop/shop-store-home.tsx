"use client";

import Link from "next/link";

import { HeroArt } from "@/components/public/art";
import { FAQS } from "@/components/public/faq";
import { ProductTile, ShopProductCard } from "@/components/shop/shop-product-card";
import { ShopSearch } from "@/components/shop/shop-search";
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
      {href && <Link href={href} className="text-sm underline underline-offset-4">{more}</Link>}
    </div>
  );
}

/** A market's front page in the Shop style, in the store's own accent colour. Any industry or market uses the same layout. */
export function ShopStoreHome({ data }: { data: PublicStore }) {
  const { store, industry, featured, categories, partners, sections = [], category_counts: counts = {} } = data;
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
      <header className="overflow-x-clip pt-10 text-center md:pt-14">
        {floating.length > 0 && (
          <div className="relative mx-auto h-44 max-w-xl" aria-hidden="true">
            {floating.map((p, i) => (
              <div key={p.id} className="absolute w-[20%] min-w-24 rounded-[28px] bg-white p-1.5" style={{ insetInlineStart: `${[4, 28, 52, 74][i]}%`, top: [18, 0, 24, 6][i], rotate: `${[-6, 3, -3, 6][i]}deg`, boxShadow: SHOP_SHADOW }}>
                <div className="aspect-square"><ProductTile product={p} /></div>
                <p className="truncate px-1.5 pb-1.5 pt-1.5 text-start text-xs">{p.seller ?? p.name}</p>
              </div>
            ))}
          </div>
        )}
        {industry && <p className="mt-6 text-sm text-[#787574]">{tr(industry.name)}</p>}
        <h1 className="mt-2 text-5xl leading-none tracking-[-0.06em] text-brand-ink md:text-[64px]">{store.name}</h1>
        <p className="mx-auto mt-4 max-w-2xl text-base text-[#787574]">
          {store.subheadline ?? store.headline ?? tr("Quality {service} services, quoted by experts near you", { service: industry ? tr(industry.name) : "" })}
        </p>
        <div className="mt-8"><ShopSearch action={products} /></div>
        {categories.length > 0 && (
          <div className="mt-6 flex flex-wrap justify-center gap-2">
            {categories.map((c) => <Link key={c} href={`${products}?category=${encodeURIComponent(c)}`} className={pillCls} style={pillShadow}>{categoryLabel(c)} <span className="text-xs text-[#787574]">{counts[c] ?? ""}</span></Link>)}
          </div>
        )}
      </header>

      <section className="mt-12" aria-label={store.name}>
        {store.hero_image_url ? (
          <div className="relative overflow-hidden rounded-[28px]">
            {/* eslint-disable-next-line @next/next/no-img-element -- an arbitrary https photo chosen by the store owner */}
            <img src={imageSrc(store.hero_image_url) ?? ""} alt="" className="aspect-[16/7] w-full object-cover" />
            <div className="absolute inset-0 bg-black/25" aria-hidden="true" />
            <p className="absolute start-6 top-6 text-3xl text-white tracking-[-0.05em]">{store.name}</p>
          </div>
        ) : (
          <div className="rounded-[28px] bg-brand-wash p-6"><HeroArt kind={industry?.key} className="mx-auto max-h-[22rem] w-full" /></div>
        )}
      </section>

      {stats.length > 0 && (
        <ul className="mt-6 flex flex-wrap justify-center gap-2">
          {stats.map((s) => (
            <li key={s.l} className={pillCls} style={pillShadow}><strong className="font-normal text-brand-ink">{formatNumber(s.v)}{s.suffix ?? ""}</strong> <span className="text-[#787574]">{tr(s.l)}</span></li>
          ))}
        </ul>
      )}

      {sections.map((sec) => (
        <section key={sec.category} className="mt-16" aria-labelledby={`cat-${sec.category}`}>
          <Heading id={`cat-${sec.category}`} href={`${products}?category=${encodeURIComponent(sec.category)}`} more={sec.count > sec.products.length ? tr("See all") : undefined}>
            {categoryLabel(sec.category)} <span className="text-sm text-[#787574]">· {sec.count}</span>
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
              <p className="mt-1 text-sm text-[#787574]">{tr(d)}</p>
            </li>
          ))}
        </ol>
      </section>

      {partners.count > 0 && (
        <section className="mt-16" aria-labelledby="pros">
          <div className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <h2 id="pros" className="text-xl tracking-[-0.05em]">{tr("Professionals near you")}</h2>
            <p className="mt-2 text-[#787574]">{tr("{n} vetted professionals are ready to quote your job.", { n: formatNumber(partners.count) })}</p>
            {partners.cities.length > 0 && <p className="mt-2 text-sm text-[#787574]">{tr("Available in")}: {partners.cities.join(" · ")}</p>}
          </div>
        </section>
      )}

      {(store.about || store.contact_email || store.contact_phone) && (
        <section className="mt-16 grid gap-3 md:grid-cols-2" aria-label={tr("About us")}>
          {store.about && <div className={card} style={{ boxShadow: SHOP_SHADOW }}><h2 className="mb-2 text-xl tracking-[-0.05em]">{tr("About us")}</h2><p className="whitespace-pre-line text-[#787574]">{store.about}</p></div>}
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
              <p className="mt-2 text-sm text-[#787574]">{tr(i.a)}</p>
            </details>
          ))}
        </div>
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(faq).replace(/</g, "\\u003c") }} />
      </section>

      <section className="mt-16 rounded-[28px] bg-black p-8 text-center text-white md:p-12" aria-labelledby="cta">
        <h2 id="cta" className="text-3xl tracking-[-0.05em]">{tr("Ready to get started?")}</h2>
        <p className="mx-auto mt-3 max-w-md text-white/70">{tr("Create a free account, choose a service and receive quotes from professionals near you.")}</p>
        <Link href={`/register?store=${encodeURIComponent(store.slug)}`} className="btn-brand mt-6 inline-flex h-11 items-center rounded-full px-6">{tr("Create an account")}</Link>
      </section>
    </ShopShell>
  );
}
