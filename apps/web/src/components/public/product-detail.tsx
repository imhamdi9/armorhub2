"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";

import { price, savingPercent } from "@/components/public/product-card";
import type { ShellStore } from "@/components/public/shell";
import { ShopProductCard } from "@/components/shop/shop-product-card";
import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { me } from "@/services/auth.service";
import type { PublicProductPage } from "@/types/public";

/**
 * Where the buttons go. Signed-in customers go straight to ordering; guests go to checkout (goods) or signup (services).
 * Finding installation offers needs an account (centres answer a signed-in customer), so a guest is sent to signup and then to the request.
 */
function useOrderLinks(slug: string, productId: number, goods: boolean) {
  const [signedIn, setSignedIn] = useState(false);

  useEffect(() => {
    me().then(() => setSignedIn(true)).catch(() => setSignedIn(false));
  }, []);

  const buy = signedIn ? `/dashboard/orders/new?product=${productId}` : goods ? `/s/${encodeURIComponent(slug)}/checkout?product=${productId}` : `/register?store=${encodeURIComponent(slug)}&product=${productId}`;
  const find = signedIn ? `/dashboard/installations/new?product=${productId}` : `/register?store=${encodeURIComponent(slug)}&product=${productId}&installation=1`;

  return { buy, find };
}

/** Big picture with thumbnails, arrows (and the keyboard) and a hover zoom. */
function Gallery({ images, name }: { images: string[]; name: string }) {
  const [index, setIndex] = useState(0);
  const [zoom, setZoom] = useState<{ x: number; y: number } | null>(null);
  const shown = images.map((i) => imageSrc(i) ?? "").filter(Boolean);

  if (shown.length === 0) {
    return <div className="grid aspect-square place-items-center rounded-[24px] bg-linen text-6xl font-black text-forest/30" aria-hidden="true">{name.slice(0, 1)}</div>;
  }

  const go = (d: number) => setIndex((i) => (i + d + shown.length) % shown.length);

  return (
    <div className="lg:sticky lg:top-24" onKeyDown={(e) => { if (e.key === "ArrowRight") go(1); if (e.key === "ArrowLeft") go(-1); }}>
      <div className="grid gap-3 lg:grid-cols-[5rem_1fr]">
        {shown.length > 1 && (
          <ul className="order-2 flex gap-2 overflow-x-auto lg:order-1 lg:flex-col" aria-label={tr("Product pictures")}>
            {shown.map((src, i) => (
              <li key={src} className="shrink-0">
                <button type="button" onClick={() => setIndex(i)} aria-label={`${tr("Picture")} ${i + 1}`} aria-current={i === index}
                  className={`block h-16 w-16 overflow-hidden rounded-xl border-2 lg:h-20 lg:w-20 ${i === index ? "border-forest" : "border-transparent opacity-70 hover:opacity-100"}`}>
                  {/* eslint-disable-next-line @next/next/no-img-element -- seller-chosen image */}
                  <img src={src} alt="" className="h-full w-full object-cover" />
                </button>
              </li>
            ))}
          </ul>
        )}
        <div className="relative order-1 overflow-hidden rounded-[24px] bg-linen lg:order-2"
          onMouseMove={(e) => { const r = e.currentTarget.getBoundingClientRect(); setZoom({ x: ((e.clientX - r.left) / r.width) * 100, y: ((e.clientY - r.top) / r.height) * 100 }); }}
          onMouseLeave={() => setZoom(null)}>
          {/* eslint-disable-next-line @next/next/no-img-element -- seller-chosen image */}
          <img src={shown[index]} alt={name} className="aspect-square w-full object-cover transition-transform duration-200"
            style={zoom ? { transform: "scale(1.8)", transformOrigin: `${zoom.x}% ${zoom.y}%` } : undefined} />
          {shown.length > 1 && (
            <>
              <button type="button" onClick={() => go(-1)} aria-label={tr("Previous picture")} className="absolute start-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl shadow-card rtl:rotate-180">‹</button>
              <button type="button" onClick={() => go(1)} aria-label={tr("Next picture")} className="absolute end-3 top-1/2 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-xl shadow-card rtl:rotate-180">›</button>
              <span className="absolute bottom-3 end-3 rounded-full bg-black/60 px-2.5 py-1 text-xs text-white">{index + 1} / {shown.length}</span>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function Accordion({ title, children, open }: { title: string; children: React.ReactNode; open?: boolean }) {
  return (
    <details className="group border-b border-fog py-4" open={open}>
      <summary className="flex cursor-pointer list-none items-center justify-between font-semibold text-obsidian">
        {tr(title)}
        <span className="text-xl transition group-open:rotate-45" aria-hidden="true">+</span>
      </summary>
      <div className="mt-3 text-sm leading-relaxed text-charcoal">{children}</div>
    </details>
  );
}

const TRUST = [
  ["🔒", "Secure payment"], ["✔", "Vetted sellers and partners"], ["↺", "Free cancellation before you pay"],
] as const;

export function ProductDetail({ store, data }: { store: ShellStore; data: PublicProductPage }) {
  const p = data.product;
  const goods = !!p.requires_shipping;
  const links = useOrderLinks(store.slug, p.id, goods);
  // How the product is sold: on its own, on its own with optional installation offers, or only together with an installation offer.
  const mode = goods ? (p.installation ?? "none") : "none";
  const href = mode === "required" ? links.find : links.buy;
  const options = p.prices.filter((r) => r.target);
  const [picked, setPicked] = useState<string | null>(null);
  const chosen = options.find((o) => `${o.target_type}:${o.target}` === picked) ?? null;
  const shown = chosen ? chosen.price : p.from_price;
  const was = !chosen ? p.compare_at_price : null;
  const saving = savingPercent(shown, was);
  const cta = useRef<HTMLAnchorElement>(null);
  const [stuck, setStuck] = useState(false);
  const paragraphs = (p.description ?? "").split("\n").filter(Boolean);
  const multiOptions = options.length > 1;

  // A bar with the price and button appears once the main button has scrolled out of sight.
  useEffect(() => {
    const el = cta.current;
    if (!el || typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(([entry]) => setStuck(!entry.isIntersecting && entry.boundingClientRect.top < 0));
    io.observe(el);

    return () => io.disconnect();
  }, []);

  return (
    <ShopShell store={store} active="products">
      <div className="my-6 rounded-[28px] bg-white p-4 md:p-8" style={{ boxShadow: SHOP_SHADOW }}>
        <nav aria-label={tr("Breadcrumb")} className="mb-5 flex flex-wrap items-center gap-1.5 text-sm text-slate">
          <Link href={`/s/${store.slug}`} className="hover:text-forest">{store.name}</Link><span aria-hidden="true">/</span>
          <Link href={`/s/${store.slug}/products`} className="hover:text-forest">{tr("All products")}</Link>
          {p.category && <><span aria-hidden="true">/</span><Link href={`/s/${store.slug}/products?category=${encodeURIComponent(p.category)}`} className="hover:text-forest">{p.category.replace(/-/g, " ")}</Link></>}
          <span aria-hidden="true">/</span><span className="text-obsidian">{p.name}</span>
        </nav>

        <div className="grid gap-10 lg:grid-cols-2">
          <Gallery images={p.images ?? []} name={p.name} />

          <div>
            {p.category && <span className="text-xs font-bold uppercase tracking-wider text-spruce">{p.category.replace(/-/g, " ")}</span>}
            <h1 className="display display-md mt-1">{p.name}</h1>
            <p className="mt-1 text-sm text-slate">
              {p.brand}{p.brand && p.seller ? " · " : ""}
              {p.seller && (p.seller_id
                ? <>{tr("Sold by")} <Link href={`/s/${store.slug}/sellers/${p.seller_id}`} className="font-medium text-forest hover:underline">{p.seller}</Link></>
                : `${tr("Sold by")} ${p.seller}`)}
            </p>

            <div className="mt-5 flex flex-wrap items-baseline gap-3">
              {!goods && !chosen && options.length > 0 && <span className="text-sm text-slate">{tr("From")}</span>}
              <span className="text-3xl font-black tracking-tight text-forest">{formatMoney(shown, p.currency, true)}</span>
              {was && saving && (
                <>
                  <span className="text-lg text-slate line-through">{formatMoney(was, p.currency, true)}</span>
                  <span className="rounded-full px-2.5 py-1 text-xs font-bold text-white" style={{ backgroundColor: "#b91c1c" }}>{tr("Save {n}%", { n: saving })}</span>
                </>
              )}
            </div>
            <p className="mt-1 text-xs text-slate">{tr("VAT is included where it applies.")}</p>

            {paragraphs[0] && <p className="mt-5 text-charcoal">{paragraphs[0]}</p>}

            {p.highlights && p.highlights.length > 0 && (
              <ul className="mt-5 space-y-2">
                {p.highlights.map((h) => (
                  <li key={h} className="flex items-start gap-2 text-sm text-charcoal">
                    <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-forest text-xs text-lime" aria-hidden="true">✓</span>{h}
                  </li>
                ))}
              </ul>
            )}

            {multiOptions && (
              <fieldset className="mt-6">
                <legend className="mb-2 text-sm font-semibold text-obsidian">{tr("Choose an option")}</legend>
                <div className="flex flex-wrap gap-2">
                  {options.map((o) => {
                    const id = `${o.target_type}:${o.target}`;
                    return (
                      <button key={id} type="button" aria-pressed={picked === id} onClick={() => setPicked(picked === id ? null : id)}
                        className={`rounded-full border px-4 py-2 text-sm font-medium transition ${picked === id ? "border-forest bg-forest text-white" : "border-fog bg-white text-obsidian hover:border-forest"}`}>
                        {o.target} · {formatMoney(o.price, p.currency, true)}
                      </button>
                    );
                  })}
                </div>
              </fieldset>
            )}

            {p.fulfilled_by_armorhub && (
              <p className="mt-4 text-sm font-medium text-forest" data-testid="fulfilled-badge">
                ✔ {tr("Fulfilled by ArmorHub: stored in the market warehouse and shipped fast.")}
                {p.in_stock === false && <span className="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-red-700">{tr("Out of stock")}</span>}
              </p>
            )}

            <div className="mt-6 grid gap-3 sm:grid-cols-1" data-testid="buy-buttons">
              {!goods && (
                <>
                  <Link ref={cta} href={href} className="btn-brand inline-flex items-center justify-center px-8 py-4 text-lg">{tr("Get a quote")}</Link>
                  <p className="text-center text-xs text-slate">{tr("Nearby professionals send you their quote. Pay only when you accept one.")}</p>
                </>
              )}
              {goods && mode === "none" && (
                <>
                  <Link ref={cta} href={links.buy} className="btn-brand inline-flex items-center justify-center px-8 py-4 text-lg">{tr("Buy now")}</Link>
                  <p className="text-center text-xs text-slate">{tr("You pay securely online and track your delivery.")}</p>
                </>
              )}
              {goods && mode === "optional" && (
                <>
                  <Link ref={cta} href={links.buy} className="btn-brand inline-flex items-center justify-center px-8 py-4 text-lg">{tr("Buy now without installation")}</Link>
                  <Link href={links.find} className="inline-flex items-center justify-center rounded-full border-2 border-forest px-8 py-4 text-lg font-semibold text-forest hover:bg-linen">{tr("Buy now and find me a suitable installation offer")}</Link>
                  <p className="text-center text-xs text-slate">{tr("Installation is your choice. Nearby centres send you offers without seeing your details, and you pay on the platform.")}</p>
                </>
              )}
              {goods && mode === "required" && (
                <>
                  <Link ref={cta} href={links.find} className="btn-brand inline-flex items-center justify-center px-8 py-4 text-lg">{tr("Find me a suitable installation offer")}</Link>
                  <p className="text-center text-xs text-slate" data-testid="installation-required-note">{tr("This product is sold only together with an installation offer. We ask nearby centres to send you offers first, and you can buy the product once you choose one.")}</p>
                </>
              )}
            </div>

            <ul className="mt-6 grid gap-2 rounded-2xl bg-linen p-4 text-sm sm:grid-cols-3">
              {TRUST.map(([icon, label]) => <li key={label} className="flex items-center gap-2"><span aria-hidden="true">{icon}</span>{tr(label)}</li>)}
            </ul>

            <div className="mt-6 border-t border-fog">
              <Accordion title="Description" open>
                {paragraphs.length > 0 ? paragraphs.map((t) => <p key={t} className="mb-2">{t}</p>) : <p>{p.name}</p>}
              </Accordion>
              {options.length > 0 && (
                <Accordion title="Prices">
                  <table className="w-full"><tbody>
                    {options.map((o) => (
                      <tr key={`${o.target_type}-${o.target}`} className="border-b border-fog last:border-0">
                        <td className="py-2">{o.target}</td>
                        <td className="py-2 text-end font-medium">{formatMoney(o.price, p.currency, true)}</td>
                      </tr>
                    ))}
                  </tbody></table>
                  {!goods && <p className="mt-2 text-xs text-slate">{tr("Service by a partner is quoted separately after you order.")}</p>}
                </Accordion>
              )}
              <Accordion title={goods ? "Shipping and delivery" : "How it works"}>
                {goods ? tr("The seller ships your order with a carrier and you follow every step, with tracking, from your account.") : tr("Choose a service, get quotes from nearby vetted professionals, accept the one you like and pay online. You follow every step until the job is done.")}
              </Accordion>
              <Accordion title="Warranty and cancellation">
                {p.warranty_years ? <p className="mb-2">🛡 {tr("{n}-year warranty", { n: p.warranty_years })}</p> : null}
                <p>{tr("You can cancel any order before you pay, at no cost. After payment, contact the store from your order page.")}</p>
              </Accordion>
            </div>
          </div>
        </div>

        {data.related && data.related.length > 0 && (
          <section className="mt-16" aria-label={tr("You may also like")}>
            <h2 className="display display-md mb-6">{tr("You may also like")}</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {data.related.map((r) => <ShopProductCard key={r.id} product={r} slug={store.slug} />)}
            </div>
          </section>
        )}
      </div>

      {stuck && (
        <div className="fixed inset-x-0 bottom-16 z-40 border-t md:bottom-0 md:ps-16 border-fog bg-white/95 px-4 py-3 shadow-lift backdrop-blur" role="region" aria-label={tr("Quick order")}>
          <div className="mx-auto flex max-w-[1200px] items-center gap-3">
            {p.images?.[0] && (
              // eslint-disable-next-line @next/next/no-img-element -- seller-chosen image
              <img src={imageSrc(p.images[0]) ?? ""} alt="" className="h-12 w-12 rounded-lg object-cover" />
            )}
            <div className="min-w-0 flex-1"><p className="truncate font-semibold text-obsidian">{p.name}</p><p className="text-sm text-forest">{price({ from_price: shown, currency: p.currency })}</p></div>
            <Link href={href} className="btn-brand px-6 py-3">{!goods ? tr("Get a quote") : mode === "required" ? tr("Find me a suitable installation offer") : tr("Buy now")}</Link>
          </div>
        </div>
      )}
    </ShopShell>
  );
}
