import { ChevronDown, ChevronRight, Globe2, MessageSquareQuote, ShieldCheck, Star, Truck, Wallet, Store, Handshake, Car, Shield, Wrench } from "lucide-react";
import { Band, ITEMS, ShopHero, ShopShell, SHADOW, ACCENT } from "./shop-storefront";

// Shop-style ArmorHub home. Shopping first (hero, categories, markets, partners, quote), platform pitch after, calmer and shorter.
// Uses the same stand-in tiles as /ui/shop-storefront; all names and figures are fictional.
const card = "rounded-[28px] bg-white p-6";
const pill = "inline-flex h-11 items-center rounded-full px-6 text-[16px]";

const PARTNERS = ["Nile Armor Co.", "Cairo Motors", "Delta Defence", "Giza Garage", "Suez Logistics", "Alex Certify"];
const MARKETS = ["Egypt", "Saudi Arabia", "United Arab Emirates", "Jordan"];
const FAQ = [
  ["How do I know a seller is genuine?", "Every seller uploads documents and is approved by our team before listing anything."],
  ["When does the seller get paid?", "Payment is held until you receive the order, then released to the seller."],
  ["Can I ask for a custom quote?", "Yes. Describe your vehicle or need and verified partners reply with prices."],
];

export function ShopHome() {
  return (
    <ShopShell>
      <ShopHero />

      {/* partner strip */}
      <section aria-label="Trusted partners" className="mt-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-[14px] text-[#5f5c5b]">
        <span>Trusted by</span>
        {PARTNERS.map((p) => (
          <span key={p} className="flex items-center gap-2"><span className="grid size-6 place-items-center rounded-full bg-white text-[10px] text-black ring-1 ring-[#ebebeb]">{p[0]}</span>{p}</span>
        ))}
      </section>

      <Band title="Markets open now: ArmorHub Egypt" items={ITEMS.slice(0, 4)} />
      <Band title="From our partners" items={[ITEMS[5], ITEMS[3], ITEMS[7], ITEMS[6]]} />

      {/* quote feature */}
      <section className="mt-16 grid items-center gap-6 rounded-[28px] bg-black p-8 text-white md:grid-cols-[1.3fr_1fr] md:p-12" aria-labelledby="quote-h">
        <div>
          <h2 id="quote-h" className="text-[32px] leading-tight tracking-[-0.05em]">Not sure what you need? Ask for a quote.</h2>
          <p className="mt-3 max-w-md text-[16px] text-white/70">Describe your vehicle or project once. Verified partners reply with prices and timelines.</p>
          <a href="#" className={`${pill} mt-6 bg-white text-black`}>Get a quote</a>
        </div>
        <div className="grid grid-cols-3 gap-2" aria-hidden>
          {[ITEMS[1], ITEMS[3], ITEMS[0]].map((i) => <div key={i.name} className="grid aspect-square place-items-center rounded-[20px]" style={{ background: i.tone }}><i.icon className="size-8 text-white/80" /></div>)}
        </div>
      </section>

      {/* platform pitch: calmer */}
      <section className="mt-20" aria-labelledby="how-h">
        <h2 id="how-h" className="mb-6 text-[20px] tracking-[-0.05em]">How ArmorHub works</h2>
        <ol className="grid gap-3 md:grid-cols-3">
          {[["Choose", "Browse verified sellers and partners, or ask for a quote.", Store], ["Pay safely", "Your payment is held until the order arrives.", Wallet], ["Receive and review", "Track delivery, get warranty, leave a review.", Truck]].map(([t, d, I], n) => {
            const Icon = I as typeof Store;
            return (
              <li key={t as string} className={card} style={{ boxShadow: SHADOW }}>
                <span className="grid size-10 place-items-center rounded-full bg-[#f2f4f5]"><Icon className="size-5" aria-hidden /></span>
                <p className="mt-4 text-[16px]">{n + 1}. {t as string}</p>
                <p className="mt-1 text-[14px] text-[#5f5c5b]">{d as string}</p>
              </li>
            );
          })}
        </ol>
      </section>

      <section className="mt-16" aria-labelledby="why-h">
        <h2 id="why-h" className="mb-6 text-[20px] tracking-[-0.05em]">Why people trust it</h2>
        <div className="grid gap-3 md:grid-cols-3">
          {[["Verified sellers", "Documents checked before anyone lists.", ShieldCheck], ["Held payments", "Sellers are paid after delivery.", Wallet], ["Real reviews", "Only buyers of an order can review it.", MessageSquareQuote]].map(([t, d, I]) => {
            const Icon = I as typeof Store;
            return <div key={t as string} className={card} style={{ boxShadow: SHADOW }}><Icon className="size-5" aria-hidden /><p className="mt-3 text-[16px]">{t as string}</p><p className="mt-1 text-[14px] text-[#5f5c5b]">{d as string}</p></div>;
          })}
        </div>
      </section>

      {/* use cases */}
      <section className="mt-16" aria-labelledby="uc-h">
        <h2 id="uc-h" className="mb-6 text-[20px] tracking-[-0.05em]">Made for</h2>
        <div className="grid gap-3 md:grid-cols-3">
          {[["Individuals", "Protect your car or yourself.", Shield, "#d9c7ad"], ["Fleets and companies", "Quotes and transport for many vehicles.", Car, "#b98a6e"], ["Workshops and installers", "Get orders and manage jobs.", Wrench, "#a9b39b"]].map(([t, d, I, c]) => {
            const Icon = I as typeof Shield;
            return (
              <a key={t as string} href="#" className="rounded-[28px] bg-white p-2" style={{ boxShadow: SHADOW }}>
                <div className="grid h-32 place-items-center rounded-[20px]" style={{ background: c as string }}><Icon className="size-8 text-white/80" aria-hidden /></div>
                <div className="p-4"><p className="text-[16px]">{t as string}</p><p className="text-[14px] text-[#5f5c5b]">{d as string}</p></div>
              </a>
            );
          })}
        </div>
      </section>

      {/* sell / partner CTA */}
      <section className="mt-16 grid gap-3 md:grid-cols-2" aria-label="Join ArmorHub">
        {[["Sell on ArmorHub", "Reach buyers across the region. Apply in minutes.", Store], ["Become a partner", "Quote jobs and grow your workshop or fleet.", Handshake]].map(([t, d, I]) => {
          const Icon = I as typeof Store;
          return (
            <div key={t as string} className={`${card} flex items-center justify-between gap-4`} style={{ boxShadow: SHADOW }}>
              <div><Icon className="size-5" aria-hidden /><p className="mt-3 text-[20px] tracking-[-0.05em]">{t as string}</p><p className="text-[14px] text-[#5f5c5b]">{d as string}</p></div>
              <a href="#" aria-label={t as string} className="grid size-12 shrink-0 place-items-center rounded-full text-white" style={{ background: ACCENT, boxShadow: "0 4px 24px rgba(69,36,219,.34)" }}><ChevronRight className="size-5" aria-hidden /></a>
            </div>
          );
        })}
      </section>

      {/* markets */}
      <section className="mt-16" aria-labelledby="mk-h">
        <h2 id="mk-h" className="mb-6 text-[20px] tracking-[-0.05em]">Markets</h2>
        <ul className="flex flex-wrap gap-2">
          {MARKETS.map((m, i) => (
            <li key={m} className="flex items-center gap-2 rounded-full border border-[#ebebeb] bg-white py-1.5 pl-1.5 pr-4 text-[16px]" style={{ boxShadow: "0 2px 8px rgba(0,0,0,.06)" }}>
              <span className="grid size-6 place-items-center rounded-full bg-[#f2f4f5]"><Globe2 className="size-3.5" aria-hidden /></span>{m}
              <span className="text-[12px] text-[#5f5c5b]">{i === 0 ? "Open" : "Soon"}</span>
            </li>
          ))}
        </ul>
      </section>

      {/* faq */}
      <section className="mt-16" aria-labelledby="faq-h">
        <h2 id="faq-h" className="mb-6 text-[20px] tracking-[-0.05em]">Questions</h2>
        <div className="grid gap-3">
          {FAQ.map(([q, a]) => (
            <details key={q} className="group rounded-[28px] bg-white px-6 py-4" style={{ boxShadow: SHADOW }}>
              <summary className="flex cursor-pointer list-none items-center justify-between text-[16px]">{q}<ChevronDown className="size-4 transition-transform group-open:rotate-180" aria-hidden /></summary>
              <p className="mt-2 text-[14px] text-[#5f5c5b]">{a}</p>
            </details>
          ))}
        </div>
        <p className="mt-6 flex items-center gap-1 text-[12px] text-[#5f5c5b]"><Star className="size-3" aria-hidden /> Fictional names and figures, for previewing the layout only.</p>
      </section>
    </ShopShell>
  );
}
