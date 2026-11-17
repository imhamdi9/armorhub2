"use client";

import Link from "next/link";
import { useState } from "react";

import { price } from "@/components/public/product-card";
import type { ProductModel } from "@/components/public/product-page/model";
import {
  Accordion, BuyButtons, Crumbs, DetailsAccordions, Fulfilled, Gallery, Heading, Highlights, MiniProduct, OptionPills, PriceLine, Section, StickyBar, Trust, ctaLabel,
} from "@/components/public/product-page/parts";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { SHOP_SHADOW } from "@/components/shop/shop-shell";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";

const card = "rounded-[24px] bg-white p-5";
const Tag = ({ children, tone = "up" }: { children: React.ReactNode; tone?: "up" | "down" | "best" }) => (
  <span className={`rounded-md px-2 py-0.5 text-[11px] font-extrabold tracking-wide ${tone === "up" ? "bg-emerald-50 text-emerald-700" : tone === "down" ? "bg-amber-50 text-amber-800" : "bg-[var(--brand)] text-[var(--on-brand)]"}`}>{children}</span>
);

/** Related products as a grid of full cards, for the end of a page. */
function MoreProducts({ m, title = "You may also like" }: { m: ProductModel; title?: string }) {
  if (m.others.length === 0) return null;

  return (
    <Section title={title}>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{m.others.slice(0, 4).map((r) => <ShopProductCard key={r.id} product={r} slug={m.store.slug} />)}</div>
    </Section>
  );
}

/** The next cheaper and pricier product, as two clear choices. */
function OtherTiers({ m }: { m: ProductModel }) {
  const down = m.cheaper[0];
  const up = m.pricier[0];
  if (!down && !up) return null;

  return (
    <div className="grid gap-3 sm:grid-cols-2" data-testid="other-tiers">
      {down && <MiniProduct product={down} slug={m.store.slug} tag="Lower price" />}
      {up && <MiniProduct product={up} slug={m.store.slug} tag="Step up" />}
    </div>
  );
}

// ---------------------------------------------------------------- 1 classic

export function ClassicLayout({ m }: { m: ProductModel }) {
  const { p } = m;
  const together = m.pairs.length > 0 ? m.pairs.slice(0, 3) : m.others.slice(0, 3);

  return (
    <>
      <div className="grid gap-10 lg:grid-cols-2">
        <Gallery images={p.images ?? []} name={p.name} />
        <div className="space-y-5">
          <div><Heading m={m} /></div>
          <div><PriceLine m={m} /></div>
          {m.lead && <p className="text-charcoal">{m.lead}</p>}
          <Highlights items={p.highlights} />
          <OptionPills m={m} />
          <Fulfilled m={m} />
          <BuyButtons m={m} />
          <Trust />
          {together.length > 0 && (
            <div className="rounded-2xl border border-fog bg-brand-wash p-4" data-testid="bought-together">
              <div className="mb-3 flex items-center justify-between"><b className="text-obsidian">{tr("Pairs well with this")}</b><Tag>{tr("Upsell")}</Tag></div>
              <div className="grid gap-2">{together.map((r) => <MiniProduct key={r.id} product={r} slug={m.store.slug} />)}</div>
            </div>
          )}
          <DetailsAccordions m={m} />
        </div>
      </div>
      <MoreProducts m={m} />
    </>
  );
}

// ---------------------------------------------------------------- 2 tiers

export function TiersLayout({ m }: { m: ProductModel }) {
  const { p } = m;
  const tiers = [...m.options].sort((a, b) => a.price - b.price).slice(0, 3);
  if (tiers.length < 2) return <ClassicLayout m={m} />;

  const mid = tiers.length === 3 ? 1 : 1;

  return (
    <>
      <Crumbs m={m} />
      <div className="mx-auto max-w-3xl text-center">
        {p.category && <span className="text-xs font-bold uppercase tracking-wider text-brand-ink">{p.category.replace(/-/g, " ")}</span>}
        <h1 className="display display-lg mt-1">{p.name}</h1>
        {m.lead && <p className="mt-3 text-lg text-slate">{m.lead}</p>}
      </div>
      <div className={`mt-10 grid items-end gap-5 ${tiers.length === 3 ? "lg:grid-cols-3" : "lg:grid-cols-2"}`} data-testid="tiers">
        {tiers.map((t, i) => {
          const best = i === mid;
          return (
            <div key={`${t.target_type}:${t.target}`} className={`relative flex flex-col rounded-[24px] bg-white p-6 ${best ? "border-2 border-[var(--brand)] lg:py-9" : "border border-fog"}`} style={{ boxShadow: best ? "0 18px 40px -16px color-mix(in srgb, var(--brand) 45%, transparent)" : SHOP_SHADOW }}>
              <span className="absolute -top-3 start-5">{i === 0 ? <Tag tone="down">{tr("Lowest price")}</Tag> : best ? <Tag tone="best">{tr("Best value")}</Tag> : <Tag>{tr("Most complete")}</Tag>}</span>
              <b className="text-xl text-obsidian">{m.page.tiers?.[i]?.name ?? t.target}</b>
              {m.page.tiers?.[i]?.text && <span className="mt-1 text-sm text-slate">{m.page.tiers[i].text}</span>}
              <span className="mt-3 text-3xl font-extrabold tracking-tight">{formatMoney(t.price, p.currency, true)}</span>
              <ul className="my-5 space-y-2">
                {(p.highlights ?? []).slice(0, i + 2).map((h) => <li key={h} className="flex gap-2 text-sm"><span aria-hidden="true">✓</span>{h}</li>)}
                {p.warranty_years ? <li className="flex gap-2 text-sm"><span aria-hidden="true">✓</span>{tr("{n}-year warranty", { n: p.warranty_years })}</li> : null}
              </ul>
              <Link href={m.href} className={`mt-auto inline-flex items-center justify-center rounded-full px-6 py-3 font-semibold ${best ? "btn-brand" : "border-2 border-obsidian text-obsidian hover:bg-fog"}`}>{ctaLabel(m)}</Link>
            </div>
          );
        })}
      </div>
      <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_1fr]"><Gallery images={p.images ?? []} name={p.name} sticky={false} square={false} /><div><DetailsAccordions m={m} /></div></div>
      <MoreProducts m={m} />
    </>
  );
}

// ---------------------------------------------------------------- 3 builder

export function BuilderLayout({ m }: { m: ProductModel }) {
  const { p } = m;
  const [keys, setKeys] = useState<string[]>([]);
  if (m.goods || m.options.length === 0) return <ClassicLayout m={m} />;

  const id = (o: { target_type: string; target: string }) => `${o.target_type}:${o.target}`;
  const on = keys;
  const setOn = (next: string[]) => { setKeys(next); m.setParts(m.options.filter((o) => next.includes(id(o)) && o.target_id !== undefined).map((o) => o.target_id as number)); };
  const total = m.options.filter((o) => on.includes(id(o))).reduce((n, o) => n + o.price, 0);
  const cheapest = [...m.options].sort((a, b) => a.price - b.price)[0];
  const rest = m.options.filter((o) => !on.includes(id(o)));

  return (
    <>
      <Crumbs m={m} />
      <div className="grid gap-8 lg:grid-cols-[1fr_380px]">
        <div>
          <h1 className="display display-md">{tr("Build your protection")}</h1>
          <p className="mt-1 text-slate">{p.name}. {tr("Tap what you want. The estimate updates as you choose.")}</p>
          <div className="mt-5"><Gallery images={p.images ?? []} name={p.name} sticky={false} square={false} /></div>
          <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="parts">
            {m.options.map((o) => {
              const k = id(o);
              const sel = on.includes(k);
              return (
                <button key={k} type="button" aria-pressed={sel} onClick={() => setOn(sel ? on.filter((x) => x !== k) : [...on, k])}
                  className={`flex items-center gap-3 rounded-2xl border-2 p-3 text-start transition ${sel ? "border-[var(--brand)] bg-brand-wash" : "border-fog bg-white hover:border-obsidian/30"}`}>
                  <span className={`grid size-6 place-items-center rounded-md border-2 text-xs ${sel ? "border-[var(--brand)] bg-[var(--brand)] text-[var(--on-brand)]" : "border-fog"}`} aria-hidden="true">{sel ? "✓" : ""}</span>
                  <span><b className="block text-sm text-obsidian">{o.target}</b><span className="text-xs text-slate">{formatMoney(o.price, p.currency, true)}</span></span>
                </button>
              );
            })}
          </div>
        </div>
        <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
          <div className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <b className="text-lg text-obsidian">{tr("Your selection")}</b>
            <ul className="my-3 space-y-2 text-sm">
              {on.length === 0 && <li className="text-slate">{tr("Nothing chosen yet.")}</li>}
              {m.options.filter((o) => on.includes(id(o))).map((o) => <li key={id(o)} className="flex justify-between"><span>{o.target}</span><b>{formatMoney(o.price, p.currency, true)}</b></li>)}
            </ul>
            <div className="flex items-baseline justify-between border-t border-fog pt-3"><span className="text-slate">{tr("Estimated total")}</span><span className="text-3xl font-extrabold tracking-tight">{formatMoney(total, p.currency, true)}</span></div>
            <div className="mt-4"><BuyButtons m={m} /></div>
            <p className="mt-2 text-center text-xs text-slate">{tr("Estimated. Partners quote the final price.")}</p>
          </div>
          {on.length > 0 && rest[0] && (
            <div className="rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50/50 p-4">
              <div className="flex items-center justify-between"><b className="text-obsidian">{tr("Add {name}", { name: rest[0].target })}</b><Tag>{tr("Upsell")}</Tag></div>
              <button type="button" onClick={() => setOn([...on, id(rest[0])])} className="mt-2 rounded-full border-2 border-obsidian px-4 py-1.5 text-sm font-semibold">+ {formatMoney(rest[0].price, p.currency, true)}</button>
            </div>
          )}
          {cheapest && m.options.length > 1 && (
            <button type="button" onClick={() => setOn([id(cheapest)])} className="w-full rounded-2xl border border-fog bg-white p-4 text-start">
              <span className="flex items-center justify-between"><b className="text-obsidian">{tr("Just {name}", { name: cheapest.target })}</b><Tag tone="down">{tr("Lowest price")}</Tag></span>
              <span className="text-sm text-slate">{tr("From {price}", { price: formatMoney(cheapest.price, p.currency, true) })}</span>
            </button>
          )}
        </aside>
      </div>
      <MoreProducts m={m} />
    </>
  );
}

// ---------------------------------------------------------------- 4 story

export function StoryLayout({ m }: { m: ProductModel }) {
  const { p } = m;
  const img = p.images?.[0] ? imageSrc(p.images[0]) : null;
  const feats = (p.highlights ?? []).slice(0, 3);
  const own = (m.page.steps ?? []).map((x) => x.title);
  const steps = own.length > 0 ? own : !m.goods ? ["Choose what you need", "Compare quotes", "Pay in the app", "Get it done"] : ["Choose your product", "Pay securely", "Track delivery", "Enjoy it"];

  return (
    <>
      <div className="bg-dark bg-grid-dark -mx-4 overflow-hidden rounded-[28px] px-6 pt-10 md:-mx-8 md:px-12">
        <div className="grid items-center gap-8 lg:grid-cols-2">
          <div className="pb-10">
            {p.category && <span className="rounded-full bg-white/15 px-3 py-1 text-sm">{p.category.replace(/-/g, " ")}</span>}
            <h1 className="display display-lg mt-4 !text-white">{p.name}</h1>
            {m.lead && <p className="mt-4 max-w-md text-lg text-white/80">{m.lead}</p>}
            <div className="mt-6 flex flex-wrap items-center gap-4">
              <Link href={m.href} className="btn-brand inline-flex items-center px-7 py-3.5 text-base">{ctaLabel(m)}</Link>
              <span className="text-lg font-bold text-white">{!m.goods && m.options.length > 0 ? `${tr("From")} ` : ""}{price({ from_price: m.shown, currency: p.currency })}</span>
            </div>
          </div>
          <div className="self-end">
            {img
              // eslint-disable-next-line @next/next/no-img-element -- seller-chosen image
              ? <img src={img} alt={p.name} className="mx-auto max-h-[22rem] w-full rounded-t-[24px] object-cover" />
              : <div className="mx-auto grid h-64 w-full max-w-md place-items-center rounded-t-[24px] bg-white/10 text-8xl font-bold text-white/30" aria-hidden="true">{p.name.slice(0, 1)}</div>}
          </div>
        </div>
      </div>
      <div className="mx-auto mt-10 grid max-w-4xl gap-4 md:grid-cols-3">
        {(feats.length > 0 ? feats : [p.warranty_years ? tr("{n}-year warranty", { n: p.warranty_years }) : tr("Secure payment"), tr("Vetted sellers and partners"), tr("Free cancellation before you pay")]).map((f) => (
          <div key={f} className={card} style={{ boxShadow: SHOP_SHADOW }}><span className="mb-3 grid size-10 place-items-center rounded-xl bg-brand-wash text-brand-ink" aria-hidden="true">✓</span><b className="text-obsidian">{f}</b></div>
        ))}
      </div>
      <ol className="mx-auto mt-8 grid max-w-4xl gap-3 md:grid-cols-4">
        {steps.map((s, i) => <li key={s} className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-full bg-[var(--brand)] font-bold text-[var(--on-brand)]">{i + 1}</span><b className="text-sm text-obsidian">{tr(s)}</b></li>)}
      </ol>
      <div className="mx-auto mt-10 max-w-3xl"><OptionPills m={m} /><div className="mt-4"><Fulfilled m={m} /></div><DetailsAccordions m={m} /></div>
      <div className="mt-10"><OtherTiers m={m} /></div>
      <MoreProducts m={m} />
      <div className="h-20" />
      <StickyBar m={m} always />
    </>
  );
}

// ---------------------------------------------------------------- 5 compare

export function CompareLayout({ m }: { m: ProductModel }) {
  const { p, store } = m;
  const down = m.cheaper[0];
  const up = m.pricier[0];
  if (!down && !up) return <ClassicLayout m={m} />;

  const cols = [down && { product: down, tag: "Lower price" as const }, { product: p, tag: null }, up && { product: up, tag: "Step up" as const }].filter(Boolean) as { product: typeof p; tag: "Lower price" | "Step up" | null }[];
  const rows: [string, (x: typeof p) => string][] = [
    ["Price", (x) => `${x.id === p.id && !m.goods && m.options.length > 0 ? `${tr("From")} ` : ""}${price({ from_price: x.id === p.id ? m.shown : x.from_price, currency: x.currency })}`],
    ["Warranty", (x) => (x.warranty_years ? tr("{n}-year warranty", { n: x.warranty_years }) : "—")],
    ["Brand", (x) => x.brand ?? x.seller ?? "—"],
    ["Fitting", (x) => (!x.requires_shipping ? tr("Quoted by a partner") : x.installation === "required" ? tr("Only with installation") : x.installation === "optional" ? tr("Optional installation") : tr("Sold on its own"))],
  ];

  return (
    <>
      <Crumbs m={m} />
      <h1 className="display display-md">{tr("Which one is right for you?")}</h1>
      <p className="mt-1 text-slate">{tr("Compare before you choose. Same store, different options.")}</p>
      <div className="mt-6 overflow-x-auto rounded-[24px] border border-fog bg-white" style={{ boxShadow: SHOP_SHADOW }} data-testid="compare-table">
        <table className="w-full min-w-[640px] text-start">
          <thead>
            <tr>
              <th className="w-1/4 p-4" />
              {cols.map((c) => (
                <th key={c.product.id} className={`p-4 text-start align-top ${c.tag === null ? "bg-[var(--brand)] text-[var(--on-brand)]" : "bg-fog/60"}`}>
                  <span className="block text-[11px] font-extrabold uppercase tracking-wide">{c.tag === null ? tr("You are here") : tr(c.tag)}</span>
                  <span className="text-lg">{c.product.name}</span>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map(([label, fn]) => (
              <tr key={label} className="border-t border-fog">
                <td className="p-4 text-sm font-semibold text-slate">{tr(label)}</td>
                {cols.map((c) => <td key={c.product.id} className={`p-4 ${c.tag === null ? "bg-brand-wash font-semibold" : ""}`}>{fn(c.product)}</td>)}
              </tr>
            ))}
            <tr className="border-t border-fog">
              <td className="p-4" />
              {cols.map((c) => (
                <td key={c.product.id} className={`p-4 ${c.tag === null ? "bg-brand-wash" : ""}`}>
                  {c.tag === null
                    ? <Link href={m.href} className="btn-brand inline-flex px-6 py-2.5 text-sm">{ctaLabel(m)}</Link>
                    : <Link href={`/s/${store.slug}/products/${c.product.id}`} className="inline-flex rounded-full border-2 border-obsidian px-6 py-2.5 text-sm font-semibold text-obsidian hover:bg-fog">{tr("View product")}</Link>}
                </td>
              ))}
            </tr>
          </tbody>
        </table>
      </div>
      <div className="mt-8 grid gap-8 lg:grid-cols-2"><Gallery images={p.images ?? []} name={p.name} sticky={false} square={false} /><div className="space-y-4"><PriceLine m={m} /><OptionPills m={m} /><Highlights items={p.highlights} /><DetailsAccordions m={m} /></div></div>
    </>
  );
}

// ---------------------------------------------------------------- 6 marketplace

export function MarketplaceLayout({ m }: { m: ProductModel }) {
  const { p } = m;
  const imgs = (p.images ?? []).map((i) => imageSrc(i) ?? "").filter(Boolean);

  return (
    <>
      <Crumbs m={m} />
      <div className="grid gap-8 lg:grid-cols-[1.25fr_1fr]">
        {imgs.length > 1 ? (
          <div className="grid grid-cols-2 gap-3 self-start">
            {imgs.slice(0, 3).map((src, i) => (
              // eslint-disable-next-line @next/next/no-img-element -- seller-chosen image
              <img key={src} src={src} alt={i === 0 ? p.name : ""} className={`w-full rounded-[24px] object-cover ${i === 0 ? "col-span-2 aspect-[16/10]" : "aspect-[4/3]"}`} />
            ))}
          </div>
        ) : <div className="self-start"><Gallery images={p.images ?? []} name={p.name} sticky={false} /></div>}
        <div className="space-y-5 lg:sticky lg:top-24 lg:self-start">
          <div><Heading m={m} /></div>
          <div><PriceLine m={m} /></div>
          <Highlights items={p.highlights} />
          <OptionPills m={m} />
          <Fulfilled m={m} />
          <BuyButtons m={m} />
          <Trust />
        </div>
      </div>
      <div className="mt-6 max-w-3xl"><DetailsAccordions m={m} /></div>
      <Section title="Complete the set">
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{(m.pairs.length ? m.pairs : m.others).slice(0, 4).map((r) => <ShopProductCard key={r.id} product={r} slug={m.store.slug} />)}</div>
      </Section>
    </>
  );
}

// ---------------------------------------------------------------- 7 bundle

export function BundleLayout({ m }: { m: ProductModel }) {
  const { p } = m;
  const addons = (m.pairs.length ? m.pairs : m.others).slice(0, 3);
  const skip = m.cheaper[0];

  return (
    <>
      <Crumbs m={m} />
      <div className="grid gap-8 lg:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <Gallery images={p.images ?? []} name={p.name} sticky={false} />
            <div className="space-y-4"><Heading m={m} /><PriceLine m={m} /><OptionPills m={m} /><Highlights items={p.highlights} /></div>
          </div>
          {addons.length > 0 && (
            <section className={card} style={{ boxShadow: SHOP_SHADOW }} aria-label={tr("Complete your set")} data-testid="addons">
              <div className="mb-3 flex items-center justify-between"><h2 className="display display-md !text-2xl">{tr("Complete your set")}</h2><Tag>{tr("Upsell")}</Tag></div>
              <div className="grid gap-3">{addons.map((r) => <MiniProduct key={r.id} product={r} slug={m.store.slug} />)}</div>
            </section>
          )}
          <DetailsAccordions m={m} />
        </div>
        <aside className="space-y-4 lg:sticky lg:top-24 lg:self-start">
          <div className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <b className="text-lg text-obsidian">{tr("This product")}</b>
            <div className="my-3 flex items-baseline justify-between"><span>{p.name}</span><b className="text-2xl">{price({ from_price: m.shown, currency: p.currency })}</b></div>
            <Fulfilled m={m} />
            <div className="mt-4"><BuyButtons m={m} /></div>
          </div>
          {skip && (
            <div className="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
              <div className="flex items-center justify-between"><b className="text-obsidian">{tr("Looking for less?")}</b><Tag tone="down">{tr("Lower price")}</Tag></div>
              <div className="mt-2"><MiniProduct product={skip} slug={m.store.slug} /></div>
            </div>
          )}
        </aside>
      </div>
    </>
  );
}

// ---------------------------------------------------------------- 8 warranty

export function WarrantyLayout({ m }: { m: ProductModel }) {
  const { p, store } = m;
  const up = m.pricier[0];

  return (
    <>
      <Crumbs m={m} />
      <div className="grid gap-10 lg:grid-cols-[1.1fr_1fr]">
        <div className="space-y-6">
          <div><Heading m={m} size="lg" /></div>
          <div className="flex items-center gap-5">
            <div className="grid size-28 shrink-0 place-items-center rounded-full bg-[var(--brand)] text-center text-[var(--on-brand)]" style={{ boxShadow: "0 0 0 8px var(--brand-wash)" }}>
              {p.warranty_years
                ? <div><div className="text-4xl font-extrabold leading-none">{p.warranty_years}</div><div className="text-[11px] font-bold">{tr("YEAR WARRANTY")}</div></div>
                : <div className="px-2 text-sm font-bold">{tr("Free cancellation")}</div>}
            </div>
            <p className="max-w-sm text-slate">{p.warranty_years ? tr("The warranty is issued when the job is completed and stays in your account.") : tr("You can cancel any order before you pay, at no cost.")}</p>
          </div>
          <Highlights items={p.highlights} />
          <Gallery images={p.images ?? []} name={p.name} sticky={false} square={false} />
          <DetailsAccordions m={m} />
        </div>
        <div className="space-y-4 lg:sticky lg:top-24 lg:self-start">
          <div className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <PriceLine m={m} />
            <div className="my-4"><OptionPills m={m} /></div>
            <Fulfilled m={m} />
            <p className="mb-2 mt-4 text-sm font-semibold text-obsidian">{m.goods ? tr("How do you want it fitted?") : tr("How it works")}</p>
            {m.goods && m.mode === "optional" ? (
              <div className="mb-4 grid gap-2" data-testid="fitting-choices">
                <Link href={m.links.find} className="flex items-center justify-between gap-2 rounded-2xl border-2 border-[var(--brand)] bg-brand-wash p-4"><span><b className="block text-obsidian">{tr("Buy now and find me a suitable installation offer")}</b><span className="text-xs text-slate">{tr("Recommended")}</span></span><Tag>{tr("Upsell")}</Tag></Link>
                <Link href={m.links.buy} className="flex items-center justify-between gap-2 rounded-2xl border border-fog p-4"><b className="text-obsidian">{tr("Buy now without installation")}</b><Tag tone="down">{tr("Product only")}</Tag></Link>
              </div>
            ) : <BuyButtons m={m} />}
            {m.goods && m.mode === "optional" && <p className="text-center text-xs text-slate">{tr("Installation is your choice. Nearby centres send you offers without seeing your details, and you pay on the platform.")}</p>}
            <div className="mt-4"><Trust /></div>
          </div>
          {up && (
            <div className="rounded-2xl border border-fog bg-white p-4">
              <div className="mb-2 flex items-center justify-between"><b className="text-obsidian">{tr("Want more cover?")}</b><Tag>{tr("Step up")}</Tag></div>
              <MiniProduct product={up} slug={store.slug} />
            </div>
          )}
        </div>
      </div>
      <MoreProducts m={m} />
    </>
  );
}

// ---------------------------------------------------------------- 9 quote

export function QuoteLayout({ m }: { m: ProductModel }) {
  const { p, store } = m;
  const [on, setOn] = useState<string | null>(null);
  const addon = (m.pairs.length ? m.pairs : m.others)[0];
  const lowest = [...m.options].sort((a, b) => a.price - b.price)[0];
  const id = (o: { target_type: string; target: string }) => `${o.target_type}:${o.target}`;
  const sel = m.options.find((o) => id(o) === on);
  const choose = (key: string | null) => { setOn(key); const o = m.options.find((x) => id(x) === key); m.setParts(o?.target_id !== undefined ? [o.target_id] : []); };

  return (
    <>
      <Crumbs m={m} />
      <div className="grid gap-10 lg:grid-cols-[1fr_480px]">
        <div className="space-y-5">
          <Gallery images={p.images ?? []} name={p.name} sticky={false} square={false} />
          <div><Heading m={m} /></div>
          {m.lead && <p className="text-charcoal">{m.lead}</p>}
          <Highlights items={p.highlights} />
          <DetailsAccordions m={m} />
        </div>
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className={`${card} space-y-4`} style={{ boxShadow: "0 18px 40px -20px rgba(11,27,58,.3)" }}>
            <b className="text-xl text-obsidian">{m.goods ? tr("Order in a minute") : tr("Get your price in minutes")}</b>
            {m.options.length > 0 && (
              <fieldset>
                <legend className="mb-2 text-xs font-bold uppercase tracking-wide text-slate">{tr("Choose an option")}</legend>
                <div className="flex flex-wrap gap-2">
                  {m.options.map((o) => (
                    <button key={id(o)} type="button" aria-pressed={on === id(o)} onClick={() => choose(on === id(o) ? null : id(o))}
                      className={`rounded-full px-4 py-2 text-sm font-semibold ${on === id(o) ? "bg-[var(--brand)] text-[var(--on-brand)]" : "bg-fog text-obsidian"}`}>{o.target}</button>
                  ))}
                </div>
              </fieldset>
            )}
            <div className="flex items-baseline justify-between rounded-2xl bg-fog/60 p-4"><span className="text-slate">{sel ? tr("Estimated price") : tr("Price from")}</span><b className="text-2xl">{formatMoney(sel ? sel.price : m.shown, p.currency, true)}</b></div>
            <Fulfilled m={m} />
            <BuyButtons m={m} />
            {addon && (
              <div className="rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50/50 p-3">
                <div className="mb-2 flex items-center justify-between text-sm"><b className="text-obsidian">{tr("Add {name}", { name: addon.name })}</b><Tag>{tr("Upsell")}</Tag></div>
                <MiniProduct product={addon} slug={store.slug} />
              </div>
            )}
            {lowest && m.options.length > 1 && (
              <button type="button" onClick={() => choose(id(lowest))} className="flex w-full items-center justify-between border-t border-fog pt-3 text-sm">
                <span className="text-slate">{tr("Budget tight?")}</span><b className="text-obsidian">{lowest.target} · {formatMoney(lowest.price, p.currency, true)} <Tag tone="down">{tr("Lowest price")}</Tag></b>
              </button>
            )}
          </div>
        </aside>
      </div>
      <MoreProducts m={m} />
    </>
  );
}

// ---------------------------------------------------------------- 10 immersive

export function ImmersiveLayout({ m }: { m: ProductModel }) {
  const { p, store } = m;
  const img = p.images?.[0] ? imageSrc(p.images[0]) : null;
  const before = (m.pairs.length ? m.pairs : m.others)[0];

  return (
    <>
      <div className="-mx-4 grid overflow-hidden rounded-[28px] md:-mx-8 lg:grid-cols-2">
        <div className="bg-dark bg-grid-dark relative min-h-[320px] lg:min-h-[640px]">
          {img
            // eslint-disable-next-line @next/next/no-img-element -- seller-chosen image
            ? <img src={img} alt={p.name} className="absolute inset-0 h-full w-full object-cover" />
            : <div className="absolute inset-0 grid place-items-center text-9xl font-bold text-white/25" aria-hidden="true">{p.name.slice(0, 1)}</div>}
          {p.warranty_years ? <span className="absolute start-5 top-5 rounded-full bg-white px-3 py-1 text-sm font-semibold text-obsidian">{tr("{n}-year warranty", { n: p.warranty_years })}</span> : null}
        </div>
        <div className="space-y-5 bg-white p-6 md:p-10">
          <Crumbs m={m} />
          <div><Heading m={m} /></div>
          <div><PriceLine m={m} /></div>
          {m.lead && <p className="text-charcoal">{m.lead}</p>}
          <OptionPills m={m} />
          <Fulfilled m={m} />
          <BuyButtons m={m} />
          <DetailsAccordions m={m} />
          {before && (
            <div className="rounded-2xl border border-fog bg-brand-wash p-4" data-testid="before-you-go">
              <div className="mb-2 flex items-center justify-between"><b className="text-obsidian">{tr("Before you go")}</b><Tag>{tr("Upsell")}</Tag></div>
              <MiniProduct product={before} slug={store.slug} />
            </div>
          )}
        </div>
      </div>
      <MoreProducts m={m} />
      <div className="h-20" />
      <StickyBar m={m} always />
    </>
  );
}

export { Accordion };
