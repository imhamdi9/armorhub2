"use client";

import { useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDate, formatMoney, formatNumber, tr } from "@/lib/i18n";
import { downloadExport } from "@/services/admin.service";
import { getKpis, type Kpis } from "@/services/kpi.service";
import { LoadingBlock } from "@/components/ui/loading-block";

const money = (m: Record<string, number | null>) => {
  const rows = Object.entries(m).filter(([, v]) => v !== null) as [string, number][];
  return rows.length ? rows.map(([cur, v]) => formatMoney(v, cur)).join(" · ") : "—";
};
const pct = (v: number | null) => (v === null ? "—" : `${v}%`);

/** Change of a per-currency figure: shown only when there is exactly one currency, so two currencies are never mixed into one percentage. */
function sameCurrencyChange(now: Record<string, number | null>, before?: Record<string, number | null>) {
  const cur = Object.keys(now);

  return cur.length === 1 && before ? change(now[cur[0]], before[cur[0]] ?? 0) : null;
}

/** How much a figure moved from the period before: "+12%" in green, "-8%" in red, nothing when there is nothing to compare with. */
function change(now: number | null | undefined, before: number | null | undefined): { text: string; good: boolean } | null {
  if (now == null || before == null) return null;
  if (before === 0) return now === 0 ? null : { text: tr("New"), good: true };
  const p = Math.round(((now - before) / Math.abs(before)) * 100);
  if (p === 0) return { text: "0%", good: true };

  return { text: `${p > 0 ? "+" : ""}${p}%`, good: p > 0 };
}

function Tile({ label, value, testId, delta }: { label: string; value: string; testId?: string; delta?: { text: string; good: boolean } | null }) {
  return (
    <div className="rounded-lg border p-4" data-testid={testId}>
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-xl font-bold">{value}</p>
      {delta && <p className={`mt-1 text-xs font-medium ${delta.good ? "text-emerald-700" : "text-rose-700"}`} data-testid="kpi-delta">{delta.text} <span className="font-normal text-muted-foreground">{tr("vs the period before")}</span></p>}
    </div>
  );
}

/** The days of the period as a line per currency, with the highest day marked. Drawn here: no chart library is needed for one line. */
function Trend({ days, values, currency }: { days: string[]; values: number[]; currency: string }) {
  const w = 640, h = 120, pad = 6;
  const max = Math.max(...values, 1);
  const x = (i: number) => pad + (days.length <= 1 ? 0 : (i / (days.length - 1)) * (w - pad * 2));
  const y = (v: number) => h - pad - (v / max) * (h - pad * 2);
  const path = values.map((v, i) => `${i === 0 ? "M" : "L"}${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(" ");
  const top = values.indexOf(max);

  return (
    <figure className="rounded-lg border p-4" data-testid={`trend-${currency}`}>
      <figcaption className="mb-2 flex flex-wrap items-baseline justify-between gap-2 text-sm">
        <span className="font-medium">{tr("Money handled per day")} · {currency}</span>
        <span className="text-muted-foreground">{tr("Best day")}: {formatDate(days[top])} · {formatMoney(max, currency)}</span>
      </figcaption>
      <svg viewBox={`0 0 ${w} ${h}`} className="h-32 w-full" role="img" aria-label={`${tr("Money handled per day")} ${currency}`} preserveAspectRatio="none">
        <path d={`${path} L${x(values.length - 1)} ${h - pad} L${x(0)} ${h - pad} Z`} fill="#FFD21F" opacity="0.25" />
        <path d={path} fill="none" stroke="#1a1a1a" strokeWidth="2" vectorEffect="non-scaling-stroke" />
        <circle cx={x(top)} cy={y(max)} r="4" fill="#1a1a1a" />
      </svg>
      <div className="mt-1 flex justify-between text-xs text-muted-foreground"><span>{formatDate(days[0])}</span><span>{formatDate(days[days.length - 1])}</span></div>
    </figure>
  );
}

const PILOT_LABELS: Record<keyof Kpis["pilot"], string> = {
  centres: "Active service centres",
  sellers: "Active sellers",
  requests_per_month: "Real requests in 30 days",
  answered_share: "Requests with 2 or more offers (%)",
  executed_share: "Requests carried out (%)",
};

export default function KpisPage() {
  const [days, setDays] = useState(30);
  const [kpis, setKpis] = useState<Kpis | null>(null);
  const [exportError, setExportError] = useState<string | null>(null);

  useEffect(() => {
    const to = new Date();
    const from = new Date(to.getTime() - (days - 1) * 86400000);
    const iso = (d: Date) => d.toISOString().slice(0, 10);
    setKpis(null);
    getKpis({ from: iso(from), to: iso(to) }).then(setKpis).catch(() => setKpis(null));
  }, [days]);

  const range = () => {
    const to = new Date();
    const from = new Date(to.getTime() - (days - 1) * 86400000);

    return { from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) };
  };

  return (
    <div>
      <PageHeader
        title={tr("Business numbers")}
        action={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => { setExportError(null); downloadExport("orders", range()).catch((e) => setExportError(errorMessage(e))); }}>{tr("Orders (CSV)")}</Button>
            <Button variant="outline" onClick={() => { setExportError(null); downloadExport("payments", range()).catch((e) => setExportError(errorMessage(e))); }}>{tr("Payments (CSV)")}</Button>
          </div>
        }
      />
      {exportError && <p className="mb-3 text-sm text-red-500" role="alert">{exportError}</p>}
      <div className="mb-4 flex gap-2">
        {[7, 30, 90].map((d) => (
          <button key={d} onClick={() => setDays(d)} className={`rounded-full border px-4 py-1.5 text-sm ${days === d ? "bg-primary text-primary-foreground" : ""}`}>
            {d} {tr("days")}
          </button>
        ))}
      </div>

      {!kpis ? (
        <LoadingBlock />
      ) : (
        <div className="space-y-6">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Tile label={tr("Money handled (GMV)")} value={money(kpis.gmv.total)} testId="kpi-gmv" delta={sameCurrencyChange(kpis.gmv.total, kpis.previous?.gmv.total)} />
            <Tile label={tr("Platform revenue")} value={money(kpis.net_revenue.total)} delta={sameCurrencyChange(kpis.net_revenue.total, kpis.previous?.net_revenue.total)} />
            <Tile label={tr("Share the platform keeps")} value={Object.entries(kpis.take_rate).map(([cur, v]) => `${cur} ${pct(v)}`).join(" · ") || "—"} />
            <Tile label={tr("Average purchase")} value={money(kpis.average_order_value)} delta={sameCurrencyChange(kpis.average_order_value, kpis.previous?.average_order_value)} />
            <Tile label={tr("Sellers who sold")} value={formatNumber(kpis.active.sellers)} />
            <Tile label={tr("Centres who worked")} value={formatNumber(kpis.active.centres)} />
            <Tile label={tr("Requests answered")} value={pct(kpis.rfq.response_rate)} delta={change(kpis.rfq.response_rate, kpis.previous?.rfq.response_rate)} />
            <Tile label={tr("Quotes accepted")} value={pct(kpis.rfq.quote_conversion)} delta={change(kpis.rfq.quote_conversion, kpis.previous?.rfq.quote_conversion)} />
            <Tile label={tr("Paid orders finished")} value={pct(kpis.orders.completion_rate)} delta={change(kpis.orders.completion_rate, kpis.previous?.orders.completion_rate)} />
            <Tile label={tr("Customers who bought again")} value={pct(kpis.repeat_purchase_rate)} />
          </div>

          {kpis.series && Object.entries(kpis.series.by_currency).map(([cur, values]) => <Trend key={cur} days={kpis.series!.days} values={values} currency={cur} />)}

          <div>
            <h2 className="mb-2 text-lg font-semibold">{tr("Does the pilot work?")}</h2>
            <div className="divide-y rounded-lg border">
              {(Object.keys(kpis.pilot) as (keyof Kpis["pilot"])[]).map((k) => (
                <div key={k} className="flex items-center justify-between gap-3 p-3">
                  <p>{tr(PILOT_LABELS[k])}</p>
                  <p className="flex items-center gap-3">
                    <span className="font-medium">{kpis.pilot[k].value ?? "—"} / {kpis.pilot[k].target}</span>
                    <Badge variant={kpis.pilot[k].met ? "default" : "secondary"}>{kpis.pilot[k].met ? tr("Reached") : tr("Not yet")}</Badge>
                  </p>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
