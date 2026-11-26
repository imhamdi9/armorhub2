"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { api } from "@/services/api";
import { createSellerPromo, getSellerPromos, updateSellerPromo, type SellerPromo } from "@/services/platform.service";

/** A seller's own discount codes. They work only on this seller's products, and what they give comes out of the seller's earnings. */
export default function PromotionsPage() {
  const [list, setList] = useState<SellerPromo[] | null>(null);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  // A code applies only to the products the seller picks.
  const [mine, setMine] = useState<{ id: number; name: string }[]>([]);
  const [picked, setPicked] = useState<number[]>([]);
  const [form, setForm] = useState({ code: "", type: "percent" as "percent" | "fixed", value: "10", min_order: "", usage_limit: "", ends_at: "" });
  const load = () => getSellerPromos().then(setList).catch(() => setList([]));
  useEffect(() => { load(); api.get<{ id: number; name: string }[]>("/products").then((r) => setMine(r.data ?? [])).catch(() => setMine([])); }, []);
  if (!list) return <LoadingBlock />;

  return (
    <div className="max-w-3xl space-y-6">
      <PageHeader title={tr("Discount codes")} />
      <p className="text-sm text-muted-foreground">{tr("A code gives your customers a discount on your products only. The discount comes out of your earnings, never from other sellers.")}</p>
      {msg && <Note tone={msg.ok ? "ok" : "warn"}>{msg.text}</Note>}

      <form className="grid gap-3 rounded-xl border p-4 sm:grid-cols-2" data-testid="promo-form" onSubmit={async (e) => {
        e.preventDefault(); setMsg(null);
        try {
          await createSellerPromo({ product_ids: picked, code: form.code, type: form.type, value: Number(form.value), min_order: form.min_order ? Number(form.min_order) : null, usage_limit: form.usage_limit ? Number(form.usage_limit) : null, ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : null });
          setForm({ ...form, code: "" }); setPicked([]); setMsg({ ok: true, text: tr("Code created") }); await load();
        } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
      }}>
        <label className="text-sm">{tr("Code")}<Input value={form.code} required minLength={3} maxLength={40} dir="ltr" placeholder="SUMMER10" onChange={(e) => setForm({ ...form, code: e.target.value })} /></label>
        <label className="text-sm">{tr("Type")}
          <select className="mt-1 h-10 w-full rounded-[10px] border bg-background px-2" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value as "percent" | "fixed" })}>
            <option value="percent">{tr("Percentage (%)")}</option>
            <option value="fixed">{tr("Fixed amount")}</option>
          </select>
        </label>
        <label className="text-sm">{tr("Value")}<Input type="number" min={1} step="0.01" required value={form.value} onChange={(e) => setForm({ ...form, value: e.target.value })} /></label>
        <label className="text-sm">{tr("Smallest order (optional)")}<Input type="number" min={0} value={form.min_order} onChange={(e) => setForm({ ...form, min_order: e.target.value })} /></label>
        <label className="text-sm">{tr("Times it can be used (optional)")}<Input type="number" min={1} value={form.usage_limit} onChange={(e) => setForm({ ...form, usage_limit: e.target.value })} /></label>
        <label className="text-sm">{tr("Ends on (optional)")}<Input type="date" value={form.ends_at} onChange={(e) => setForm({ ...form, ends_at: e.target.value })} /></label>
        <fieldset className="sm:col-span-2" data-testid="promo-products">
          <legend className="mb-1 flex items-center justify-between text-sm font-medium"><span>{tr("Which of your products does this code apply to?")}</span>
            <button type="button" className="text-xs underline" onClick={() => setPicked(picked.length === mine.length ? [] : mine.map((p) => p.id))}>{picked.length === mine.length ? tr("Clear") : tr("Select all")}</button>
          </legend>
          {mine.length === 0 ? <p className="text-sm text-muted-foreground">{tr("You have no products yet.")}</p> : (
            <div className="grid max-h-48 gap-1 overflow-auto rounded-lg border p-2 text-sm sm:grid-cols-2">
              {mine.map((p) => (
                <label key={p.id} className="flex items-center gap-2"><input type="checkbox" checked={picked.includes(p.id)} onChange={(e) => setPicked(e.target.checked ? [...picked, p.id] : picked.filter((x) => x !== p.id))} />{p.name}</label>
              ))}
            </div>
          )}
        </fieldset>
        <div className="sm:col-span-2"><Button type="submit" disabled={picked.length === 0}>{tr("Create code")}</Button></div>
      </form>

      {list.length === 0 ? <EmptyState>{tr("You have no discount codes yet.")}</EmptyState> : (
        <ul className="divide-y rounded-xl border text-sm" data-testid="promo-list">
          {list.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
              <span><b dir="ltr">{p.code}</b> · {p.type === "percent" ? `${p.value}%` : p.value}{p.ends_at ? ` · ${tr("Ends on")} ${formatDateTime(p.ends_at)}` : ""}<span className="block text-xs text-muted-foreground">{Object.values(p.products ?? {}).join("، ")}</span></span>
              <span className="flex items-center gap-2">
                <span className="text-muted-foreground">{tr("Used {n} times", { n: p.used })}</span>
                <Badge variant={p.is_active ? "default" : "secondary"}>{tr(p.is_active ? "On" : "Off")}</Badge>
                <Button size="sm" variant="outline" onClick={async () => { try { await updateSellerPromo(p.id, { is_active: !p.is_active }); await load(); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); } }}>{tr(p.is_active ? "Switch off" : "Switch on")}</Button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
