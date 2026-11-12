"use client";

import { useCallback, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { announceInbound, cancelInbound, getSellerInbound, getSellerStock, type InboundRow, type SellerStock } from "@/services/platform.service";

/** A seller's goods kept by ArmorHub: what is in stock, and the cartons it sends to the warehouse. */
export default function SellerWarehousePage() {
  const [stock, setStock] = useState<SellerStock | null>(null);
  const [inbound, setInbound] = useState<InboundRow[]>([]);
  const [cartons, setCartons] = useState<Record<number, string>>({});
  const [form, setForm] = useState({ carrier: "", tracking_number: "" });
  const [msg, setMsg] = useState<string | null>(null);

  const [tick, setTick] = useState(0);
  const load = useCallback(async () => setTick((t) => t + 1), []);
  useEffect(() => {
    let live = true;
    Promise.all([getSellerStock(), getSellerInbound()]).then(([s, i]) => { if (live) { setStock(s); setInbound(i); } }).catch((e) => { if (live) setMsg(errorMessage(e)); });
    return () => { live = false; };
  }, [tick]);

  async function announce(e: React.FormEvent) {
    e.preventDefault();
    setMsg(null);
    const items = Object.entries(cartons).filter(([, v]) => Number(v) > 0).map(([id, v]) => ({ product_id: Number(id), cartons: Number(v) }));
    if (items.length === 0) { setMsg(tr("Enter the number of cartons for at least one product.")); return; }
    try {
      await announceInbound({ items, carrier: form.carrier || undefined, tracking_number: form.tracking_number || undefined });
      setCartons({}); setForm({ carrier: "", tracking_number: "" }); setMsg(tr("Delivery announced. The warehouse will count it when it arrives.")); await load();
    } catch (err) { setMsg(errorMessage(err)); }
  }

  if (!stock) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;
  const stored = stock.products.filter((p) => p.fulfilment === "armorhub");

  return (
    <div className="space-y-8">
      <PageHeader title="Warehouse" />
      <p className="text-sm text-muted-foreground">
        {stock.warehouse ? tr("Your market's warehouse: {name}.", { name: stock.warehouse.name }) : tr("This market has no open warehouse yet.")}{" "}
        {tr("Choose ArmorHub stores and ships on a product to have it kept here. Until then you ship it yourself.")}
      </p>
      {msg && <p className="text-sm" role="status">{msg}</p>}

      <section aria-labelledby="sw-stock">
        <h2 id="sw-stock" className="mb-2 text-lg font-semibold">{tr("Your stock in the warehouse")}</h2>
        {stored.length === 0 ? <EmptyState>{tr("No product is stored by ArmorHub yet.")}</EmptyState> : (
          <table className="w-full text-sm" data-testid="seller-stock">
            <thead><tr><th className="py-1 text-start">{tr("Product")}</th><th className="text-start">SKU</th><th className="text-end">{tr("Units")}</th><th className="text-end">{tr("Cartons to send")}</th></tr></thead>
            <tbody>
              {stored.map((p) => (
                <tr key={p.id} className="border-t">
                  <td className="py-1">{p.name}</td><td><code>{p.sku}</code></td><td className="text-end">{p.units}</td>
                  <td className="text-end"><Input aria-label={`${tr("Cartons to send")} ${p.name}`} type="number" min="0" className="ms-auto w-24" value={cartons[p.id] ?? ""} onChange={(e) => setCartons({ ...cartons, [p.id]: e.target.value })} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        {stored.length > 0 && (
          <form onSubmit={announce} className="mt-3 flex flex-wrap items-end gap-2">
            <div><Label htmlFor="sw-carrier">{tr("Carrier")}</Label><Input id="sw-carrier" value={form.carrier} onChange={(e) => setForm({ ...form, carrier: e.target.value })} /></div>
            <div><Label htmlFor="sw-track">{tr("Tracking number")}</Label><Input id="sw-track" dir="ltr" value={form.tracking_number} onChange={(e) => setForm({ ...form, tracking_number: e.target.value })} /></div>
            <Button type="submit">{tr("Announce delivery")}</Button>
          </form>
        )}
      </section>

      <section aria-labelledby="sw-in">
        <h2 id="sw-in" className="mb-2 text-lg font-semibold">{tr("Your deliveries")}</h2>
        {inbound.length === 0 ? <EmptyState>{tr("No deliveries announced.")}</EmptyState> : (
          <ul className="space-y-2" data-testid="seller-inbound">
            {inbound.map((d) => (
              <li key={d.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-sm">
                <span>#{d.id} · {d.items.map((it) => `${it.product?.name} × ${d.status === "received" ? it.cartons_received : it.cartons_expected}`).join(", ")}</span>
                <span className="flex items-center gap-2">
                  <Badge variant={d.status === "announced" ? "default" : "secondary"}>{tr(d.status === "announced" ? "On its way" : d.status === "received" ? "Received" : "Cancelled")}</Badge>
                  {d.status === "announced" && <Button size="sm" variant="outline" onClick={() => cancelInbound(d.id).then(load).catch((e) => setMsg(errorMessage(e)))}>{tr("Cancel")}</Button>}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
