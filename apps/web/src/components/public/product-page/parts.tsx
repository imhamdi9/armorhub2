"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { price } from "@/components/public/product-card";
import type { ProductModel } from "@/components/public/product-page/model";
import { ProductTile } from "@/components/shop/shop-product-card";
import { SHOP_SHADOW } from "@/components/shop/shop-shell";
import { errorMessage } from "@/lib/errors";
import { formatDate, formatMoney, formatNumber, tr } from "@/lib/i18n";
import { addToCart } from "@/services/cart.service";
import { addFavourite, getFavouriteIds, getFit, getGarage, removeFavourite, type Vehicle, type VehicleFit } from "@/services/ops.service";
import { getMyReview, saveReview, uploadReviewPhoto } from "@/services/platform.service";
import { imageSrc } from "@/lib/surfaces";
import type { PublicProduct } from "@/types/public";

/** Big picture with thumbnails, arrows (and the keyboard) and a hover zoom. */
export function Gallery({ images, name, sticky = true, square = true }: { images: string[]; name: string; sticky?: boolean; square?: boolean }) {
  const [index, setIndex] = useState(0);
  const [zoom, setZoom] = useState<{ x: number; y: number } | null>(null);
  const shown = images.map((i) => imageSrc(i) ?? "").filter(Boolean);

  if (shown.length === 0) {
    return <div className={`grid ${square ? "aspect-square" : "aspect-[4/3]"} place-items-center rounded-[24px] bg-brand-wash text-6xl font-bold text-brand-ink/40`} aria-hidden="true">{name.slice(0, 1)}</div>;
  }

  const go = (d: number) => setIndex((i) => (i + d + shown.length) % shown.length);

  return (
    <div className={sticky ? "lg:sticky lg:top-24" : ""} onKeyDown={(e) => { if (e.key === "ArrowRight") go(1); if (e.key === "ArrowLeft") go(-1); }}>
      <div className="grid gap-3 lg:grid-cols-[5rem_1fr]">
        {shown.length > 1 && (
          <ul className="order-2 flex gap-2 overflow-x-auto lg:order-1 lg:flex-col" aria-label={tr("Product pictures")}>
            {shown.map((src, i) => (
              <li key={src} className="shrink-0">
                <button type="button" onClick={() => setIndex(i)} aria-label={`${tr("Picture")} ${i + 1}`} aria-current={i === index}
                  className={`block h-16 w-16 overflow-hidden rounded-xl border-2 lg:h-20 lg:w-20 ${i === index ? "border-[var(--brand)]" : "border-transparent opacity-70 hover:opacity-100"}`}>
                  {/* eslint-disable-next-line @next/next/no-img-element -- seller-chosen image */}
                  <img src={src} alt="" className="h-full w-full object-cover" />
                </button>
              </li>
            ))}
          </ul>
        )}
        <div className="relative order-1 overflow-hidden rounded-[24px] bg-brand-wash lg:order-2"
          onMouseMove={(e) => { const r = e.currentTarget.getBoundingClientRect(); setZoom({ x: ((e.clientX - r.left) / r.width) * 100, y: ((e.clientY - r.top) / r.height) * 100 }); }}
          onMouseLeave={() => setZoom(null)}>
          {/* eslint-disable-next-line @next/next/no-img-element -- seller-chosen image */}
          <img src={shown[index]} alt={name} className={`${square ? "aspect-square" : "aspect-[4/3]"} w-full object-cover transition-transform duration-200`}
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

export function Accordion({ title, children, open }: { title: string; children: React.ReactNode; open?: boolean }) {
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

const TRUST = [["🔒", "Secure payment"], ["✔", "Vetted sellers and partners"], ["↺", "Free cancellation before you pay"]] as const;

export function Trust() {
  return (
    <ul className="grid gap-2 rounded-2xl bg-brand-wash p-4 text-sm sm:grid-cols-3">
      {TRUST.map(([icon, label]) => <li key={label} className="flex items-center gap-2"><span aria-hidden="true">{icon}</span>{tr(label)}</li>)}
    </ul>
  );
}

export function Crumbs({ m }: { m: ProductModel }) {
  const { p, store } = m;

  return (
    <nav aria-label={tr("Breadcrumb")} className="mb-5 flex flex-wrap items-center gap-1.5 text-sm text-slate">
      <Link href={`/s/${store.slug}`} className="hover:text-obsidian">{store.name}</Link><span aria-hidden="true">/</span>
      <Link href={`/s/${store.slug}/products`} className="hover:text-obsidian">{tr("All products")}</Link>
      {p.category && <><span aria-hidden="true">/</span><Link href={`/s/${store.slug}/products?category=${encodeURIComponent(p.category)}`} className="hover:text-obsidian">{p.category.replace(/-/g, " ")}</Link></>}
      <span aria-hidden="true">/</span><span className="text-obsidian">{p.name}</span>
    </nav>
  );
}

/** Category, name, brand and seller. */
export function Heading({ m, size = "md" }: { m: ProductModel; size?: "md" | "lg" }) {
  const { p, store } = m;

  return (
    <>
      {p.category && <span className="text-xs font-bold uppercase tracking-wider text-brand-ink">{p.category.replace(/-/g, " ")}</span>}
      <h1 className={`display mt-1 ${size === "lg" ? "display-lg" : "display-md"}`}>{p.name}</h1>
      <Rating m={m} />
      <p className="mt-1 text-sm text-slate">
        {p.brand}{p.brand && p.seller ? " · " : ""}
        {p.seller && (p.seller_id
          ? <>{tr("Sold by")} <Link href={`/s/${store.slug}/sellers/${p.seller_id}`} className="font-medium text-obsidian hover:underline">{p.seller}</Link></>
          : `${tr("Sold by")} ${p.seller}`)}
      </p>
    </>
  );
}

export function PriceLine({ m, size = "text-3xl" }: { m: ProductModel; size?: string }) {
  const { p, goods, chosen, options, shown, was, saving } = m;

  return (
    <>
      <div className="flex flex-wrap items-baseline gap-3">
        {!goods && !chosen && options.length > 0 && <span className="text-sm text-slate">{tr("From")}</span>}
        <span className={`${size} font-extrabold tracking-tight text-obsidian`}>{formatMoney(shown, p.currency, true)}</span>
        {was && saving && (
          <>
            <span className="text-lg text-slate line-through">{formatMoney(was, p.currency, true)}</span>
            <span className="rounded-full px-2.5 py-1 text-xs font-bold text-white" style={{ backgroundColor: "#b91c1c" }}>{tr("Save {n}%", { n: saving })}</span>
          </>
        )}
      </div>
      <p className="mt-1 text-xs text-slate">{tr("VAT is included where it applies.")}{m.feePercent > 0 && <> {tr("A platform fee of {n}% is added at payment and shown before you pay.", { n: formatNumber(m.feePercent) })}</>}</p>
    </>
  );
}

export function Highlights({ items }: { items?: string[] }) {
  if (!items || items.length === 0) return null;

  return (
    <ul className="space-y-2">
      {items.map((h) => (
        <li key={h} className="flex items-start gap-2 text-sm text-charcoal">
          <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-[var(--brand)] text-xs text-[var(--on-brand)]" aria-hidden="true">✓</span>{h}
        </li>
      ))}
    </ul>
  );
}

/** The product's options as pills (parts, sizes…). */
export function OptionPills({ m }: { m: ProductModel }) {
  const { p, options, picked, setPicked, multiOptions } = m;
  if (!multiOptions) return null;

  return (
    <fieldset>
      <legend className="mb-2 text-sm font-semibold text-obsidian">{tr("Choose an option")}</legend>
      <div className="flex flex-wrap gap-2">
        {options.map((o) => {
          const id = `${o.target_type}:${o.target}`;
          return (
            <button key={id} type="button" aria-pressed={picked === id} onClick={() => setPicked(picked === id ? null : id)}
              className={`rounded-full border px-4 py-2 text-sm font-medium transition ${picked === id ? "border-obsidian bg-obsidian text-white" : "border-fog bg-white text-obsidian hover:border-obsidian"}`}>
              {o.target} · {formatMoney(o.price, p.currency, true)}
            </button>
          );
        })}
      </div>
    </fieldset>
  );
}

export function Fulfilled({ m }: { m: ProductModel }) {
  const { p } = m;
  if (!p.fulfilled_by_armorhub) return null;

  return (
    <p className="text-sm font-medium text-brand-ink" data-testid="fulfilled-badge">
      ✔ {tr("Fulfilled by ArmorHub: stored in the market warehouse and shipped fast.")}
      {p.in_stock === false && <span className="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-red-700">{tr("Out of stock")}</span>}
    </p>
  );
}

const BIG = "btn-brand inline-flex items-center justify-center px-8 py-4 text-lg";

/** The real buying actions of the product, whatever the layout: quote, buy, buy with optional installation, or installation first. */
/** A heart that saves the product for later. Signed-in customers only. */
export function FavouriteButton({ m }: { m: ProductModel }) {
  const [saved, setSaved] = useState(false);
  useEffect(() => {
    if (m.links.signedIn) getFavouriteIds().then((ids) => setSaved(ids.includes(m.p.id))).catch(() => {});
  }, [m.links.signedIn, m.p.id]);
  if (!m.links.signedIn) return null;

  return (
    <button
      type="button"
      aria-pressed={saved}
      data-testid="favourite"
      onClick={async () => {
        const next = !saved;
        setSaved(next);
        try { await (next ? addFavourite(m.p.id) : removeFavourite(m.p.id)); } catch { setSaved(!next); }
      }}
      className="inline-flex items-center justify-center gap-2 rounded-full border border-fog px-6 py-2.5 text-sm font-semibold text-obsidian hover:bg-fog"
    >
      <span aria-hidden="true">{saved ? "♥" : "♡"}</span>{saved ? tr("Saved to favourites") : tr("Save to favourites")}
    </button>
  );
}

export function BuyButtons({ m, big = true }: { m: ProductModel; big?: boolean }) {
  const { goods, mode, links, href, cta } = m;
  const cls = big ? BIG : "btn-brand inline-flex items-center justify-center px-6 py-3 text-base";
  const second = big ? "inline-flex items-center justify-center rounded-full border-2 border-obsidian px-8 py-4 text-lg font-semibold text-obsidian hover:bg-fog" : "inline-flex items-center justify-center rounded-full border-2 border-obsidian px-6 py-3 text-base font-semibold text-obsidian hover:bg-fog";

  if (m.p.sale_mode === "quote") {
    return (
      <div className="grid gap-3" data-testid="buy-buttons">
        <Link ref={cta} href={links.quote} className={cls} data-testid="request-price">{tr("Request a price")}</Link>
        <p className="text-center text-xs text-slate">{tr("Sellers send you their price. You compare, accept one and pay only then.")}</p>
        <FavouriteButton m={m} />
      </div>
    );
  }

  return (
    <div className="grid gap-3" data-testid="buy-buttons">
      {m.p.units_left ? <p className="text-center text-sm font-semibold text-amber-700" data-testid="units-left">{tr("Only {n} left", { n: m.p.units_left })}</p> : null}
      <FavouriteButton m={m} />
      {m.whatsapp && (
        <a href={m.whatsapp} target="_blank" rel="noopener noreferrer" className="order-last text-center text-sm font-semibold text-brand-ink underline underline-offset-4" data-testid="ask-whatsapp">{tr("Have a question? Ask the store on WhatsApp")}</a>
      )}
      {!goods && (
        <>
          <Link ref={cta} href={href} className={cls}>{tr("Get a quote")}</Link>
          <p className="text-center text-xs text-slate">{tr("Nearby professionals send you their quote. Pay only when you accept one.")}</p>
        </>
      )}
      {goods && mode === "none" && (
        <>
          {m.p.fitment_required ? (
            // This part must be checked against a car, which only the cart does: no direct "Buy now".
            m.links.signedIn ? <p className="text-center text-sm text-slate" data-testid="fit-required">{tr("Choose your car below. This part is only sold for cars it fits.")}</p>
              : <Link ref={cta} href="/login" className={cls}>{tr("Sign in and add your car to buy")}</Link>
          ) : <Link ref={cta} href={links.buy} className={cls}>{tr("Buy now")}</Link>}
          <AddToCart m={m} />
          <p className="text-center text-xs text-slate">{tr("You pay securely online and track your delivery.")}</p>
        </>
      )}
      {goods && mode === "optional" && (
        <>
          <Link ref={cta} href={links.buy} className={cls}>{tr("Buy now without installation")}</Link>
          <Link href={links.find} className={second}>{tr("Buy now and find me a suitable installation offer")}</Link>
          <AddToCart m={m} />
          <p className="text-center text-xs text-slate">{tr("Installation is your choice. Nearby centres send you offers without seeing your details, and you pay on the platform.")}</p>
        </>
      )}
      {goods && mode === "required" && (
        <>
          <Link ref={cta} href={links.find} className={cls}>{tr("Find me a suitable installation offer")}</Link>
          <p className="text-center text-xs text-slate" data-testid="installation-required-note">{tr("This product is sold only together with an installation offer. We ask nearby centres to send you offers first, and you can buy the product once you choose one.")}</p>
        </>
      )}
    </div>
  );
}

/** Puts the product (with the option chosen on the page) in the customer's cart, then opens the cart. Signed-in customers only: a guest buys it now. */
export function AddToCart({ m }: { m: ProductModel }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // The customer's cars: the default one is chosen, so the server can refuse parts that do not fit it.
  const [cars, setCars] = useState<Vehicle[]>([]);
  const [car, setCar] = useState("");
  const [fits, setFits] = useState<VehicleFit[]>([]);
  useEffect(() => {
    if (!m.links.signedIn) return;
    getGarage().then((v) => { setCars(v); setCar(String(v.find((c) => c.is_default)?.id ?? "")); }).catch(() => {});
    getFit(m.p.id).then(setFits).catch(() => {});
  }, [m.links.signedIn]);
  if (!m.goods || m.mode === "required" || !m.links.signedIn) return null;

  async function add() {
    setBusy(true);
    setError(null);
    try {
      await addToCart(m.p.id, m.chosen?.target_id, 1, car ? Number(car) : null);
      router.push("/dashboard/cart");
    } catch (e) {
      setError(errorMessage(e));
      setBusy(false);
    }
  }

  return (
    <div>
      {cars.length > 0 && (
        <label className="mb-2 block text-sm text-slate">{tr("For my car")}
          <select value={car} onChange={(e) => setCar(e.target.value)} className="mt-1 h-10 w-full rounded-[10px] border border-fog bg-white px-2 text-sm text-obsidian" data-testid="cart-vehicle">
            <option value="">{tr("No specific car")}</option>
            {cars.map((c) => <option key={c.id} value={c.id}>{c.nickname || c.label}</option>)}
          </select>
        </label>
      )}
      {(() => {
        const f = fits.find((x) => String(x.vehicle_id) === car);
        if (!f || f.status === "unknown") return null;
        const ok = f.status === "fits" || f.status === "universal";
        return <p role="status" data-testid="fit-badge" className={`mb-2 rounded-lg px-3 py-2 text-sm ${ok ? "bg-green-50 text-green-900" : "bg-amber-50 text-amber-900"}`}>{f.status === "universal" ? tr("Fits every car") : ok ? tr("Fits your {car}", { car: f.label }) : tr("Does not fit your {car}", { car: f.label })}</p>;
      })()}
      <button type="button" onClick={add} disabled={busy || (!!m.p.fitment_required && (!car || fits.find((x) => String(x.vehicle_id) === car)?.status === "does_not_fit"))} data-testid="add-to-cart" className="inline-flex w-full items-center justify-center rounded-full border-2 border-obsidian px-6 py-3 text-base font-semibold text-obsidian hover:bg-fog disabled:opacity-60">{tr("Add to cart")}</button>
      {error && <p role="alert" className="mt-1 text-center text-sm text-red-600">{error}</p>}
    </div>
  );
}

export function ctaLabel(m: ProductModel) {
  if (m.p.sale_mode === "quote") return tr("Request a price");
  return !m.goods ? tr("Get a quote") : m.mode === "required" ? tr("Find me a suitable installation offer") : tr("Buy now");
}

/** The details every layout ends with: description, prices, how it works, warranty. */
export function DetailsAccordions({ m }: { m: ProductModel }) {
  const { p, goods, options, paragraphs, page } = m;

  return (
    <div className="border-t border-fog">
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
      {page.specs && page.specs.length > 0 && (
        <Accordion title="Specifications">
          <table className="w-full" data-testid="specs"><tbody>
            {page.specs.map((r) => <tr key={r.label} className="border-b border-fog last:border-0"><th scope="row" className="py-2 pe-3 text-start font-medium">{r.label}</th><td className="py-2">{r.value}</td></tr>)}
          </tbody></table>
        </Accordion>
      )}
      <Accordion title={goods ? "Shipping and delivery" : "How it works"}>
        {goods ? tr("The seller ships your order with a carrier and you follow every step, with tracking, from your account.") : tr("Choose a service, get quotes from nearby vetted professionals, accept the one you like and pay online. You follow every step until the job is done.")}
        {page.lead_time && <p className="mt-2 font-medium" data-testid="lead-time">{page.lead_time}</p>}
        {page.free_delivery_from != null && Number(page.free_delivery_from) > 0 && <p className="mt-2 font-medium" data-testid="free-delivery">{tr("Free delivery on orders from {amount}", { amount: formatMoney(page.free_delivery_from, p.currency, true) })}</p>}
        {page.quote_note && <p className="mt-2 text-slate">{page.quote_note}</p>}
      </Accordion>
      <Accordion title="Warranty and cancellation">
        {p.warranty_years ? <p className="mb-2">🛡 {tr("{n}-year warranty", { n: p.warranty_years })}</p> : null}
        {page.warranty_terms && <p className="mb-2" data-testid="warranty-terms">{page.warranty_terms}</p>}
        <p>{tr("You can cancel any order before you pay, at no cost. After payment, contact the store from your order page.")}</p>
      </Accordion>
      {page.faqs?.map((f) => <Accordion key={f.q} title={f.q}><p>{f.a}</p></Accordion>)}
    </div>
  );
}

/** A small product card that links to that product's page, for up- and cross-sells. */
export function MiniProduct({ product, slug, tag }: { product: PublicProduct; slug: string; tag?: string }) {
  return (
    <Link href={`/s/${slug}/products/${product.id}`} className="group flex items-center gap-3 rounded-2xl border border-fog bg-white p-3 transition hover:-translate-y-0.5 hover:border-obsidian/30" style={{ boxShadow: SHOP_SHADOW }}>
      <span className="size-16 shrink-0"><ProductTile product={product} /></span>
      <span className="min-w-0 flex-1">
        <span className="block truncate font-semibold text-obsidian">{product.name}</span>
        <span className="block truncate text-xs text-slate">{product.brand ?? product.seller ?? ""}</span>
        <span className="text-sm font-bold text-obsidian">{price(product)}</span>
      </span>
      {tag && <span className="shrink-0 rounded-md bg-brand-wash px-2 py-1 text-[11px] font-bold text-brand-ink">{tr(tag)}</span>}
    </Link>
  );
}

/** A bar that stays at the bottom with the price and the main action. */
export function StickyBar({ m, always = false }: { m: ProductModel; always?: boolean }) {
  const { p, shown, href, stuck } = m;
  if (!always && !stuck) return null;

  return (
    <div className="fixed inset-x-0 bottom-16 z-40 border-t border-fog bg-white/95 px-4 py-3 shadow-lift backdrop-blur md:bottom-0" role="region" aria-label={tr("Quick order")}>
      <div className="mx-auto flex max-w-[1200px] items-center gap-3">
        {p.images?.[0] && (
          // eslint-disable-next-line @next/next/no-img-element -- seller-chosen image
          <img src={imageSrc(p.images[0]) ?? ""} alt="" className="h-12 w-12 rounded-lg object-cover" />
        )}
        <div className="min-w-0 flex-1"><p className="truncate font-semibold text-obsidian">{p.name}</p><p className="text-sm text-slate">{price({ from_price: shown, currency: p.currency })}</p></div>
        <Link href={href} className="btn-brand px-6 py-3">{ctaLabel(m)}</Link>
      </div>
    </div>
  );
}

export function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="mt-14" aria-label={tr(title)}>
      <h2 className="display display-md mb-5">{tr(title)}</h2>
      {children}
    </section>
  );
}

const Stars = ({ value }: { value: number }) => (
  <span className="tracking-widest text-amber-500" aria-hidden="true">{"★".repeat(Math.round(value))}<span className="text-fog">{"★".repeat(5 - Math.round(value))}</span></span>
);

/** The average rating next to the title, when the product has reviews. */
export function Rating({ m }: { m: ProductModel }) {
  const { reviews } = m;
  if (reviews.count === 0 || reviews.average === null) return null;

  return (
    <a href="#reviews" className="mt-1.5 inline-flex items-center gap-2 text-sm" data-testid="rating">
      <Stars value={reviews.average} />
      <b>{formatNumber(reviews.average, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</b>
      <span className="text-slate">({formatNumber(reviews.count)})</span>
    </a>
  );
}

/** A customer who received the product can rate it here; everyone sees the average and the latest comments. */
function ReviewForm({ productId }: { productId: number }) {
  const [state, setState] = useState<"loading" | "hidden" | "ready" | "saved">("loading");
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState("");
  const [photos, setPhotos] = useState<string[]>([]);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getMyReview(productId).then((r) => {
      if (!r.eligible) return setState("hidden");
      if (r.review) { setRating(r.review.rating); setComment(r.review.comment ?? ""); setPhotos(r.review.photos ?? []); }
      setState("ready");
    }).catch(() => setState("hidden"));
  }, [productId]);

  if (state === "loading" || state === "hidden") return null;

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    if (rating < 1) return setError(tr("Choose a rating from 1 to 5."));
    try {
      await saveReview(productId, rating, comment, photos);
      setState("saved");
    } catch {
      setError(tr("Could not save your review. Try again."));
    }
  }

  async function addPhotos(files: FileList | null) {
    if (!files) return;
    setError(null);
    setUploading(true);
    try {
      for (const file of Array.from(files).slice(0, 4 - photos.length)) {
        const path = await uploadReviewPhoto(productId, file);
        setPhotos((p) => [...p, path].slice(0, 4));
      }
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setUploading(false);
    }
  }

  return (
    <form onSubmit={submit} className="mt-6 rounded-2xl border border-fog bg-brand-wash p-5" data-testid="review-form">
      <p className="font-semibold text-obsidian">{tr("You received this product. How was it?")}</p>
      <div className="my-3 flex gap-1" role="radiogroup" aria-label={tr("Rating")}>
        {[1, 2, 3, 4, 5].map((n) => (
          <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n}`} onClick={() => setRating(n)} className={`text-3xl leading-none ${n <= rating ? "text-amber-500" : "text-fog"}`}>★</button>
        ))}
      </div>
      <textarea value={comment} onChange={(e) => setComment(e.target.value)} maxLength={1000} rows={3} placeholder={tr("Add a comment (optional)")} className="w-full rounded-xl border border-fog bg-white p-3 text-sm" />
      <div className="mt-3" data-testid="review-photos">
        <p className="mb-2 text-sm text-slate">{tr("Add photos of what you received (up to 4)")}</p>
        <div className="flex flex-wrap items-center gap-2">
          {photos.map((p) => (
            <span key={p} className="relative">
              {/* eslint-disable-next-line @next/next/no-img-element -- a picture the customer just uploaded */}
              <img src={imageSrc(p) ?? ""} alt="" className="size-16 rounded-xl border border-fog object-cover" />
              <button type="button" aria-label={tr("Remove photo")} onClick={() => setPhotos((all) => all.filter((x) => x !== p))} className="absolute -end-1 -top-1 grid size-5 place-items-center rounded-full bg-black text-xs text-white">×</button>
            </span>
          ))}
          {photos.length < 4 && (
            <label className="grid size-16 cursor-pointer place-items-center rounded-xl border border-dashed border-slate/50 bg-white text-2xl text-slate">
              {uploading ? "…" : "+"}
              <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple className="sr-only" disabled={uploading} onChange={(e) => { addPhotos(e.target.files); e.target.value = ""; }} aria-label={tr("Add photos")} />
            </label>
          )}
        </div>
      </div>
      {error && <p className="mt-2 text-sm text-red-600" role="alert">{error}</p>}
      {state === "saved" && <p className="mt-2 text-sm text-brand-ink" role="status">{tr("Thank you. Your review is saved.")}</p>}
      <button type="submit" className="btn-brand mt-3 px-6 py-2.5 text-sm">{tr("Save review")}</button>
    </form>
  );
}

export function ReviewsSection({ m }: { m: ProductModel }) {
  const { reviews, p } = m;

  return (
    <section id="reviews" className="mt-14 scroll-mt-24" aria-label={tr("Reviews")}>
      <h2 className="display display-md mb-4">{tr("Reviews")}</h2>
      {reviews.count === 0 ? (
        <p className="text-sm text-slate">{tr("No reviews yet. Customers who receive this product can rate it here.")}</p>
      ) : (
        <>
          <div className="mb-4 flex items-center gap-3">
            <span className="text-4xl font-extrabold tracking-tight">{formatNumber(reviews.average ?? 0, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}</span>
            <div><Stars value={reviews.average ?? 0} /><p className="text-sm text-slate">{tr("{n} reviews", { n: formatNumber(reviews.count) })}</p></div>
          </div>
          <ul className="grid gap-3 md:grid-cols-2">
            {reviews.items.map((r) => (
              <li key={r.id} className="rounded-2xl border border-fog p-4">
                <div className="flex items-center justify-between"><Stars value={r.rating} /><span className="text-xs text-slate">{r.date ? formatDate(r.date) : ""}</span></div>
                {r.comment && <p className="mt-2 text-sm text-charcoal">{r.comment}</p>}
                {(r.photos ?? []).length > 0 && (
                  <div className="mt-2 flex flex-wrap gap-2" data-testid="review-photo-list">
                    {r.photos!.map((src) => (
                      <a key={src} href={imageSrc(src) ?? "#"} target="_blank" rel="noopener noreferrer">
                        {/* eslint-disable-next-line @next/next/no-img-element -- a customer's uploaded picture */}
                        <img src={imageSrc(src) ?? ""} alt={tr("Photo from a customer")} loading="lazy" className="size-16 rounded-xl border border-fog object-cover" />
                      </a>
                    ))}
                  </div>
                )}
                <p className="mt-2 text-xs font-semibold text-obsidian">{r.author}</p>
              </li>
            ))}
          </ul>
        </>
      )}
      <ReviewForm productId={p.id} />
    </section>
  );
}

/** Why it is safe to book or buy here, in the four things that actually protect the customer. Nothing here is a promise the platform does not keep in code. */
export function SafetyBlock({ m }: { m: ProductModel }) {
  const items: [string, string, string][] = m.goods
    ? [
        ["🔒", "Secure payment", "You pay securely online and your card details never reach the seller."],
        ["✔", "Vetted sellers", "Sellers are verified with their ID and commercial registration before they can sell."],
        ["📦", "Tracked delivery", "You follow every step of the delivery from your account."],
        ["↺", "Free cancellation before you pay", "You can cancel any order before you pay, at no cost."],
      ]
    : [
        ["✔", "Verified partners", "Every partner's ID and commercial registration are checked before they can quote."],
        ["🔒", "Your payment is held", "The money is held in the app until the job is done."],
        ["🛡", "Warranty in your account", "A warranty is issued automatically when the job is completed."],
        ["💬", "Keep it in the app", "Warranty and help with a dispute apply to jobs paid in the app, so book and pay here."],
      ];

  return (
    <section className="mt-14" aria-label={tr("Why booking here is safe")} data-testid="safety">
      <h2 className="display display-md mb-5">{m.goods ? tr("Why buying here is safe") : tr("Why booking here is safe")}</h2>
      <ul className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
        {items.map(([icon, head, text]) => (
          <li key={head} className="rounded-2xl border border-fog bg-brand-wash p-4">
            <span className="mb-2 block text-2xl" aria-hidden="true">{icon}</span>
            <b className="block text-obsidian">{tr(head)}</b>
            <span className="mt-1 block text-sm text-slate">{tr(text)}</span>
          </li>
        ))}
      </ul>
    </section>
  );
}
