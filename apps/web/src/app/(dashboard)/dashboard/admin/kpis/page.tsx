"use client";

import { useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatMoney, tr } from "@/lib/i18n";
import { getKpis, type Kpis } from "@/services/kpi.service";
import { LoadingBlock } from "@/components/ui/loading-block";

const money = (m: Record<string, number | null>) => {
  const rows = Object.entries(m).filter(([, v]) => v !== null) as [string, number][];
  return rows.length ? rows.map(([cur, v]) => formatMoney(v, cur)).join(" · ") : "—";
};
const pct = (v: number | null) => (v === null ? "—" : `${v}%`);

function Tile({ label, value, testId }: { label: string; value: string; testId?: string }) {
  return (
    <div className="rounded-lg border p-4" data-testid={testId}>
      <p className="text-sm text-muted-foreground">{label}</p>
      <p className="mt-1 text-xl font-bold">{value}</p>
    </div>
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

  useEffect(() => {
    const to = new Date();
    const from = new Date(to.getTime() - (days - 1) * 86400000);
    const iso = (d: Date) => d.toISOString().slice(0, 10);
    getKpis({ from: iso(from), to: iso(to) }).then(setKpis).catch(() => setKpis(null));
  }, [days]);

  return (
    <div>
      <PageHeader title={tr("Business numbers")} />
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
            <Tile label={tr("Money handled (GMV)")} value={money(kpis.gmv.total)} testId="kpi-gmv" />
            <Tile label={tr("Platform revenue")} value={money(kpis.net_revenue.total)} />
            <Tile label={tr("Share the platform keeps")} value={Object.entries(kpis.take_rate).map(([cur, v]) => `${cur} ${pct(v)}`).join(" · ") || "—"} />
            <Tile label={tr("Average purchase")} value={money(kpis.average_order_value)} />
            <Tile label={tr("Sellers who sold")} value={String(kpis.active.sellers)} />
            <Tile label={tr("Centres who worked")} value={String(kpis.active.centres)} />
            <Tile label={tr("Requests answered")} value={pct(kpis.rfq.response_rate)} />
            <Tile label={tr("Quotes accepted")} value={pct(kpis.rfq.quote_conversion)} />
            <Tile label={tr("Paid orders finished")} value={pct(kpis.orders.completion_rate)} />
            <Tile label={tr("Customers who bought again")} value={pct(kpis.repeat_purchase_rate)} />
          </div>

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
