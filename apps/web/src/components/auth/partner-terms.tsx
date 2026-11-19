"use client";

import { useEffect, useState } from "react";

import { formatNumber, tr } from "@/lib/i18n";
import { getPublicTerms, type PublicTerms } from "@/services/auth.service";

const EXAMPLE = 1000;

/**
 * What a business wants to know before it uploads a single document: how it gets paid, what the commission is and how it is shared,
 * what it will need, and what happens next. The numbers come from the live settings, never from copy that could go stale.
 */
export function PartnerTerms() {
  const [t, setT] = useState<PublicTerms | null>(null);

  useEffect(() => {
    getPublicTerms().then(setT).catch(() => setT(null));
  }, []);

  const pct = t?.commission_percentage ?? null;
  const commission = pct === null ? null : (EXAMPLE * pct) / 100;
  const share = (k: "platform" | "company" | "partner") => (t && commission !== null ? commission * t.split[k] : null);
  const money = (n: number | null) => (n === null ? "" : formatNumber(n, { maximumFractionDigits: 2 }));

  return (
    <div className="mb-6 space-y-4 text-start" data-testid="partner-terms">
      {t && (t.open_requests > 0 || t.verified_partners > 0) && (
        <p className="rounded-xl bg-accent p-3 text-sm font-semibold text-accent-foreground" data-testid="terms-counts">
          {t.open_requests > 0 && tr("{n} requests are waiting for quotes right now.", { n: formatNumber(t.open_requests) })}{" "}
          {t.verified_partners > 0 && tr("{n} verified partners are active.", { n: formatNumber(t.verified_partners) })}
        </p>
      )}

      <section className="rounded-xl border bg-white p-4" aria-labelledby="how-paid">
        <h2 id="how-paid" className="font-display text-lg font-semibold">{tr("How you get paid")}</h2>
        <ul className="mt-2 space-y-1.5 text-sm text-muted-foreground">
          <li>{tr("Joining is free. There is no monthly fee.")}</li>
          <li>{tr("The customer pays inside the app and the money is held until the job is done, so you are not left chasing payment.")}</li>
          {pct !== null && <li>{tr("ArmorHub takes a commission of {p}% of each paid order.", { p: formatNumber(pct) })}</li>}
        </ul>
        {t && commission !== null && (
          <table className="mt-3 w-full text-sm" data-testid="commission-example">
            <caption className="mb-1 text-start text-xs text-muted-foreground">{tr("Example: a job paid at {amount}", { amount: formatNumber(EXAMPLE) })}</caption>
            <tbody>
              <tr className="border-t"><td className="py-1.5">{tr("Commission ({p}%)", { p: formatNumber(pct ?? 0) })}</td><td className="py-1.5 text-end font-semibold">{money(commission)}</td></tr>
              <tr className="border-t"><td className="py-1.5 ps-4 text-muted-foreground">{tr("Kept by the platform")} ({formatNumber(t.split.platform * 100)}%)</td><td className="py-1.5 text-end">{money(share("platform"))}</td></tr>
              <tr className="border-t"><td className="py-1.5 ps-4 text-muted-foreground">{tr("Goes to the store that hosts you")} ({formatNumber(t.split.company * 100)}%)</td><td className="py-1.5 text-end">{money(share("company"))}</td></tr>
              <tr className="border-t"><td className="py-1.5 ps-4 text-muted-foreground">{tr("Goes back to you")} ({formatNumber(t.split.partner * 100)}%)</td><td className="py-1.5 text-end">{money(share("partner"))}</td></tr>
            </tbody>
          </table>
        )}
      </section>

      {t?.plans && t.plans.length > 0 && (
        <section className="rounded-xl border bg-white p-4" aria-labelledby="plans-h" data-testid="terms-plans">
          <h2 id="plans-h" className="font-display text-lg font-semibold">{tr("Plans")}</h2>
          <p className="mt-1 text-sm text-muted-foreground">{tr("Start free. Choose a monthly plan when you need more customers: the higher the plan, the lower the commission.")}</p>
          <table className="mt-3 w-full text-sm">
            <thead><tr className="text-start text-xs text-muted-foreground"><th className="py-1 text-start font-medium">{tr("Plan")}</th><th className="py-1 text-start font-medium">{tr("Customers a month")}</th><th className="py-1 text-start font-medium">{tr("Commission (%)")}</th><th className="py-1 text-end font-medium">{tr("Price per month")}</th></tr></thead>
            <tbody>
              {t.plans.map((p) => (
                <tr key={p.key} className="border-t"><td className="py-1.5 font-semibold">{tr(p.name)}</td><td className="py-1.5">{p.customer_limit === null ? tr("no limit") : formatNumber(p.customer_limit)}</td><td className="py-1.5">{formatNumber(p.commission_percentage)}%</td><td className="py-1.5 text-end">{p.price_monthly > 0 ? `${formatNumber(p.price_monthly)} ${p.currency}` : tr("Free")}</td></tr>
              ))}
            </tbody>
          </table>
          {(t.customer_fee_percentage ?? 0) > 0 && <p className="mt-2 text-xs text-muted-foreground">{tr("Customers pay a platform fee of {n}% on top of your price. It is not taken from you.", { n: formatNumber(t.customer_fee_percentage ?? 0) })}</p>}
        </section>
      )}

      <section className="rounded-xl border bg-white p-4" aria-labelledby="what-next">
        <h2 id="what-next" className="font-display text-lg font-semibold">{tr("What you need and what happens next")}</h2>
        <ol className="mt-2 list-decimal space-y-1.5 ps-5 text-sm text-muted-foreground">
          <li>{tr("Fill in this form and upload your ID and commercial registration. It takes one sitting.")}</li>
          <li>{tr("A reviewer checks your documents. Once you are approved you can sign in.")}</li>
          <li>{tr("You then see the request inbox and send quotes. Customers pick a quote and pay in the app.")}</li>
        </ol>
        <p className="mt-2 text-xs text-muted-foreground">{tr("Only the name, city, description and logo are shown. Phone, address and location stay private.")}</p>
      </section>
    </div>
  );
}
