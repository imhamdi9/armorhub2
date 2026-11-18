"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { deleteZone, getDemand, getZones, saveZone, type Zone } from "@/services/ops.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** Where each market is open. A zone starts closed and is opened once it has trusted centres in it. */
export default function ZonesPage() {
  const [zones, setZones] = useState<Zone[] | null>(null);
  const [demand, setDemand] = useState<{ market: string; city: string; customers: number }[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const load = () => { getZones().then(setZones).catch(() => setZones([])); getDemand().then(setDemand).catch(() => {}); };
  useEffect(() => { load(); }, []);

  async function run(fn: () => Promise<unknown>) {
    setErr(null);
    try { await fn(); load(); } catch (e) { setErr(errorMessage(e)); }
  }

  return (
    <div className="space-y-8">
      <PageHeader title={tr("Service zones")} />
      {err && <Note tone="warn">{err}</Note>}
      {zones === null ? <LoadingBlock /> : zones.length === 0 ? <EmptyState>{tr("No zones yet. Until you add one, the market is open everywhere.")}</EmptyState> : (
        <ul className="divide-y rounded-xl border" data-testid="zones">
          {zones.map((z) => (
            <li key={z.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
              <span className="font-medium">{z.market} · {z.name_en}</span>
              <span className="text-muted-foreground">{z.radius_km} km · {z.centres} {tr("centres")}</span>
              <span className="flex items-center gap-2">
                <Badge variant={z.is_active ? "default" : "secondary"}>{z.is_active ? tr("Open") : tr("Closed")}</Badge>
                <Button size="sm" variant="outline" onClick={() => run(() => saveZone({ is_active: !z.is_active }, z.id))}>{z.is_active ? tr("Close") : tr("Open")}</Button>
                <Button size="sm" variant="outline" onClick={() => run(() => deleteZone(z.id))}>{tr("Remove")}</Button>
              </span>
            </li>
          ))}
        </ul>
      )}

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Add a zone")}</h2>
        <form
          className="grid gap-3 rounded-xl border p-4 md:grid-cols-3"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            const form = e.currentTarget;
            run(() => saveZone({ market: String(f.get("market")), key: String(f.get("key")), name_en: String(f.get("name")), city: String(f.get("city") || "") || undefined, latitude: Number(f.get("lat")), longitude: Number(f.get("lng")), radius_km: Number(f.get("radius")) })).then(() => form.reset());
          }}
        >
          <input name="market" className={field} placeholder={tr("Country code, e.g. EG")} maxLength={2} required />
          <input name="key" className={field} placeholder={tr("Short key, e.g. maadi")} required />
          <input name="name" className={field} placeholder={tr("Name")} required />
          <input name="city" className={field} placeholder={tr("City")} />
          <input name="lat" type="number" step="any" className={field} placeholder={tr("Latitude")} required />
          <input name="lng" type="number" step="any" className={field} placeholder={tr("Longitude")} required />
          <input name="radius" type="number" min={1} max={200} defaultValue={10} className={field} required />
          <div><Button type="submit">{tr("Add zone")}</Button></div>
        </form>
      </section>

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Where customers asked outside the open zones")}</h2>
        {demand.length === 0 ? <p className="text-sm text-muted-foreground">{tr("Nobody yet.")}</p> : (
          <ul className="divide-y rounded-xl border">{demand.map((d) => <li key={d.market + d.city} className="flex justify-between p-3 text-sm"><span>{d.market} · {d.city}</span><span>{d.customers}</span></li>)}</ul>
        )}
      </section>
    </div>
  );
}
