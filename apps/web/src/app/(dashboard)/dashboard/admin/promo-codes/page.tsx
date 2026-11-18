"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getPromoCodes, savePromoCode, type PromoCode } from "@/services/ops.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** Discount codes: create them, switch them on and off, and see how much each was used. */
export default function PromoCodesPage() {
  const [rows, setRows] = useState<PromoCode[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const load = () => getPromoCodes().then(setRows).catch(() => setRows([]));
  useEffect(() => { load(); }, []);

  async function run(fn: () => Promise<unknown>) {
    setErr(null);
    try { await fn(); await load(); } catch (e) { setErr(errorMessage(e)); }
  }
  const num = (v: FormDataEntryValue | null) => (v === null || v === "" ? null : Number(v));

  return (
    <div className="space-y-8">
      <PageHeader title={tr("Promo codes")} />
      <p className="text-sm text-muted-foreground">{tr("Codes take money off the items. The platform pays for the discount; sellers still receive the full price.")}</p>
      {err && <Note tone="warn">{err}</Note>}

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Add a code")}</h2>
        <form
          className="grid gap-3 rounded-xl border p-4 md:grid-cols-4"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            const form = e.currentTarget;
            run(() => savePromoCode({ code: f.get("code"), type: f.get("type"), value: num(f.get("value")), max_discount: num(f.get("max")), min_order: num(f.get("min")), market: String(f.get("market") || "") || null, usage_limit: num(f.get("limit")), per_customer_limit: num(f.get("per")) ?? 1 })).then(() => form.reset());
          }}
        >
          <input name="code" className={field} placeholder={tr("Code")} required maxLength={40} />
          <select name="type" className={field} defaultValue="percent"><option value="percent">{tr("Percent")}</option><option value="fixed">{tr("Fixed amount")}</option></select>
          <input name="value" type="number" step="0.01" min="0.01" className={field} placeholder={tr("Value")} required />
          <input name="max" type="number" step="0.01" className={field} placeholder={tr("Largest discount (optional)")} />
          <input name="min" type="number" step="0.01" className={field} placeholder={tr("Smallest order (optional)")} />
          <input name="market" maxLength={2} className={field} placeholder={tr("Country code (optional)")} />
          <input name="limit" type="number" min={1} className={field} placeholder={tr("Total uses (optional)")} />
          <input name="per" type="number" min={1} defaultValue={1} className={field} aria-label={tr("Uses per customer")} />
          <div><Button type="submit">{tr("Save code")}</Button></div>
        </form>
      </section>

      {rows === null ? <LoadingBlock /> : rows.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No codes yet.")}</p> : (
        <ul className="divide-y rounded-xl border" data-testid="promo-list">
          {rows.map((p) => (
            <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm">
              <span><b>{p.code}</b> · {p.type === "percent" ? `${p.value}%` : p.value}{p.market ? ` · ${p.market}` : ""}</span>
              <span className="text-muted-foreground">{p.used} {tr("used")}{p.usage_limit ? ` / ${p.usage_limit}` : ""}</span>
              <span className="flex items-center gap-2">
                <Badge variant={p.is_active ? "default" : "secondary"}>{p.is_active ? tr("Open") : tr("Closed")}</Badge>
                <Button size="sm" variant="outline" onClick={() => run(() => savePromoCode({ is_active: !p.is_active }, p.id))}>{p.is_active ? tr("Turn off") : tr("Turn on")}</Button>
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
