"use client";

import { useMemo, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export interface Sku {
  sku: string; name: string; supplier: string;
  onHand: number; forecastPerWeek: number; inbound: number; leadDays: number; unitCost: number;
  /** Demand in units for the last eight weeks, oldest first. */
  demand: number[];
}
export interface Supplier { name: string; onTime: number }

type Risk = "Critical" | "High" | "Medium" | "Low";
const RISK_STYLE: Record<Risk, string> = { Critical: "bg-rose-50 text-rose-700 ring-rose-200", High: "bg-orange-50 text-orange-700 ring-orange-200", Medium: "bg-amber-50 text-amber-700 ring-amber-200", Low: "bg-emerald-50 text-emerald-700 ring-emerald-200" };
const RISK_ORDER: Risk[] = ["Critical", "High", "Medium", "Low"];
const money = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", maximumFractionDigits: 0 });
const num = new Intl.NumberFormat("en-US");

/** Everything the table and the panel show is derived from the raw SKU numbers, so the two can never disagree. */
export function derive(s: Sku) {
  const daily = s.forecastPerWeek / 7;
  const cover = daily > 0 ? (s.onHand + s.inbound) / daily : Infinity;
  const coverNow = daily > 0 ? s.onHand / daily : Infinity;
  const risk: Risk = coverNow < s.leadDays ? "Critical" : coverNow < s.leadDays + 7 ? "High" : coverNow < s.leadDays + 14 ? "Medium" : "Low";
  // Order enough to cover the lead time plus two weeks of safety, less what is on hand or on its way.
  const recommended = Math.max(0, Math.ceil(daily * (s.leadDays + 14) - s.onHand - s.inbound));

  return { cover, coverNow, risk, recommended };
}

function Metric({ label, value, hint }: { label: string; value: string; hint: string }) {
  return (
    <article className="rounded-xl border border-neutral-200 bg-white p-4">
      <p className="text-sm text-neutral-500">{label}</p>
      <p className="mt-1 text-2xl font-semibold tracking-tight text-neutral-900 tabular-nums">{value}</p>
      <p className="mt-1 text-xs text-neutral-400">{hint}</p>
    </article>
  );
}

/** A replenishment cockpit: headline metrics, a priority table of SKUs and a detail panel for the selected one. */
export function InventoryCockpit({ skus, suppliers }: { skus: Sku[]; suppliers: Supplier[] }) {
  const rows = useMemo(() => skus.map((s) => ({ s, ...derive(s) })).sort((a, b) => RISK_ORDER.indexOf(a.risk) - RISK_ORDER.indexOf(b.risk) || a.coverNow - b.coverNow), [skus]);
  const [selectedSku, setSelectedSku] = useState(rows[0]?.s.sku ?? "");
  const [created, setCreated] = useState<Record<string, number>>({});
  const sel = rows.find((r) => r.s.sku === selectedSku) ?? rows[0];

  const avgCover = rows.reduce((t, r) => t + Math.min(r.cover, 999), 0) / Math.max(rows.length, 1);
  const atRisk = rows.filter((r) => r.risk === "Critical" || r.risk === "High").length;
  const openPo = skus.reduce((t, s) => t + s.inbound * s.unitCost, 0);
  const reliability = suppliers.reduce((t, s) => t + s.onTime, 0) / Math.max(suppliers.length, 1);

  const maxDemand = sel ? Math.max(...sel.s.demand, sel.s.forecastPerWeek) : 1;

  return (
    <div className="mx-auto w-full max-w-7xl space-y-6 px-4 py-8 sm:px-6">
      <header>
        <h1 className="text-xl font-semibold tracking-tight text-neutral-900">Replenishment</h1>
        <p className="mt-0.5 text-sm text-neutral-500">What to order, ranked by how soon it runs out.</p>
      </header>

      <section aria-label="Headline metrics" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Metric label="Days of cover" value={`${avgCover.toFixed(0)} days`} hint="Average, including stock on its way" />
        <Metric label="Stockout risk" value={`${atRisk} of ${rows.length} SKUs`} hint="Critical or high" />
        <Metric label="Open PO value" value={money.format(openPo)} hint="Inbound at cost" />
        <Metric label="Supplier reliability" value={`${reliability.toFixed(0)}%`} hint="On-time deliveries, 90 days" />
      </section>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section aria-labelledby="priority-title" className="min-w-0 overflow-hidden rounded-2xl border border-neutral-200 bg-white">
          <h2 id="priority-title" className="border-b border-neutral-200 px-5 py-3 text-sm font-semibold text-neutral-900">Priority SKUs</h2>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[44rem] text-sm">
              <thead className="bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
                <tr>
                  <th scope="col" className="px-5 py-2.5 text-start font-medium">SKU</th>
                  <th scope="col" className="px-3 py-2.5 text-end font-medium">Forecast / wk</th>
                  <th scope="col" className="px-3 py-2.5 text-end font-medium">Inbound</th>
                  <th scope="col" className="px-3 py-2.5 text-end font-medium">Lead time</th>
                  <th scope="col" className="px-3 py-2.5 text-end font-medium">Order</th>
                  <th scope="col" className="px-5 py-2.5 text-start font-medium">Risk</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100">
                {rows.map((r) => (
                  <tr key={r.s.sku} data-sku={r.s.sku} tabIndex={0} aria-selected={r.s.sku === sel?.s.sku}
                    onClick={() => setSelectedSku(r.s.sku)}
                    onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); setSelectedSku(r.s.sku); } }}
                    className={`cursor-pointer transition-colors hover:bg-neutral-50 ${r.s.sku === sel?.s.sku ? "bg-neutral-100 hover:bg-neutral-100" : ""} ${focus} focus-visible:ring-inset`}>
                    <td className="px-5 py-3"><p className="font-medium text-neutral-900">{r.s.name}</p><p className="text-xs text-neutral-500">{r.s.sku}</p></td>
                    <td className="px-3 py-3 text-end tabular-nums text-neutral-700">{num.format(r.s.forecastPerWeek)}</td>
                    <td className="px-3 py-3 text-end tabular-nums text-neutral-700">{num.format(r.s.inbound)}</td>
                    <td className="px-3 py-3 text-end tabular-nums text-neutral-700">{r.s.leadDays} d</td>
                    <td className="px-3 py-3 text-end font-medium tabular-nums text-neutral-900">{r.recommended > 0 ? num.format(r.recommended) : "—"}</td>
                    <td className="px-5 py-3"><span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${RISK_STYLE[r.risk]}`}>{r.risk}</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        {sel && (
          <aside data-detail aria-label="Selected item" aria-live="polite" className="h-fit rounded-2xl border border-neutral-200 bg-white p-5 lg:sticky lg:top-6">
            <p className="text-xs text-neutral-500">{sel.s.sku}</p>
            <h2 data-detail-name className="mt-0.5 text-base font-semibold text-neutral-900">{sel.s.name}</h2>
            <p className="text-sm text-neutral-500">Supplied by {sel.s.supplier}</p>

            <dl className="mt-4 grid grid-cols-3 gap-3 text-sm">
              <div><dt className="text-xs text-neutral-500">On hand</dt><dd className="font-semibold tabular-nums text-neutral-900">{num.format(sel.s.onHand)}</dd></div>
              <div><dt className="text-xs text-neutral-500">Cover now</dt><dd className="font-semibold tabular-nums text-neutral-900">{Number.isFinite(sel.coverNow) ? `${sel.coverNow.toFixed(0)} d` : "—"}</dd></div>
              <div><dt className="text-xs text-neutral-500">Lead time</dt><dd className="font-semibold tabular-nums text-neutral-900">{sel.s.leadDays} d</dd></div>
            </dl>

            <div className="mt-5">
              <p className="text-xs font-medium text-neutral-500">Demand, last 8 weeks (units)</p>
              <div className="mt-2 flex h-24 items-end gap-1.5" role="img" aria-label={`Weekly demand: ${sel.s.demand.join(", ")}`} data-demand-bars>
                {sel.s.demand.map((d, i) => <span key={i} title={`${d} units`} style={{ height: `${Math.max((d / maxDemand) * 100, 4)}%` }} className="flex-1 rounded-sm bg-neutral-300" />)}
                <span title={`Forecast ${sel.s.forecastPerWeek} units`} style={{ height: `${(sel.s.forecastPerWeek / maxDemand) * 100}%` }} className="flex-1 rounded-sm bg-neutral-900" />
              </div>
              <p className="mt-1 text-xs text-neutral-400">The dark bar is next week&rsquo;s forecast.</p>
            </div>

            <div className="mt-5 rounded-xl bg-neutral-50 p-3 text-sm">
              <p className="text-neutral-500">Recommended order</p>
              <p data-detail-order className="text-lg font-semibold tabular-nums text-neutral-900">{sel.recommended > 0 ? `${num.format(sel.recommended)} units · ${money.format(sel.recommended * sel.s.unitCost)}` : "Nothing needed"}</p>
            </div>

            <button type="button" disabled={sel.recommended === 0 || created[sel.s.sku] !== undefined}
              onClick={() => setCreated((c) => ({ ...c, [sel.s.sku]: sel.recommended }))}
              className={`mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 ${focus}`}>
              Create purchase order
            </button>
            {created[sel.s.sku] !== undefined && <p role="status" className="mt-2 text-xs text-emerald-700">Draft purchase order for {num.format(created[sel.s.sku])} units created.</p>}
          </aside>
        )}
      </div>
    </div>
  );
}
