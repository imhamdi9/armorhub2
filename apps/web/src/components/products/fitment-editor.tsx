"use client";

import { useEffect, useMemo, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getFitments, getMakes, getModels, saveFitments, type FitmentRow, type MakeOrModel } from "@/services/ops.service";

/** Which cars a part fits. No rows means it fits any car; "required" makes the customer choose a car that fits before buying. */
export function FitmentEditor({ productId }: { productId: number }) {
  const [rows, setRows] = useState<FitmentRow[]>([]);
  const [required, setRequired] = useState(false);
  const [makes, setMakes] = useState<MakeOrModel[]>([]);
  const [models, setModels] = useState<MakeOrModel[]>([]);
  const [msg, setMsg] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  const [draft, setDraft] = useState({ make: "", model: "", from: "", to: "" });
  const shown = useMemo(() => models.filter((m) => String(m.vehicle_make_id) === draft.make), [models, draft.make]);
  const name = (list: MakeOrModel[], id: number | null) => list.find((x) => x.id === id)?.name ?? "";

  useEffect(() => {
    getFitments(productId).then((f) => { setRows(f.rows); setRequired(f.required); }).catch(() => {});
    getMakes().then(setMakes).catch(() => {});
    getModels().then(setModels).catch(() => {});
  }, [productId]);

  function add() {
    if (!draft.make) return;
    setRows([...rows, { vehicle_make_id: Number(draft.make), vehicle_model_id: draft.model ? Number(draft.model) : null, year_from: draft.from ? Number(draft.from) : null, year_to: draft.to ? Number(draft.to) : null, note: null }]);
    setDraft({ make: "", model: "", from: "", to: "" });
  }

  async function save() {
    setMsg(null);
    try { await saveFitments(productId, rows, required); setMsg({ tone: "ok", text: tr("Saved") }); } catch (e) { setMsg({ tone: "warn", text: errorMessage(e) }); }
  }

  return (
    <section className="rounded-xl border p-4" data-testid="fitment-editor">
      <h2 className="mb-1 text-lg font-semibold">{tr("Cars this fits")}</h2>
      <p className="mb-3 text-sm text-muted-foreground">{tr("Leave empty if it fits every car. Customers with a car in their garage see whether it fits.")}</p>
      {msg && <Note tone={msg.tone}>{msg.text}</Note>}
      <ul className="mb-3 divide-y rounded-lg border">
        {rows.length === 0 && <li className="p-3 text-sm text-muted-foreground">{tr("Fits every car")}</li>}
        {rows.map((r, i) => (
          <li key={i} className="flex items-center justify-between gap-2 p-3 text-sm">
            <span>{r.make ?? name(makes, r.vehicle_make_id)} {r.model ?? name(models, r.vehicle_model_id) ?? ""} {r.year_from ? `${r.year_from}${r.year_to ? `–${r.year_to}` : "+"}` : ""}</span>
            <Button type="button" size="sm" variant="outline" onClick={() => setRows(rows.filter((_, j) => j !== i))}>{tr("Remove")}</Button>
          </li>
        ))}
      </ul>
      <div className="mb-3 grid gap-2 md:grid-cols-4">
        <select className={field} value={draft.make} onChange={(e) => setDraft({ ...draft, make: e.target.value, model: "" })} aria-label={tr("Make")}><option value="">{tr("Make")}</option>{makes.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}</select>
        <select className={field} value={draft.model} onChange={(e) => setDraft({ ...draft, model: e.target.value })} aria-label={tr("Model")}><option value="">{tr("Any model")}</option>{shown.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}</select>
        <input className={field} type="number" placeholder={tr("From year")} value={draft.from} onChange={(e) => setDraft({ ...draft, from: e.target.value })} />
        <input className={field} type="number" placeholder={tr("To year")} value={draft.to} onChange={(e) => setDraft({ ...draft, to: e.target.value })} />
      </div>
      <label className="mb-3 flex items-start gap-2 text-sm"><input type="checkbox" className="mt-1" checked={required} onChange={(e) => setRequired(e.target.checked)} />{tr("The customer must choose a car that fits before buying")}</label>
      <div className="flex gap-2"><Button type="button" variant="outline" onClick={add}>{tr("Add car")}</Button><Button type="button" onClick={save}>{tr("Save fitment")}</Button></div>
    </section>
  );
}
