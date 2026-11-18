"use client";

import { useEffect, useMemo, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { addVehicle, getGarage, getMakes, getModels, removeVehicle, updateVehicle, type MakeOrModel, type Vehicle } from "@/services/ops.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** The customer's cars: saved once, used to show only the parts that fit and to start a request faster. */
export default function GaragePage() {
  const [cars, setCars] = useState<Vehicle[] | null>(null);
  const [makes, setMakes] = useState<MakeOrModel[]>([]);
  const [models, setModels] = useState<MakeOrModel[]>([]);
  const [adding, setAdding] = useState(false);
  const [make, setMake] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const load = () => getGarage().then(setCars).catch(() => setCars([]));
  useEffect(() => { load(); getMakes().then(setMakes).catch(() => {}); getModels().then(setModels).catch(() => {}); }, []);
  const shown = useMemo(() => models.filter((m) => String(m.vehicle_make_id) === make), [models, make]);

  async function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setErr(null);
    const f = new FormData(e.currentTarget);
    try {
      await addVehicle({ vehicle_model_id: Number(f.get("model")), year: Number(f.get("year")), nickname: String(f.get("nickname") || "") || undefined, vin: String(f.get("vin") || "") || undefined });
      setAdding(false);
      setMake("");
      load();
    } catch (x) { setErr(errorMessage(x)); }
  }

  return (
    <div>
      <PageHeader title={tr("My garage")} action={<Button onClick={() => setAdding(true)}>{tr("Add a vehicle")}</Button>} />
      {err && <Note tone="warn">{err}</Note>}
      {adding && (
        <form onSubmit={submit} className="mb-6 grid gap-3 rounded-xl border p-4 md:grid-cols-2" data-testid="vehicle-form">
          <select className={field} value={make} onChange={(e) => setMake(e.target.value)} aria-label={tr("Make")} required>
            <option value="">{tr("Make")}</option>
            {makes.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
          </select>
          <select name="model" className={field} aria-label={tr("Model")} required>
            <option value="">{tr("Model")}</option>
            {shown.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
          </select>
          <input name="year" type="number" min={1980} max={new Date().getFullYear() + 1} className={field} placeholder={tr("Year")} required />
          <input name="nickname" className={field} placeholder={tr("Nickname (optional)")} maxLength={60} />
          <input name="vin" className={field} placeholder={tr("VIN, 17 characters (optional)")} maxLength={17} />
          <div className="flex gap-2"><Button type="submit">{tr("Save vehicle")}</Button><Button type="button" variant="outline" onClick={() => setAdding(false)}>{tr("Cancel")}</Button></div>
        </form>
      )}
      {cars === null ? <LoadingBlock /> : cars.length === 0 ? (
        <EmptyState>{tr("Add your car to see only the parts that fit it.")}</EmptyState>
      ) : (
        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
          {cars.map((c) => (
            <div key={c.id} className="rounded-xl border p-4" data-testid="vehicle">
              <div className="flex items-center justify-between gap-2">
                <p className="font-semibold">{c.nickname || c.label}</p>
                {c.is_default && <Badge>{tr("Default")}</Badge>}
              </div>
              <p className="text-sm text-muted-foreground">{c.label}{c.vin ? ` · ${c.vin}` : ""}</p>
              <div className="mt-3 flex flex-wrap gap-2">
                {!c.is_default && <Button size="sm" variant="outline" onClick={() => updateVehicle(c.id, { is_default: true }).then(load)}>{tr("Make default")}</Button>}
                <Button size="sm" variant="outline" onClick={() => removeVehicle(c.id).then(load)}>{tr("Remove")}</Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
