"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { aliasLabel, aliasSingular } from "@/lib/navigation";
import {
  createCatalogItem,
  deleteCatalogItem,
  getCatalog,
  getCatalogSchema,
} from "@/services/platform.service";
import type { CatalogItem } from "@/types/platform";
import { tr } from "@/lib/i18n";

function slugify(value: string) {
  return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

type Item = CatalogItem & Record<string, unknown>;

export default function CatalogPage() {
  const { alias } = useParams<{ alias: string }>();
  const [items, setItems] = useState<Item[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [name, setName] = useState("");
  const [parents, setParents] = useState<{ field: string; alias: string; options: CatalogItem[] }[]>([]);
  const [parentValues, setParentValues] = useState<Record<string, string>>({});
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getCatalog(alias).then((rows) => setItems(rows as Item[])).catch(() => setFailed(true));
    // Records like a vehicle model belong to a parent list (its make).
    getCatalogSchema(alias)
      .then(async (schema) =>
        setParents(
          await Promise.all(
            schema.parents.map(async (p) => ({ ...p, options: await getCatalog(p.alias) })),
          ),
        ),
      )
      .catch(() => setParents([]));
  }, [alias]);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const created = await createCatalogItem(alias, {
        name,
        slug: slugify(name),
        ...Object.fromEntries(parents.map((p) => [p.field, Number(parentValues[p.field])])),
      });
      setItems((prev) => [...(prev ?? []), created as Item]);
      setName("");
    } catch (err) {
      setError(errorMessage(err, "Could not add this item."));
    }
  }

  async function remove(id: number) {
    setError(null);
    try {
      await deleteCatalogItem(alias, id);
      setItems((prev) => prev?.filter((i) => i.id !== id) ?? null);
    } catch (err) {
      setError(errorMessage(err, "This item is in use and cannot be deleted."));
    }
  }

  const parentName = (item: Item) =>
    parents
      .map((p) => p.options.find((o) => o.id === Number(item[p.field]))?.name)
      .filter(Boolean)
      .join(", ");

  return (
    <div>
      <PageHeader title={aliasLabel(alias)} />

      {!failed && (
        <form onSubmit={add} className="mb-6 flex flex-wrap gap-2">
          {parents.map((p) => (
            <select
              key={p.field}
              required
              aria-label={tr(aliasLabel(p.alias))}
              className="rounded-lg border px-3 py-2"
              value={parentValues[p.field] ?? ""}
              onChange={(e) => setParentValues((v) => ({ ...v, [p.field]: e.target.value }))}
            >
              <option value="" disabled>{tr(aliasSingular(p.alias))}</option>
              {p.options.map((o) => (
                <option key={o.id} value={o.id}>{o.name}</option>
              ))}
            </select>
          ))}
          <Input className="max-w-xs" placeholder={tr("Add new...")} required value={name} onChange={(e) => setName(e.target.value)} />
          <Button type="submit">{tr("Add")}</Button>
        </form>
      )}
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {failed ? (
        <EmptyState>{tr("This catalog is not available.")}</EmptyState>
      ) : items === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : items.length === 0 ? (
        <EmptyState>{tr("Nothing here yet.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {items.map((item) => (
            <div key={item.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <h2 className="font-semibold">{item.name}</h2>
                <p className="text-sm text-muted-foreground">{[parentName(item), item.slug].filter(Boolean).join(" · ")}</p>
              </div>
              <Button size="sm" variant="outline" onClick={() => remove(item.id)}>{tr("Delete")}</Button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
