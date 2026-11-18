"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { createSellerBundle, getSellerBundles, updateSellerBundle, type SellerBundle } from "@/services/platform.service";
import { api } from "@/services/api";

interface Mine { id: number; name: string; base_price: number; fulfilment?: string }

/** Several of the seller's own products sold together at one price. Bought, shipped and returned like any product. */
export default function BundlesPage() {
  const [bundles, setBundles] = useState<SellerBundle[] | null>(null);
  const [mine, setMine] = useState<Mine[]>([]);
  const [qty, setQty] = useState<Record<number, number>>({});
  const [name, setName] = useState("");
  const [nameAr, setNameAr] = useState("");
  const [price, setPrice] = useState("");
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const load = () => getSellerBundles().then(setBundles).catch(() => setBundles([]));
  useEffect(() => {
    load();
    api.get<Mine[]>("/products").then((r) => setMine((r.data ?? []).filter((p) => p.fulfilment !== "armorhub"))).catch(() => setMine([]));
  }, []);
  if (!bundles) return <LoadingBlock />;

  const picked = mine.filter((p) => (qty[p.id] ?? 0) > 0);
  const worth = picked.reduce((s, p) => s + Number(p.base_price) * (qty[p.id] ?? 0), 0);

  return (
    <div className="max-w-3xl space-y-6">
      <PageHeader title={tr("Bundles")} />
      <p className="text-sm text-muted-foreground">{tr("Sell several of your products together at one price. Choose at least two, and how many of each. Products stored in ArmorHub's warehouse cannot be bundled.")}</p>
      {msg && <Note tone={msg.ok ? "ok" : "warn"}>{msg.text}</Note>}

      <form className="space-y-3 rounded-xl border p-4" data-testid="bundle-form" onSubmit={async (e) => {
        e.preventDefault(); setMsg(null);
        try {
          await createSellerBundle({ name, name_ar: nameAr || undefined, base_price: Number(price), items: picked.map((p) => ({ product_id: p.id, quantity: qty[p.id] })) });
          setName(""); setNameAr(""); setPrice(""); setQty({}); setMsg({ ok: true, text: tr("Bundle created") }); await load();
        } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
      }}>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm">{tr("Bundle name")}<Input value={name} required minLength={3} maxLength={255} onChange={(e) => setName(e.target.value)} /></label>
          <label className="text-sm">{tr("Name in Arabic")}<Input value={nameAr} dir="rtl" maxLength={255} onChange={(e) => setNameAr(e.target.value)} /></label>
        </div>
        <fieldset className="space-y-1">
          <legend className="text-sm font-medium">{tr("Products in the bundle")}</legend>
          {mine.length === 0 ? <p className="text-sm text-muted-foreground">{tr("You have no products to bundle yet.")}</p> : mine.map((p) => (
            <div key={p.id} className="flex items-center justify-between gap-2 text-sm">
              <span>{p.name} · {formatMoney(Number(p.base_price), "EGP")}</span>
              <Input aria-label={`${tr("How many")} ${p.name}`} className="w-20" type="number" min={0} max={50} value={qty[p.id] ?? 0} onChange={(e) => setQty({ ...qty, [p.id]: Number(e.target.value) })} />
            </div>
          ))}
        </fieldset>
        <div className="flex flex-wrap items-end gap-3">
          <label className="text-sm">{tr("Bundle price")}<Input type="number" min={1} step="0.01" required value={price} onChange={(e) => setPrice(e.target.value)} /></label>
          {worth > 0 && <p className="text-sm text-muted-foreground" data-testid="bundle-worth">{tr("Separately these cost")} {formatMoney(worth, "EGP")}{price && Number(price) < worth ? ` · ${tr("Customers save")} ${formatMoney(worth - Number(price), "EGP")}` : ""}</p>}
        </div>
        <Button type="submit" disabled={picked.length < 2}>{tr("Create bundle")}</Button>
      </form>

      {bundles.length === 0 ? <EmptyState>{tr("You have no bundles yet.")}</EmptyState> : (
        <ul className="divide-y rounded-xl border text-sm" data-testid="bundle-list">
          {bundles.map((b) => (
            <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
              <span><b>{b.name}</b> · {formatMoney(b.base_price, "EGP")} <span className="text-muted-foreground">({tr("Separately these cost")} {formatMoney(b.worth, "EGP")})</span><span className="block text-xs text-muted-foreground">{b.items.map((i) => `${i.quantity} × ${i.name}`).join(" + ")}</span></span>
              <span className="flex items-center gap-2">
                <Badge variant={b.is_active ? "default" : "secondary"}>{tr(b.is_active ? "On" : "Off")}</Badge>
                <Button size="sm" variant="outline" onClick={async () => { try { await updateSellerBundle(b.id, { is_active: !b.is_active }); await load(); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); } }}>{tr(b.is_active ? "Switch off" : "Switch on")}</Button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
