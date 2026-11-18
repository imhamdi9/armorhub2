"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { PublicShell } from "@/components/public/shell";
import { formatNumber, tr } from "@/lib/i18n";
import { useMarketOffers } from "@/lib/market-offers";
import { getPublicTerms, type PublicTerms } from "@/services/auth.service";

type Audience = "seller" | "partner";

const AUDIENCES: { key: Audience; icon: string; title: string; text: string; signup: string }[] = [
  { key: "seller", icon: "📦", title: "I sell products", text: "Brands and shops that list products and ship them to customers.", signup: "/seller-signup" },
  { key: "partner", icon: "🛠️", title: "I install or maintain", text: "Installation and maintenance centres, workshops and technicians that quote for jobs.", signup: "/partner-signup" },
];

/** Pricing for businesses: pick what you are, compare the plans, then go on to the application form with the plan already chosen. */
export function Pricing({ initial, store }: { initial?: Audience; store?: string }) {
  const [audience, setAudience] = useState<Audience | null>(initial ?? null);
  const offers = useMarketOffers();
  const [terms, setTerms] = useState<PublicTerms | null>(null);

  useEffect(() => {
    getPublicTerms().then(setTerms).catch(() => setTerms(null));
  }, []);

  const plans = terms?.plans ?? [];
  const current = AUDIENCES.find((a) => a.key === audience);
  const popular = plans.length > 2 ? plans[1]?.key : null;

  return (
    <PublicShell>
      <main className="mx-auto max-w-[1100px] px-4 py-14 md:px-10">
        <h1 className="display display-md text-center">{tr("Plans for businesses")}</h1>
        <p className="mx-auto mt-2 max-w-xl text-center text-muted-foreground">{tr("Start free. Move to a monthly plan when you need more customers: the higher the plan, the lower the commission.")}</p>

        <section aria-labelledby="who-h" className="mt-10">
          <h2 id="who-h" className="text-center text-lg font-bold text-obsidian">{tr("What describes your business?")}</h2>
          <div className="mx-auto mt-4 grid max-w-3xl gap-3 sm:grid-cols-2" role="radiogroup" aria-labelledby="who-h">
            {AUDIENCES.filter((a) => (a.key === "seller" ? offers.selling : offers.installation)).map((a) => (
              <button key={a.key} type="button" role="radio" aria-checked={audience === a.key} onClick={() => setAudience(a.key)}
                className={`flex items-start gap-4 rounded-[24px] border p-5 text-start transition ${audience === a.key ? "border-[#2f6bff] bg-white ring-2 ring-[#2f6bff]/25" : "border-fog bg-white hover:border-[#2f6bff]/50"}`}>
                <span className="text-3xl" aria-hidden="true">{a.icon}</span>
                <span>
                  <span className="block text-lg font-black tracking-tight text-obsidian">{tr(a.title)}</span>
                  <span className="mt-1 block text-sm text-slate">{tr(a.text)}</span>
                </span>
              </button>
            ))}
          </div>
        </section>

        {current && (
          <section aria-labelledby="plans-h" className="mt-12" data-testid="pricing-plans">
            <h2 id="plans-h" className="text-center text-lg font-bold text-obsidian">{tr("Choose a plan")}</h2>
            <div className="mt-4 grid gap-4 md:grid-cols-3">
              {plans.map((p) => (
                <article key={p.key} className={`flex flex-col rounded-[28px] border bg-white p-7 ${popular === p.key ? "border-[#2f6bff] ring-2 ring-[#2f6bff]/25" : "border-fog"}`}>
                  {popular === p.key && <p className="mb-2 text-xs font-bold text-[#2f6bff]">{tr("Most chosen")}</p>}
                  <h3 className="text-xl font-black tracking-tight text-obsidian">{tr(p.name)}</h3>
                  <p className="mt-3 text-3xl font-black text-obsidian">
                    {p.price_monthly > 0 ? <>{formatNumber(p.price_monthly)} <span className="text-base font-semibold">{p.currency}</span><span className="text-sm font-medium text-slate"> / {tr("month")}</span></> : tr("Free")}
                  </p>
                  <ul className="mt-4 flex-1 space-y-2 text-sm text-slate">
                    <li>{p.customer_limit === null ? tr("No limit on customers a month") : tr("Up to {n} customers a month", { n: formatNumber(p.customer_limit) })}</li>
                    <li>{tr("Platform commission {p}%", { p: formatNumber(p.commission_percentage) })}</li>
                    <li>{tr("{n} team accounts", { n: formatNumber(p.team_seats) })}</li>
                  </ul>
                  <Link href={`${current.signup}?plan=${encodeURIComponent(p.key)}${store && current.key === "partner" ? `&store=${encodeURIComponent(store)}` : ""}`} className="btn-brand mt-6 block px-5 py-3 text-center" data-testid={`choose-${p.key}`}>
                    {p.price_monthly > 0 ? tr("Choose {name}", { name: tr(p.name) }) : tr("Start free")}
                  </Link>
                </article>
              ))}
            </div>
            <p className="mt-4 text-center text-xs text-muted-foreground">
              {tr("You fill in the application next. Your plan starts when we approve your documents.")}
              {(terms?.customer_fee_percentage ?? 0) > 0 && <> {tr("Customers pay a platform fee of {n}% on top of your price. It is not taken from you.", { n: formatNumber(terms?.customer_fee_percentage ?? 0) })}</>}
            </p>
          </section>
        )}
      </main>
    </PublicShell>
  );
}
