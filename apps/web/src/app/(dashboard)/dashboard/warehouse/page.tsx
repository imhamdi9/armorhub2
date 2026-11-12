"use client";

import { useCallback, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import {
  addWarehouse, getMarkets, getPackOrders, getWarehouseInbound, getWarehouses, getWarehouseStock, receiveInbound, shipPackOrder,
  type InboundRow, type MarketRow, type PackOrder, type StockRow, type WarehouseRow,
} from "@/services/platform.service";

/** The market's warehouse team: deliveries from sellers, stock, and orders to pack and send. */
export default function WarehousePage() {
  const user = useCurrentUser();
  const isAdmin = !!user?.roles?.includes("platform-admin");
  const [markets, setMarkets] = useState<MarketRow[]>([]);
  const [market, setMarket] = useState<number | null>(null);
  const [warehouses, setWarehouses] = useState<WarehouseRow[]>([]);
  const [inbound, setInbound] = useState<InboundRow[]>([]);
  const [stock, setStock] = useState<StockRow[]>([]);
  const [orders, setOrders] = useState<PackOrder[]>([]);
  const [counted, setCounted] = useState<Record<number, string>>({});
  const [tracking, setTracking] = useState<Record<number, string>>({});
  const [name, setName] = useState("");
  const [msg, setMsg] = useState<string | null>(null);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => { if (isAdmin) getMarkets().then(({ markets: m }) => { setMarkets(m); setMarket((cur) => cur ?? m[0]?.id ?? null); }).catch(() => undefined); }, [isAdmin]);

  const [tick, setTick] = useState(0);
  const load = useCallback(async () => setTick((t) => t + 1), []);
  useEffect(() => {
    if (isAdmin && !market) return;
    let live = true;
    const mk = isAdmin ? market : null;
    Promise.all([getWarehouses(mk), getWarehouseInbound(mk), getWarehouseStock(mk), getPackOrders(mk)])
      .then(([w, i, st, o]) => { if (live) { setWarehouses(w); setInbound(i); setStock(st); setOrders(o); } })
      .catch((e) => { if (live) setMsg(errorMessage(e)); })
      .finally(() => { if (live) setLoaded(true); });
    return () => { live = false; };
  }, [isAdmin, market, tick]);

  const m = isAdmin ? market : null;
  const run = async (fn: () => Promise<unknown>, ok: string) => {
    setMsg(null);
    try { await fn(); setMsg(ok); await load(); } catch (e) { setMsg(errorMessage(e)); }
  };

  if (!loaded && !isAdmin) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  return (
    <div className="space-y-8">
      <PageHeader title="Warehouse" />
      {isAdmin && (
        <div className="max-w-xs">
          <Label htmlFor="wh-market">{tr("Market")}</Label>
          <select id="wh-market" className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={market ?? ""} onChange={(e) => setMarket(Number(e.target.value))}>
            {markets.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
          </select>
        </div>
      )}
      {msg && <p className="text-sm" role="status">{msg}</p>}

      <section aria-labelledby="wh-orders">
        <h2 id="wh-orders" className="mb-2 text-lg font-semibold">{tr("Orders to pack and send")}</h2>
        {orders.length === 0 ? <EmptyState>{tr("Nothing to pack right now.")}</EmptyState> : (
          <ul className="divide-y rounded-xl border" data-testid="pack-orders">
            {orders.map((o) => (
              <li key={o.id} className="flex flex-wrap items-center justify-between gap-3 p-3">
                <div>
                  <p className="font-medium">#{o.id} · {o.product?.name} <code className="text-xs text-muted-foreground">{o.product?.sku}</code></p>
                  <p className="text-sm text-muted-foreground">{o.shipping_address ? `${o.shipping_address.name}, ${o.shipping_address.line1}, ${o.shipping_address.city} · ${o.shipping_address.phone}` : ""}</p>
                </div>
                <div className="flex items-center gap-2">
                  <Input aria-label={tr("Tracking number")} placeholder={tr("Tracking number")} className="w-40" value={tracking[o.id] ?? ""} onChange={(e) => setTracking({ ...tracking, [o.id]: e.target.value })} />
                  <Button size="sm" onClick={() => run(() => shipPackOrder(o.id, { carrier: "ArmorHub", tracking_number: tracking[o.id] || undefined }, m), tr("Shipped."))}>{tr("Pack and ship")}</Button>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section aria-labelledby="wh-in">
        <h2 id="wh-in" className="mb-2 text-lg font-semibold">{tr("Deliveries from sellers")}</h2>
        {inbound.length === 0 ? <EmptyState>{tr("No deliveries announced.")}</EmptyState> : (
          <ul className="space-y-3" data-testid="inbound-list">
            {inbound.map((d) => (
              <li key={d.id} className="rounded-xl border p-3">
                <div className="flex items-center justify-between">
                  <p className="font-medium">{tr("Delivery")} #{d.id} {d.carrier && <span className="text-sm text-muted-foreground">· {d.carrier} {d.tracking_number}</span>}</p>
                  <Badge variant={d.status === "announced" ? "default" : "secondary"}>{tr(d.status === "announced" ? "On its way" : d.status === "received" ? "Received" : "Cancelled")}</Badge>
                </div>
                <ul className="mt-2 space-y-2">
                  {d.items.map((it) => (
                    <li key={it.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                      <span>{it.product?.name} <code className="text-xs text-muted-foreground">{it.product?.sku}</code> · {tr("{n} cartons announced", { n: it.cartons_expected })}</span>
                      {d.status === "announced" ? (
                        <span className="flex items-center gap-2">
                          <Label htmlFor={`cnt-${it.id}`} className="sr-only">{tr("Cartons counted")}</Label>
                          <Input id={`cnt-${it.id}`} type="number" min="0" className="w-24" placeholder={tr("Counted")} value={counted[it.id] ?? ""} onChange={(e) => setCounted({ ...counted, [it.id]: e.target.value })} />
                        </span>
                      ) : <span>{tr("{n} cartons received", { n: it.cartons_received })}</span>}
                    </li>
                  ))}
                </ul>
                {d.status === "announced" && (
                  <Button size="sm" className="mt-3" onClick={() => run(() => receiveInbound(d.id, d.items.map((it) => ({ id: it.id, cartons_received: Number(counted[it.id] ?? it.cartons_expected) })), m), tr("Delivery received."))}>{tr("Confirm count and receive")}</Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section aria-labelledby="wh-stock">
        <h2 id="wh-stock" className="mb-2 text-lg font-semibold">{tr("Stock")}</h2>
        {stock.length === 0 ? <EmptyState>{tr("No stock yet.")}</EmptyState> : (
          <table className="w-full text-sm" data-testid="stock-table">
            <thead><tr className="text-start text-muted-foreground"><th className="py-1 text-start">{tr("Product")}</th><th className="text-start">SKU</th><th className="text-start">{tr("Warehouse")}</th><th className="text-end">{tr("Units")}</th></tr></thead>
            <tbody>{stock.map((r) => <tr key={`${r.warehouse_id}-${r.product_id}`} className="border-t"><td className="py-1">{r.name}</td><td><code>{r.sku}</code></td><td>{r.warehouse}</td><td className="text-end">{r.units}</td></tr>)}</tbody>
          </table>
        )}
      </section>

      <section aria-labelledby="wh-list">
        <h2 id="wh-list" className="mb-2 text-lg font-semibold">{tr("Warehouses")}</h2>
        <ul className="mb-3 space-y-1 text-sm">{warehouses.map((w) => <li key={w.id}>{w.name}{w.city ? ` · ${w.city}` : ""}</li>)}</ul>
        <form className="flex items-end gap-2" onSubmit={(e) => { e.preventDefault(); run(async () => { await addWarehouse({ name }, m); setName(""); }, tr("Warehouse added.")); }}>
          <div><Label htmlFor="wh-name">{tr("New warehouse name")}</Label><Input id="wh-name" required value={name} onChange={(e) => setName(e.target.value)} /></div>
          <Button type="submit">{tr("Add warehouse")}</Button>
        </form>
      </section>
    </div>
  );
}
