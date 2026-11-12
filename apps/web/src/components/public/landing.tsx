"use client";

import Link from "next/link";
import { useState } from "react";

import { HeroArt, Icon } from "@/components/public/art";
import { price } from "@/components/public/product-card";
import { useSiteList, useSiteText, useSiteValue } from "@/components/public/site-content";
import { formatNumber, tr } from "@/lib/i18n";
import type { PublicShowcase } from "@/types/public";

export const COUNTRY_NAMES: Record<string, string> = { SA: "Saudi Arabia", EG: "Egypt" };
export const INDUSTRY_ICONS: Record<string, string> = { automotive: "🚗", "home-services": "🏠", beauty: "💆", devices: "📱" };

export function Eyebrow({ children, dark }: { children: React.ReactNode; dark?: boolean }) {
  return <p className={`text-xs font-bold uppercase tracking-[0.12em] ${dark ? "text-on-dark-accent" : "text-spruce"}`}>{children}</p>;
}

export function Chip({ children, tone = "linen" }: { children: React.ReactNode; tone?: "linen" | "fog" | "white" }) {
  const bg = tone === "linen" ? "bg-linen" : tone === "fog" ? "bg-fog" : "border border-fog bg-white";
  return <span className={`inline-flex items-center rounded-full px-3.5 py-1.5 text-sm font-medium text-forest ${bg}`}>{children}</span>;
}

export function Check({ children, dark }: { children: React.ReactNode; dark?: boolean }) {
  return (
    <li className="flex items-start gap-2.5 text-sm">
      <span className={`mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full ${dark ? "bg-lime text-forest" : "bg-forest text-lime"}`}>
        <svg viewBox="0 0 16 16" className="h-3 w-3" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7" /></svg>
      </span>
      <span>{children}</span>
    </li>
  );
}

/** Stat figures the page shows come from the live directory, never invented. */
export interface Facts {
  stores: number;
  countries: number;
  industries: number;
  partners: number;
  cities: number;
  products: number;
}

export function factsOf(stores: { market?: string | null }[], showcase: PublicShowcase): Facts {
  const all = showcase.industries.flatMap((i) => i.stores);
  const cities = new Set(all.flatMap((s) => s.partners.map((p) => p.city).filter(Boolean)));

  return {
    stores: stores.length,
    countries: new Set(stores.map((s) => s.market).filter(Boolean)).size,
    industries: showcase.industries.length,
    partners: all.reduce((n, s) => n + s.partners.length, 0),
    cities: cities.size,
    products: all.reduce((n, s) => n + s.products.length, 0),
  };
}

// ------------------------------------------------------------------ hero

export function Hero({ facts, firstStore }: { facts: Facts; firstStore?: { name: string; slug: string; headline: string | null; primary_color: string } }) {
  const badge = useSiteText("home", "badge_text", "Saudi Arabia · Egypt");
  const title = useSiteText("home", "hero_title", "Trusted services, quoted by experts near you");
  const subtitle = useSiteText("home", "hero_subtitle", "A marketplace that connects customers with trusted service providers, across automotive, home services, beauty, devices and more.");
  const cta = useSiteText("home", "primary_cta", "Browse markets");
  const heroImage = useSiteValue("home", "hero_image_url");

  return (
    <section className="overflow-hidden bg-white">
      <div className="mx-auto max-w-[1200px] px-4 pb-6 pt-14 text-center md:pt-20">
        <span className="inline-flex items-center gap-2 rounded-full bg-linen px-4 py-1.5 text-sm font-semibold text-forest">
          <span className="h-2 w-2 rounded-full bg-spruce" aria-hidden="true" />
          {badge}
        </span>
        <h1 className="display display-xl mx-auto mt-6 max-w-5xl uppercase">{title}</h1>
        <p className="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-charcoal">
          {subtitle}
        </p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-5">
          <a href="#stores" className="btn-brand inline-flex items-center gap-2 px-7 py-3.5 text-base">{cta} <span aria-hidden="true">→</span></a>
          <Link href="/login" className="text-base font-semibold text-forest underline underline-offset-4">{tr("For businesses: sign in")}</Link>
        </div>
        <div className="mt-8 flex flex-wrap justify-center gap-2 border-t border-dashed border-fog pt-6">
          {["Paint protection", "Home cleaning", "Salons and spas", "Screen protection", "Ceramic coating", "AC service"].map((c) => <Chip key={c}>{tr(c)}</Chip>)}
        </div>
      </div>

      {/* A layered collage of real numbers and a real store, rather than one flat screenshot. */}
      <div className="mx-auto mt-6 grid max-w-[1100px] items-end gap-4 px-4 pb-14 md:grid-cols-12">
        <div className="bg-dark rounded-[28px] p-7 md:col-span-4 md:-mb-4">
          <p className="text-sm text-white/70">{tr("Open markets")}</p>
          <p className="display text-on-dark-accent mt-1 !text-7xl md:!text-8xl" style={{ color: "var(--on-dark-accent)" }}>{formatNumber(facts.stores)}</p>
          <p className="mt-2 text-sm text-white/80">{tr("{n} countries · {m} industries", { n: facts.countries, m: facts.industries })}</p>
        </div>

        <div className="relative overflow-hidden rounded-[28px] bg-linen md:col-span-5">
          {heroImage ? (
            // eslint-disable-next-line @next/next/no-img-element -- an https image an admin chose
            <img src={heroImage} alt="" className="h-full min-h-56 w-full object-cover" />
          ) : (
            <div className="p-5"><HeroArt kind="automotive" className="w-full" /></div>
          )}
          {firstStore && (
            <Link href={`/s/${firstStore.slug}`} className="absolute bottom-4 start-4 flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-forest shadow-card">
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: firstStore.primary_color }} />
              {firstStore.name}
            </Link>
          )}
        </div>

        <div className="rounded-[28px] border border-fog bg-white p-6 shadow-lift md:col-span-3 md:mb-6">
          <div className="flex items-center gap-3">
            <span className="grid h-11 w-11 place-items-center rounded-full bg-forest font-black text-lime" aria-hidden="true">?</span>
            <div>
              <p className="font-bold text-obsidian">{tr("Need a hand?")}</p>
              <p className="text-xs text-slate">{tr("Answers in seconds")}</p>
            </div>
          </div>
          <a href="#faq" className="mt-4 inline-block rounded-full bg-fog px-4 py-2 text-sm font-semibold text-forest hover:bg-linen">{tr("Read the FAQ")}</a>
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ partners strip

export function PartnerStrip({ showcase, facts }: { showcase: PublicShowcase; facts: Facts }) {
  const names = showcase.industries.flatMap((i) => i.stores.flatMap((s) => s.partners.map((p) => p.name)));
  if (names.length === 0) return null;

  return (
    <section className="bg-white py-10">
      <div className="mx-auto max-w-[1200px] px-4 text-center">
        <p className="text-sm font-medium text-slate">{tr("{n} vetted professionals across {c} cities", { n: facts.partners, c: facts.cities })}</p>
        <div className="mt-4 flex flex-wrap justify-center gap-x-8 gap-y-3">
          {names.map((n) => <span key={n} className="text-lg font-bold tracking-tight text-forest/70">{n}</span>)}
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ industries: tabs + three cards

export function IndustryTabs({ showcase }: { showcase: PublicShowcase }) {
  const [active, setActive] = useState(showcase.industries[0]?.industry.key ?? "");
  const current = showcase.industries.find((i) => i.industry.key === active);
  if (!current) return null;

  const items = current.stores.flatMap((s) => s.products.map((p) => ({ p, store: s }))).slice(0, 3);

  return (
    <section id="stores-offer" className="bg-white py-16">
      <div className="mx-auto max-w-[1200px] px-4 text-center">
        <Eyebrow>{tr("What you can get done")}</Eyebrow>
        <h2 className="display display-md mt-2">{tr("Pick an industry. See what is on offer.")}</h2>
        <p className="mx-auto mt-3 max-w-xl text-charcoal">{tr("Real products from open stores, with prices you can see before you order.")}</p>

        <div role="tablist" aria-label={tr("Industries")} className="mx-auto mt-8 inline-flex flex-wrap justify-center gap-1 rounded-full bg-fog p-1">
          {showcase.industries.map(({ industry }) => (
            <button key={industry.key} role="tab" aria-selected={active === industry.key} type="button" onClick={() => setActive(industry.key)}
              className={`rounded-full px-5 py-2 text-sm font-semibold transition ${active === industry.key ? "bg-lime text-forest" : "text-charcoal hover:bg-white"}`}>
              {tr(industry.name)}
            </button>
          ))}
        </div>

        {items.length === 0 ? (
          <p className="mt-10 text-slate">{tr("No products yet.")}</p>
        ) : (
          <div className="mt-10 grid gap-5 text-start md:grid-cols-3 md:items-center">
            {items.map(({ p, store }, idx) => {
              const hot = items.length === 3 && idx === 1;
              return (
                <article key={`${store.slug}-${p.id}`} className={`hover-lift rounded-[28px] p-7 ${hot ? "bg-dark md:-my-3 md:py-10" : "border border-fog bg-white shadow-card"}`}>
                  <span className={`grid h-12 w-12 place-items-center rounded-2xl text-2xl ${hot ? "bg-white/10" : "bg-linen"}`} aria-hidden="true">{INDUSTRY_ICONS[current.industry.key] ?? "•"}</span>
                  <h3 className={`mt-4 text-xl font-bold ${hot ? "text-white" : "text-obsidian"}`}>{p.name}{p.brand ? ` · ${p.brand}` : ""}</h3>
                  <p className={`mt-1 text-sm ${hot ? "text-white/70" : "text-slate"}`}>{store.name}</p>
                  <p className={`mt-4 text-sm ${hot ? "text-white/70" : "text-slate"}`}>{tr("Starting from")}</p>
                  <p className={`text-3xl font-black tracking-tight ${hot ? "text-on-dark-accent" : "text-forest"}`}>{price(p)}</p>
                  <p className={`mt-4 text-xs font-bold uppercase tracking-wider ${hot ? "text-white/60" : "text-slate"}`}>{tr("Includes")}</p>
                  <ul className={`mt-2 space-y-2 ${hot ? "text-white/90" : "text-charcoal"}`}>
                    <Check dark={hot}>{tr("Quotes from nearby professionals")}</Check>
                    {p.warranty_years ? <Check dark={hot}>{tr("{n}-year warranty", { n: p.warranty_years })}</Check> : <Check dark={hot}>{tr("Secure online payment")}</Check>}
                    <Check dark={hot}>{tr("Order tracking and notifications")}</Check>
                  </ul>
                  <Link href={`/s/${store.slug}/products/${p.id}`} className={`mt-6 block rounded-full py-3 text-center font-semibold ${hot ? "bg-lime text-forest" : "bg-forest text-white hover:bg-spruce"}`}>{tr("View product")}</Link>
                  <Link href={`/s/${store.slug}`} className={`mt-3 block text-center text-sm font-medium underline underline-offset-4 ${hot ? "text-white/80" : "text-forest"}`}>{tr("Visit store")}</Link>
                </article>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ alternating feature rows

export function QuoteFeature() {
  const title = useSiteText("home", "quote_title", "Describe the job once. Get prices from experts nearby.");
  const text = useSiteText("home", "quote_text", "Choose a service and your options. Nearby, vetted professionals send you their price. You compare, accept the one you like and pay online.");
  const cta = useSiteText("home", "quote_cta", "Browse markets");
  return (
    <section className="bg-white py-16">
      <div className="mx-auto grid max-w-[1200px] items-center gap-12 px-4 md:grid-cols-2">
        <div className="relative">
          <div className="bg-dark rounded-[28px] p-6 font-mono text-sm shadow-lift" dir="ltr">
            <p className="text-white/50">{tr("Example request")}</p>
            <p className="mt-2 text-white">&gt; Paint protection film · Hood · Riyadh</p>
            <div className="mt-4 space-y-2">
              {[["Shop One", "SAR 1,450", "2 km"], ["Garage 2", "SAR 1,520", "5 km"], ["Garage 3", "SAR 1,390", "8 km"]].map(([n, p, d]) => (
                <div key={n} className="flex items-center justify-between rounded-2xl bg-white/10 px-4 py-2.5">
                  <span className="text-white">{n} <span className="text-white/50">· {d}</span></span>
                  <span className="font-bold text-on-dark-accent">{p}</span>
                </div>
              ))}
            </div>
            <p className="mt-3 text-xs text-white/50">{tr("Illustration with sample numbers")}</p>
          </div>
          <div className="absolute -bottom-5 end-4 grid h-14 w-14 place-items-center rounded-2xl bg-lime text-forest shadow-lift"><Icon name="tag" className="h-7 w-7" /></div>
        </div>
        <div>
          <Eyebrow>{tr("Compare quotes")}</Eyebrow>
          <h2 className="display display-md mt-2">{title}</h2>
          <p className="mt-4 max-w-lg text-charcoal">{text}</p>
          <a href="#stores" className="btn-brand mt-6 inline-flex items-center gap-2 px-6 py-3">{cta} <span aria-hidden="true">→</span></a>
        </div>
      </div>
    </section>
  );
}

export function DashboardFeature() {
  const title = useSiteText("home", "biz_title", "Run your whole business from one dashboard.");
  const text = useSiteText("home", "biz_text", "Open a branded store, receive quote requests, schedule the work, manage your team and track payouts and shipments.");
  const cta = useSiteText("home", "biz_cta", "For businesses: sign in");
  return (
    <section className="bg-white py-16">
      <div className="mx-auto grid max-w-[1200px] items-center gap-12 px-4 md:grid-cols-2">
        <div className="order-2 md:order-1">
          <Eyebrow>{tr("For businesses")}</Eyebrow>
          <h2 className="display display-md mt-2">{title}</h2>
          <p className="mt-4 max-w-lg text-charcoal">{text}</p>
          <Link href="/login" className="btn-brand mt-6 inline-flex items-center gap-2 px-6 py-3">{cta} <span aria-hidden="true">→</span></Link>
        </div>
        <div className="relative order-1 md:order-2">
          <div className="overflow-hidden rounded-[28px] border border-fog bg-white shadow-lift" dir="ltr">
            <div className="flex items-center gap-1.5 border-b border-fog bg-fog px-4 py-3"><span className="h-2.5 w-2.5 rounded-full bg-pebble/50" /><span className="h-2.5 w-2.5 rounded-full bg-pebble/50" /><span className="h-2.5 w-2.5 rounded-full bg-pebble/50" /></div>
            <div className="grid grid-cols-3 gap-3 p-4">
              {[["Orders", "128"], ["Quotes", "42"], ["Paid", "SAR 38k"]].map(([k, v]) => (
                <div key={k} className="rounded-2xl bg-linen p-3"><p className="text-xs text-slate">{k}</p><p className="text-xl font-black text-forest">{v}</p></div>
              ))}
              <div className="col-span-3 space-y-2">
                {[70, 45, 85].map((w, i) => <div key={i} className="h-3 rounded-full bg-fog"><div className="h-3 rounded-full bg-lime" style={{ width: `${w}%` }} /></div>)}
              </div>
            </div>
          </div>
          <div className="absolute -start-3 -top-4 flex items-center gap-2 rounded-2xl bg-white px-4 py-2.5 text-sm font-semibold text-forest shadow-lift">
            <span className="grid h-7 w-7 place-items-center rounded-full bg-forest text-lime"><Icon name="users" className="h-4 w-4" /></span>{tr("Your team, with roles")}
          </div>
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ three calm columns

export function ValueProps() {
  const items: [string, string, string][] = [
    ["shield", "Warranty on every job", "Completed work is covered by the warranty shown on the product."],
    ["tag", "Clear prices", "See the price before you order. VAT is included where it applies."],
    ["lock", "Secure payment", "Pay on a secure page. We never see or store your card details."],
  ];
  const custom = useSiteList<{ title: string; text: string }>("home", "values");
  const icons = ["shield", "tag", "lock", "users"];
  const shown: [string, string, string][] = custom.length > 0 ? custom.map((c, i) => [icons[i % icons.length], c.title, c.text]) : items;
  return (
    <section className="bg-white py-20">
      <div className="mx-auto grid max-w-[1200px] gap-12 px-4 md:grid-cols-3">
        {shown.map(([icon, title, text]) => (
          <div key={title}>
            <Icon name={icon} className="h-7 w-7 text-forest" />
            <h3 className="mt-5 text-lg font-bold text-obsidian">{custom.length > 0 ? title : tr(title)}</h3>
            <p className="mt-2 text-slate">{custom.length > 0 ? text : tr(text)}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ dark use-cases accordion

export function UseCases({ showcase }: { showcase: PublicShowcase }) {
  const [open, setOpen] = useState(0);
  const title = useSiteText("home", "usecases_title", "One platform. Many industries.");
  const text = useSiteText("home", "usecases_text", "The same quote, payment, shipping and warranty flow works for every kind of service business.");
  if (showcase.industries.length === 0) return null;

  return (
    <section className="bg-white px-4 py-6">
      <div className="bg-dark mx-auto max-w-[1200px] overflow-hidden rounded-[32px] px-6 py-14 md:px-14">
        <div className="grid gap-6 md:grid-cols-2 md:items-end">
          <div>
            <Eyebrow dark>{tr("Use cases")}</Eyebrow>
            <h2 className="display display-lg mt-3 uppercase" style={{ color: "var(--on-dark-accent)" }}>{title}</h2>
          </div>
          <p className="text-white/80 md:text-end">{text}</p>
        </div>

        <ul className="mt-10 divide-y divide-white/15 border-y border-white/15">
          {showcase.industries.map(({ industry, stores }, i) => (
            <li key={industry.key}>
              <button type="button" aria-expanded={open === i} onClick={() => setOpen(open === i ? -1 : i)} className="flex w-full items-center justify-between gap-4 py-5 text-start">
                <span className="text-2xl font-bold text-white md:text-3xl">{tr(industry.name)}</span>
                <span className={`grid h-9 w-9 shrink-0 place-items-center rounded-full border border-white/40 text-xl text-white transition ${open === i ? "rotate-45" : ""}`} aria-hidden="true">+</span>
              </button>
              {open === i && (
                <div className="pb-6">
                  <p className="max-w-2xl text-white/80">{industry.description ? tr(industry.description) : ""}</p>
                  <Link href={stores[0] ? `/s/${stores[0].slug}` : "/#stores"} className="mt-4 inline-block rounded-full bg-lime px-5 py-2.5 text-sm font-semibold text-forest">{tr("Learn more")}</Link>
                </div>
              )}
            </li>
          ))}
        </ul>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ why choose (split)

export function WhySplit() {
  const title = useSiteText("home", "why_title", "Why customers and businesses choose us");
  const cardTitle = useSiteText("home", "why_card_title", "Built for trust, end to end");
  const cardText = useSiteText("home", "why_card_text", "Every step is recorded, from the first quote to the warranty, so both sides know where they stand.");
  const custom = useSiteList<{ text: string }>("home", "why_points");
  const points = custom.length > 0 ? custom.map((c) => c.text) : ["Vetted professionals who quote on every job", "Secure payment released only when confirmed", "Delivery tracking for physical products", "Arabic and English, with local currency and VAT"].map((t) => tr(t));
  return (
    <section className="bg-white py-20">
      <div className="mx-auto max-w-[1200px] px-4">
        <h2 className="display display-md text-center">{title}</h2>
        <div className="mt-10 grid gap-5 md:grid-cols-2">
          <div className="bg-dark rounded-[28px] p-8 md:p-10">
            <h3 className="text-2xl font-bold text-white">{cardTitle}</h3>
            <p className="mt-3 text-white/80">{cardText}</p>
            <ul className="mt-6 space-y-3 text-white/90">
              {points.map((t) => <Check key={t} dark>{t}</Check>)}
            </ul>
            <a href="#faq" className="mt-7 inline-block text-sm font-semibold text-on-dark-accent underline underline-offset-4">{tr("Read the FAQ")}</a>
          </div>
          <div className="overflow-hidden rounded-[28px] bg-linen p-6"><HeroArt kind="devices" className="h-full w-full" /></div>
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ bento

export function Bento({ facts }: { facts: Facts }) {
  const title = useSiteText("home", "bento_title", "Every job is covered, tracked and paid securely.");
  return (
    <section className="bg-white py-10">
      <div className="mx-auto grid max-w-[1200px] gap-4 px-4 md:grid-cols-5 md:grid-rows-2">
        <div className="relative overflow-hidden rounded-[28px] bg-lime p-8 md:col-span-3 md:row-span-2">
          <Eyebrow>{tr("Built to be trusted")}</Eyebrow>
          <h3 className="display display-md mt-2 max-w-md text-forest">{title}</h3>
          <div className="mt-4 max-w-md"><HeroArt kind="home-services" className="w-full" /></div>
        </div>
        <div className="rounded-[28px] border border-fog bg-white p-7 md:col-span-2">
          <svg viewBox="0 0 120 120" className="h-20 w-20" aria-hidden="true">{[56, 40, 24, 8].map((r, i) => <circle key={r} cx="60" cy="60" r={r} fill="none" stroke="#163300" strokeOpacity={0.15 + i * 0.2} strokeWidth="3" />)}<circle cx="60" cy="60" r="4" fill="#163300" /></svg>
          <p className="mt-3 font-semibold text-obsidian">{tr("Quotes come from professionals near you, matched by location.")}</p>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 md:col-span-2">
          {[[facts.stores, "Open markets"], [facts.partners, "Vetted professionals"]].map(([n, l]) => (
            <div key={String(l)} className="bg-dark rounded-[28px] p-6">
              <p className="display text-6xl" style={{ color: "var(--on-dark-accent)" }}>{formatNumber(Number(n))}</p>
              <p className="mt-2 text-sm text-white/80">{tr(String(l))}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ developers

const SNIPPETS = {
  Webhook: `# A carrier tells us a parcel moved. Signed, so only they can.
curl -X POST https://api.your-domain.com/api/webhooks/shipping/http \\
  -H "X-Signature: <hmac-sha256 of the body>" \\
  -d '{"status": "DELIVERED", "tracking": "SM123456"}'`,
  Booking: `{
  "reference": "{order_id}",
  "service": "{service}",
  "recipient": {
    "name": "{recipient_name}",
    "phone": "{phone}",
    "city": "{city}",
    "country": "{country}"
  }
}`,
  Payment: `POST /api/orders/{id}/payments
{ "amount": 1200 }

-> { "status": "redirect",
     "checkout_url": "https://pay.example.com/..." }`,
} as const;

export function DevRow() {
  const [tab, setTab] = useState<keyof typeof SNIPPETS>("Webhook");
  const [copied, setCopied] = useState(false);

  return (
    <section className="bg-white py-16">
      <div className="mx-auto grid max-w-[1200px] items-center gap-12 px-4 md:grid-cols-2">
        <div>
          <Eyebrow>{tr("For developers")}</Eyebrow>
          <h2 className="display display-md mt-2">{tr("Connect any carrier or payment provider.")}</h2>
          <p className="mt-4 max-w-lg text-charcoal">{tr("Describe a provider's HTTP API in the admin panel: where to send the booking, which field holds the tracking number, how its webhooks are signed. No code, no deploy.")}</p>
          <Link href="/login" className="btn-brand mt-6 inline-flex items-center gap-2 px-6 py-3">{tr("Open the admin panel")} <span aria-hidden="true">→</span></Link>
        </div>
        <div className="overflow-hidden rounded-[20px] border border-fog bg-white shadow-lift" dir="ltr">
          <div className="flex items-center justify-between border-b border-fog bg-muted px-3 py-2">
            <div className="flex gap-1">
              {(Object.keys(SNIPPETS) as (keyof typeof SNIPPETS)[]).map((k) => (
                <button key={k} type="button" onClick={() => setTab(k)} className={`rounded-full px-3 py-1 text-xs font-semibold ${tab === k ? "bg-lime text-forest" : "text-slate hover:bg-fog"}`}>{k}</button>
              ))}
            </div>
            <button type="button" onClick={() => { navigator.clipboard?.writeText(SNIPPETS[tab]); setCopied(true); setTimeout(() => setCopied(false), 1500); }} className="rounded-full border border-forest px-3 py-1 text-xs font-semibold text-forest hover:bg-linen">{copied ? tr("Copied") : tr("Copy")}</button>
          </div>
          <pre className="overflow-x-auto p-5 font-mono text-[13px] leading-relaxed text-forest"><code>{SNIPPETS[tab]}</code></pre>
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ compatibility

const BUILT_IN = ["Stripe Checkout", "Manual shipping (any carrier)", "Custom payment gateway (HTTP)", "Custom carrier (HTTP)", "Signed webhooks", "Arabic and English"];
const EXAMPLES = ["Moyasar", "Tap", "Paymob", "Fawry", "SMSA", "Aramex", "Bosta", "DHL"];

export function Compatibility() {
  return (
    <section className="bg-white py-16">
      <div className="mx-auto max-w-[1200px] px-4">
        <div className="grid gap-4 md:grid-cols-2 md:items-end">
          <h2 className="display display-md">{tr("Works with the tools you already use.")}</h2>
          <p className="text-charcoal md:text-end">{tr("Built in today, plus any gateway or carrier with an HTTP API.")}</p>
        </div>
        <p className="mt-8 text-xs font-bold uppercase tracking-wider text-slate">{tr("Built in")}</p>
        <div className="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3">
          {BUILT_IN.map((n) => <div key={n} className="grid min-h-16 place-items-center rounded-2xl border border-fog bg-white px-3 text-center text-sm font-semibold text-forest">{tr(n)}</div>)}
        </div>
        <p className="mt-8 text-xs font-bold uppercase tracking-wider text-slate">{tr("Examples you can connect with the custom driver")}</p>
        <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
          {EXAMPLES.map((n) => <div key={n} className="grid min-h-14 place-items-center rounded-2xl bg-fog px-3 text-sm font-semibold text-forest">{n}</div>)}
        </div>
        <p className="mt-3 text-xs text-slate">{tr("Brand names are examples of providers that offer HTTP APIs; they are not partners of ours.")}</p>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ markets

export function Markets() {
  const markets = [
    { code: "SA", name: "Saudi Arabia", cur: "SAR", vat: 15, lang: "العربية · English" },
    { code: "EG", name: "Egypt", cur: "EGP", vat: 14, lang: "العربية · English" },
  ];
  return (
    <section className="bg-white py-14">
      <div className="mx-auto max-w-[1200px] px-4">
        <h2 className="display display-md text-center">{tr("Local from day one")}</h2>
        <div className="mx-auto mt-8 grid max-w-3xl gap-4 sm:grid-cols-2">
          {markets.map((m) => (
            <div key={m.code} className="rounded-[28px] bg-linen p-7">
              <p className="text-2xl font-black text-forest">{tr(m.name)}</p>
              <dl className="mt-3 space-y-1 text-sm text-forest/80" dir="ltr">
                <div className="flex justify-between"><dt>Currency</dt><dd className="font-semibold">{m.cur}</dd></div>
                <div className="flex justify-between"><dt>VAT</dt><dd className="font-semibold">{m.vat}%</dd></div>
                <div className="flex justify-between"><dt>Languages</dt><dd className="font-semibold">{m.lang}</dd></div>
              </dl>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ------------------------------------------------------------------ closing banner

export function Banner({ title, text, href, label, secondary, image }: { title: string; text: string; href: string; label: string; secondary?: { href: string; label: string }; image?: string | null }) {
  return (
    <section className="bg-white px-4 pb-16 pt-6">
      <div className="bg-dark relative mx-auto max-w-[1200px] overflow-hidden rounded-[32px] px-6 py-16 text-center md:px-14">
        {image && (
          // eslint-disable-next-line @next/next/no-img-element -- an https image an admin chose; a dark veil keeps the text readable
          <><img src={image} alt="" className="absolute inset-0 h-full w-full object-cover" /><div className="absolute inset-0 bg-[var(--dark)]/70" aria-hidden="true" /></>
        )}
        <div className="absolute -end-10 -top-10 h-40 w-40 rounded-full bg-lime/20" aria-hidden="true" />
        <div className="absolute -bottom-12 start-6 h-32 w-32 rounded-full bg-lime/10" aria-hidden="true" />
        <h2 className="display display-lg relative mx-auto max-w-3xl uppercase" style={{ color: "var(--on-dark-accent)" }}>{tr(title)}</h2>
        <p className="relative mx-auto mt-4 max-w-xl text-white/85">{tr(text)}</p>
        <div className="relative mt-8 flex flex-wrap items-center justify-center gap-4">
          <a href={href} className="btn-brand px-7 py-3.5">{tr(label)}</a>
          {secondary && <Link href={secondary.href} className="rounded-full border border-white/50 px-7 py-3.5 font-semibold text-white hover:bg-white/10">{tr(secondary.label)}</Link>}
        </div>
      </div>
    </section>
  );
}
