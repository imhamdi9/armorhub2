"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { hasRole } from "@/lib/navigation";
import { addMaterial, assignTechnician, getTechnicians, getWorkOrder, removeMaterial, type WorkOrder } from "@/services/ops.service";

/** One job as the people doing it see it: who has it, when, and what was used. */
export default function WorkOrderPage() {
  const id = Number(useParams<{ id: string }>().id);
  const user = useCurrentUser();
  const manager = hasRole(user, "partner-manager");
  const [w, setW] = useState<WorkOrder | null>(null);
  const [techs, setTechs] = useState<{ id: number; name: string; open_jobs: number }[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const [missing, setMissing] = useState(false);
  const load = () => getWorkOrder(id).then(setW).catch(() => setMissing(true));
  useEffect(() => { load(); if (manager) getTechnicians().then(setTechs).catch(() => {}); }, [id, manager]);

  async function act(fn: () => Promise<unknown>) {
    setErr(null);
    try { await fn(); await load(); } catch (e) { setErr(errorMessage(e)); }
  }

  if (missing) return <EmptyState>{tr("This work order was not found.")}</EmptyState>;
  if (!w) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="space-y-6">
      <PageHeader title={`${tr("Work order")} #${w.job_id}`} action={<Badge>{w.status}</Badge>} />
      {err && <Note tone="warn">{err}</Note>}
      <div className="rounded-xl border p-4 text-sm">
        <p className="font-semibold">{w.subject}</p>
        <p className="text-muted-foreground">{w.parts.join(", ")}</p>
        {w.booking && <p className="mt-1">{tr("Booked")}: {formatDateTime(w.booking.starts_at)}</p>}
        <p className="mt-1">{tr("Technician")}: {w.technician?.name ?? tr("Not assigned")}</p>
        {manager && (
          <select className={`${field} mt-3 max-w-xs`} value={w.technician?.id ?? ""} aria-label={tr("Technician")} onChange={(e) => act(() => assignTechnician(id, e.target.value ? Number(e.target.value) : null))}>
            <option value="">{tr("Not assigned")}</option>
            {techs.map((t) => <option key={t.id} value={t.id}>{t.name} · {t.open_jobs}</option>)}
          </select>
        )}
      </div>

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Materials used")}</h2>
        <ul className="divide-y rounded-xl border" data-testid="materials">
          {w.materials.length === 0 && <li className="p-3 text-sm text-muted-foreground">{tr("Nothing added yet.")}</li>}
          {w.materials.map((m) => (
            <li key={m.id} className="flex items-center justify-between gap-2 p-3 text-sm">
              <span>{m.name} × {m.quantity}{m.unit ? ` ${m.unit}` : ""}{m.batch ? ` · ${m.batch}` : ""}</span>
              {w.can_edit_materials && <Button size="sm" variant="outline" onClick={() => act(() => removeMaterial(id, m.id))}>{tr("Remove")}</Button>}
            </li>
          ))}
        </ul>
        {w.can_edit_materials && (
          <form
            className="mt-3 flex flex-wrap gap-2"
            onSubmit={(e) => {
              e.preventDefault();
              const f = new FormData(e.currentTarget);
              const form = e.currentTarget;
              act(() => addMaterial(id, { name: String(f.get("name")), quantity: Number(f.get("quantity")), unit: String(f.get("unit") || "") || undefined, batch: String(f.get("batch") || "") || undefined })).then(() => form.reset());
            }}
          >
            <input name="name" className={`${field} max-w-xs`} placeholder={tr("Material")} required maxLength={160} />
            <input name="quantity" type="number" step="0.01" min="0.01" className={`${field} w-24`} placeholder={tr("Qty")} required />
            <input name="unit" className={`${field} w-24`} placeholder={tr("Unit")} maxLength={20} />
            <input name="batch" className={`${field} w-32`} placeholder={tr("Batch")} maxLength={80} />
            <Button type="submit">{tr("Add material")}</Button>
          </form>
        )}
      </section>
    </div>
  );
}
