"use client";

import { useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDate, formatMoney, formatNumber, tr } from "@/lib/i18n";
import { cancelSubscription, chooseSubscription, getSubscription, type SubscriptionView } from "@/services/platform.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** A partner's or seller's own plan: what it includes, how much of the month is used, and the other plans to move to. */
export default function SubscriptionPage() {
  const [view, setView] = useState<SubscriptionView | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    getSubscription().then(setView).catch((e) => setError(errorMessage(e)));
  }, []);

  async function run(key: string, fn: () => Promise<SubscriptionView>) {
    setBusy(key);
    setError(null);
    try {
      setView(await fn());
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setBusy(null);
    }
  }

  if (error && !view) return <p role="alert" className="text-red-600">{error}</p>;
  if (!view) return <LoadingBlock />;

  const { plan, usage } = view;
  const pct = usage.limit ? Math.min(100, Math.round((usage.customers / usage.limit) * 100)) : 0;

  return (
    <div>
      <PageHeader title="Subscription" />
      {error && <p role="alert" className="mb-4 text-sm text-red-600">{error}</p>}

      <section className="mb-6 rounded-2xl border bg-white p-5 shadow-card" aria-labelledby="current-plan" data-testid="current-plan">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="text-sm text-muted-foreground">{tr("Your plan")}</p>
            <h2 id="current-plan" className="font-display text-2xl font-bold">{tr(plan.name)}</h2>
          </div>
          <p className="text-lg font-semibold">{plan.price_monthly > 0 ? `${formatMoney(plan.price_monthly, plan.currency)} / ${tr("month")}` : tr("Free")}</p>
        </div>
        <div className="mt-4">
          <div className="mb-1 flex justify-between text-sm">
            <span>{tr("Customers this month")}</span>
            <strong>{formatNumber(usage.customers)}{usage.limit !== null ? ` / ${formatNumber(usage.limit)}` : ` (${tr("no limit")})`}</strong>
          </div>
          {usage.limit !== null && (
            <div className="h-2.5 overflow-hidden rounded-full bg-fog" role="progressbar" aria-label={tr("Customers this month")} aria-valuenow={usage.customers} aria-valuemin={0} aria-valuemax={usage.limit}>
              <div className={`h-full rounded-full ${usage.reached ? "bg-red-500" : "bg-[#FFD21F]"}`} style={{ width: `${pct}%` }} />
            </div>
          )}
          {usage.reached && <p role="status" className="mt-2 text-sm text-red-600">{tr("You have reached this month's customer limit. Upgrade to keep sending quotes.")}</p>}
        </div>
        <p className="mt-3 text-sm text-muted-foreground">
          {tr("Commission on your orders")}: <strong>{formatNumber(plan.commission_percentage)}%</strong>
          {view.period_end && <> · {view.auto_renew ? tr("Renews on {date}", { date: formatDate(view.period_end) }) : tr("Ends on {date}", { date: formatDate(view.period_end) })}</>}
        </p>
        {plan.key !== "free" && view.auto_renew && (
          <Button variant="outline" size="sm" className="mt-3" disabled={busy !== null} onClick={() => run("cancel", cancelSubscription)}>{tr("Stop renewing")}</Button>
        )}
      </section>

      <div className="grid gap-4 md:grid-cols-3" data-testid="plans">
        {view.plans.map((p) => {
          const current = p.key === plan.key;
          return (
            <section key={p.key} className={`flex flex-col rounded-2xl border bg-white p-5 shadow-card ${current ? "border-[#E0B400] ring-2 ring-[#E0B400]/20" : ""}`} aria-label={tr(p.name)}>
              <h3 className="font-display text-xl font-bold">{tr(p.name)}</h3>
              <p className="mt-1 text-3xl font-extrabold tracking-tight">{p.price_monthly > 0 ? formatMoney(p.price_monthly, p.currency) : tr("Free")}{p.price_monthly > 0 && <span className="text-sm font-medium text-muted-foreground"> / {tr("month")}</span>}</p>
              <ul className="my-4 space-y-1.5 text-sm">
                <li>✓ {p.customer_limit === null ? tr("No limit on customers") : tr("{n} customers a month", { n: formatNumber(p.customer_limit) })}</li>
                <li>✓ {tr("Commission {n}%", { n: formatNumber(p.commission_percentage) })}</li>
                <li>✓ {tr("{n} team accounts", { n: formatNumber(p.team_seats) })}</li>
              </ul>
              <Button className="mt-auto" variant={current ? "outline" : "default"} disabled={current || busy !== null} onClick={() => run(p.key, () => chooseSubscription(p.key))}>
                {current ? tr("Current plan") : p.price_monthly > (plan.price_monthly ?? 0) ? tr("Upgrade") : tr("Switch to this plan")}
              </Button>
            </section>
          );
        })}
      </div>
      <p className="mt-3 text-xs text-muted-foreground">{tr("Paid plans are charged through the platform's payment provider. Prices are per month and plans renew each month until you stop them.")}</p>

      {view.invoices.length > 0 && (
        <section className="mt-6 rounded-2xl border bg-white p-5 shadow-card" aria-labelledby="inv">
          <h2 id="inv" className="font-display mb-2 text-xl font-semibold">{tr("Invoices")}</h2>
          <ul className="divide-y text-sm">
            {view.invoices.map((i) => (
              <li key={i.id} className="flex justify-between py-2"><span>{tr(i.plan_key)} · {formatDate(i.period_start)} - {formatDate(i.period_end)}</span><strong>{formatMoney(i.amount, i.currency)}</strong></li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}
