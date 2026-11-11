"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { aliasLabel } from "@/lib/navigation";
import { createMyItem, getCatalog, getMyItems } from "@/services/platform.service";

type Row = Record<string, unknown>;

/**
 * What the customer brings to be serviced. Customer-owned types (a property)
 * can be added here; shared catalogs (vehicle models) are browse-only.
 */
export default function MyItemsPage() {
  const { alias } = useParams<{ alias: string }>();
  const [rows, setRows] = useState<Row[] | null>(null);
  const [owned, setOwned] = useState(true);
  const [form, setForm] = useState({ type: "", address: "", city: "", area_sqm: "" });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getMyItems(alias)
      .then(setRows)
      .catch(async () => {
        setOwned(false);
        setRows((await getCatalog(alias).catch(() => [])).map((r) => ({ ...r })));
      });
  }, [alias]);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const body = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ""));
      const created = await createMyItem(alias, body);
      setRows((prev) => [created, ...(prev ?? [])]);
      setForm({ type: "", address: "", city: "", area_sqm: "" });
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader title={`My ${aliasLabel(alias)}`} />

      {owned && (
        <form onSubmit={add} className="mb-6 grid grid-cols-2 gap-2 rounded-lg border p-4">
          <Input placeholder="Type (e.g. villa)" value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} />
          <Input placeholder="City" value={form.city} onChange={(e) => setForm({ ...form, city: e.target.value })} />
          <Input placeholder="Address" value={form.address} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          <Input placeholder="Area (sqm)" type="number" min="0" value={form.area_sqm} onChange={(e) => setForm({ ...form, area_sqm: e.target.value })} />
          {error && <p className="col-span-2 text-sm text-red-500">{error}</p>}
          <Button type="submit" className="col-span-2">Add</Button>
        </form>
      )}

      {rows === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : rows.length === 0 ? (
        <EmptyState>Nothing here yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {rows.map((r) => (
            <div key={String(r.id)} className="rounded-lg border p-4">
              <h2 className="font-semibold">
                {String(r.name ?? r.type ?? `#${r.id}`)}
              </h2>
              <p className="text-sm text-muted-foreground">
                {[r.address, r.city, r.area_sqm ? `${r.area_sqm} sqm` : null].filter(Boolean).join(" · ")}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
