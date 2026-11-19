"use client";

import Link from "next/link";

import { FAQS } from "@/components/public/faq";
import { Stars } from "@/components/shop/stars";
import { ForYou, RecentlyViewed } from "@/components/shop/memory";
import { ProductTile } from "@/components/shop/shop-product-card";
import { savingPercent } from "@/components/public/product-card";
import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { categoryLabel } from "@/lib/category";
import { formatMoney, formatNumber, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import type { PublicProduct, PublicStore } from "@/types/public";

const STEPS = [
  ["1", "Find what you need", "Search or browse the departments. You see the price straight away."],
  ["2", "Add to cart or ask for a quote", "Buy ready goods at once; for services, nearby professionals send you their price."],
  ["3", "Pay and track", "Pay online and follow your order to the door, with a warranty where it applies."],
];
const card = "rounded-2xl border border-border bg-white p-5";

/** A row of products that scrolls sideways, with a link to the whole list. */
function Rail({ title, href, more, products, slug, id }: { title: string; href?: string; more?: string; products: PublicProduct[]; slug: string; id: string }) {
  if (products.length === 0) return null;

  return (
    <section className={`${card} mt-4`} aria-labelledby={id} data-testid="rail">
      <div className="mb-3 flex items-baseline justify-between gap-4">
        <h2 id={id} className="text-xl font-bold">{title}</h2>
        {href && more && <Link href={href} className="text-sm font-semibold text-[var(--brand-ink)] hover:underline">{more}</Link>}
      </div>
      <ul className="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2">
        {products.map((p) => {
          const saving = savingPercent(p.from_price, p.compare_at_price);
          return (
            <li key={p.id} className="w-[170px] shrink-0 snap-start sm:w-[190px]">
              <Link href={`/s/${slug}/products/${p.id}`} className="group block">
                <div className="relative aspect-square"><ProductTile product={p} />{saving && <span className="absolute start-2 top-2 rounded bg-[#a32d2d] px-1.5 py-0.5 text-xs font-bold text-white">−{saving}%</span>}</div>
                <p className="mt-2 line-clamp-2 text-sm leading-snug group-hover:text-[var(--brand-ink)]">{p.name}</p>
                <div className="mt-0.5"><Stars average={p.rating?.average} count={p.rating?.count} /></div>
                <p className="mt-0.5 flex flex-wrap items-baseline gap-x-2"><strong className="text-base">{formatMoney(p.from_price, p.currency, true)}</strong>{p.compare_at_price && p.compare_at_price > p.from_price && <s className="text-xs text-slate">{formatMoney(p.compare_at_price, p.currency, true)}</s>}</p>
              </Link>
            </li>
          );
        })}
      </ul>
    </section>
  );
}

/** A store's front page as a shop window: a banner, department cards with real photos, then rows of products. Any industry or market uses the same layout. */
export function ShopStoreHome({ data, deals = [] }: { data: PublicStore; deals?: PublicProduct[] }) {
  const { store, industry, category_labels: labels = {}, partners, sections = [], category_tree: tree = [], featured } = data;
  const products = `/s/${store.slug}/products`;
  const name = (key: string) => labels[key] ?? categoryLabel(key);
  const faq = { "@context": "https://schema.org", "@type": "FAQPage", mainEntity: FAQS.map((i) => ({ "@type": "Question", name: tr(i.q), acceptedAnswer: { "@type": "Answer", text: tr(i.a) } })) };
  // The first product of each category stands for it in the department cards.
  const sample = new Map(sections.map((s) => [s.category, s.products[0]]));
  const departments = tree.slice(0, 3);
  const topRated = [...sections.flatMap((s) => s.products)].filter((p) => (p.rating?.count ?? 0) > 0).sort((a, b) => (b.rating?.average ?? 0) - (a.rating?.average ?? 0)).slice(0, 10);
  const railSections = [...sections].sort((a, b) => b.count - a.count).slice(0, 6);

  return (
    <ShopShell store={{ ...store, market: data.market }} active="home">
      {/* Banner: the store's own headline, sub-headline and picture (all editable), with the way into the shop. */}
      <section className="relative mt-4 overflow-hidden rounded-2xl bg-brand-wash" data-testid="home-banner" aria-label={store.name}>
        {store.hero_image_url && (
          <>
            {/* eslint-disable-next-line @next/next/no-img-element -- an arbitrary https photo chosen by the store owner */}
            <img src={imageSrc(store.hero_image_url) ?? ""} alt="" className="absolute inset-0 h-full w-full object-cover" />
            <div className="absolute inset-0 bg-gradient-to-l from-black/60 via-black/25 to-transparent rtl:bg-gradient-to-r" aria-hidden="true" />
          </>
        )}
        <div className={`relative flex min-h-[220px] flex-col justify-center gap-3 px-6 py-8 sm:min-h-[300px] sm:px-12 ${store.hero_image_url ? "text-white" : "text-obsidian"}`}>
          <p className="text-sm font-semibold opacity-90">{industry ? tr(industry.name) : store.name}{data.market ? ` · ${data.market.code}` : ""}</p>
          <h1 className="max-w-2xl text-3xl font-bold leading-tight tracking-tight sm:text-5xl">{store.headline ?? tr("Everything you need, from trusted sellers")}</h1>
          {store.subheadline && <p className="max-w-xl text-base opacity-90 sm:text-lg">{store.subheadline}</p>}
          <div><Link href={products} className="btn-brand inline-flex h-11 items-center rounded-full px-7 text-base font-bold" data-testid="shop-now">{tr("Shop now")}</Link></div>
        </div>
      </section>

      {/* Department cards: a few categories each, with a real product photo, sitting over the banner like a shelf. */}
      {(departments.length > 0 || deals.length > 0) && (
        <div className="relative z-10 mx-3 -mt-10 grid gap-3 sm:grid-cols-2 lg:mx-6 lg:grid-cols-4" data-testid="department-cards">
          {departments.map((d) => {
            const kids = (d.children.length > 0 ? d.children : [{ key: d.key, name: d.name, count: d.count }]).slice(0, 4);
            return (
              <div key={d.key} className="rounded-2xl border border-border bg-white p-4" style={{ boxShadow: SHOP_SHADOW }}>
                <h2 className="mb-3 text-lg font-bold">{d.name}</h2>
                <div className="grid grid-cols-2 gap-2">
                  {kids.map((c) => (
                    <Link key={c.key} href={`${products}?category=${encodeURIComponent(c.key)}`} className="group block">
                      <div className="aspect-square">{sample.get(c.key) ? <ProductTile product={sample.get(c.key)!} /> : <div className="size-full rounded-[20px] bg-brand-wash" aria-hidden="true" />}</div>
                      <span className="mt-1 line-clamp-1 block text-xs group-hover:text-[var(--brand-ink)]">{c.name}</span>
                    </Link>
                  ))}
                </div>
                <Link href={`${products}?category=${encodeURIComponent(d.key)}`} className="mt-3 inline-block text-sm font-semibold text-[var(--brand-ink)] hover:underline">{tr("Shop")} {d.name}</Link>
              </div>
            );
          })}
          {deals.length > 0 && (
            <div className="rounded-2xl border border-border bg-white p-4" style={{ boxShadow: SHOP_SHADOW }}>
              <h2 className="mb-3 text-lg font-bold">{tr("Deals and discounts")}</h2>
              <div className="grid grid-cols-2 gap-2">
                {deals.slice(0, 4).map((p) => <Link key={p.id} href={`/s/${store.slug}/products/${p.id}`} className="block aspect-square" aria-label={p.name}><ProductTile product={p} /></Link>)}
              </div>
              <Link href={`${products}?deals=1`} className="mt-3 inline-block text-sm font-semibold text-[var(--brand-ink)] hover:underline">{tr("See all deals")}</Link>
            </div>
          )}
        </div>
      )}

      <Rail id="rail-deals" title={tr("Today's deals")} href={`${products}?deals=1`} more={tr("See all deals")} products={deals} slug={store.slug} />
      <Rail id="rail-rated" title={tr("Top rated")} href={`${products}?sort=rating`} more={tr("See more")} products={topRated} slug={store.slug} />
      {featured.length > 0 && deals.length === 0 && topRated.length === 0 && <Rail id="rail-featured" title={tr("Featured")} href={products} more={tr("See more")} products={featured} slug={store.slug} />}
      {railSections.map((sec) => (
        <Rail key={sec.category} id={`cat-${sec.category}`} title={`${name(sec.category)}`} href={`${products}?category=${encodeURIComponent(sec.category)}`} more={sec.count > sec.products.length ? tr("See all") : undefined} products={sec.products} slug={store.slug} />
      ))}

      <section id="how" className="mt-10 scroll-mt-6" aria-labelledby="how-h">
        <h2 id="how-h" className="mb-3 text-xl font-bold">{tr("How it works")}</h2>
        <ol className="grid gap-3 md:grid-cols-3">
          {STEPS.map(([n, t, d]) => (
            <li key={n} className={card}>
              <span className="grid size-9 place-items-center rounded-full bg-[var(--brand)] font-bold text-[var(--on-brand)]" aria-hidden="true">{n}</span>
              <p className="mt-3 text-base font-semibold">{tr(t)}</p>
              <p className="mt-1 text-sm text-slate">{tr(d)}</p>
            </li>
          ))}
        </ol>
      </section>

      {partners.count > 0 && (
        <section className={`${card} mt-4`} aria-labelledby="pros">
          <h2 id="pros" className="text-xl font-bold">{tr("Professionals near you")}</h2>
          <p className="mt-1 text-slate">{tr("{n} vetted professionals are ready to quote your job.", { n: formatNumber(partners.count) })}</p>
          {partners.cities.length > 0 && <p className="mt-1 text-sm text-slate">{tr("Available in")}: {partners.cities.map((c) => tr(c)).join(" · ")}</p>}
        </section>
      )}

      {(store.about || store.contact_email || store.contact_phone) && (
        <section className="mt-4 grid gap-3 md:grid-cols-2" aria-label={tr("About us")}>
          {store.about && <div className={card}><h2 className="mb-2 text-xl font-bold">{tr("About us")}</h2><p className="whitespace-pre-line text-slate">{store.about}</p></div>}
          {(store.contact_email || store.contact_phone) && (
            <div className={card}>
              <h2 className="mb-2 text-xl font-bold">{tr("Contact")}</h2>
              {store.contact_email && <p><a className="underline" href={`mailto:${store.contact_email}`}>{store.contact_email}</a></p>}
              {store.contact_phone && <p dir="ltr" className="text-start">{store.contact_phone}</p>}
            </div>
          )}
        </section>
      )}

      <section id="faq" className="mt-10 scroll-mt-6" aria-labelledby="faq-h">
        <h2 id="faq-h" className="mb-3 text-xl font-bold">{tr("Frequently asked questions")}</h2>
        <div className="grid gap-2">
          {FAQS.map((i) => (
            <details key={i.q} className="group rounded-2xl border border-border bg-white px-5 py-3">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-4 text-base [&::-webkit-details-marker]:hidden">
                {tr(i.q)}
                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-fog text-lg leading-none transition group-open:rotate-45" aria-hidden="true">+</span>
              </summary>
              <p className="mt-2 text-sm text-slate">{tr(i.a)}</p>
            </details>
          ))}
        </div>
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(faq).replace(/</g, "\\u003c") }} />
      </section>

      <RecentlyViewed slug={store.slug} />
      <ForYou slug={store.slug} />

      <section className="mt-10 rounded-2xl bg-[var(--brand)] p-8 text-center text-[var(--on-brand)] md:p-12" aria-labelledby="cta">
        <h2 id="cta" className="text-2xl font-bold md:text-3xl">{tr("Ready to get started?")}</h2>
        <p className="mx-auto mt-2 max-w-md opacity-90">{tr("Create a free account to track your orders, save favourites and get offers.")}</p>
        <Link href={`/register?store=${encodeURIComponent(store.slug)}`} className="mt-5 inline-flex h-11 items-center rounded-full bg-obsidian px-7 text-base font-bold text-white">{tr("Create an account")}</Link>
      </section>
    </ShopShell>
  );
}
