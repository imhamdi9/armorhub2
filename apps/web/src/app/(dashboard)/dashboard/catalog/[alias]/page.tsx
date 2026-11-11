"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { aliasLabel } from "@/lib/navigation";
import {
  createCatalogItem,
  deleteCatalogItem,
  getCatalog,
} from "@/services/platform.service";
import type { CatalogItem } from "@/types/platform";

function slugify(value: string) {
  return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

export default function CatalogPage() {
  const { alias } = useParams<{ alias: string }>();
  const [items, setItems] = useState<CatalogItem[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [name, setName] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getCatalog(alias).then(setItems).catch(() => setFailed(true));
  }, [alias]);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const created = await createCatalogItem(alias, { name, slug: slugify(name) });
      setItems((prev) => [...(prev ?? []), created]);
      setName("");
    } catch (err) {
      setError(errorMessage(err, "Could not add this item."));
    }
  }

  async function remove(id: number) {
    await deleteCatalogItem(alias, id);
    setItems((prev) => prev?.filter((i) => i.id !== id) ?? null);
  }

  return (
    <div>
      <PageHeader title={aliasLabel(alias)} />

      {!failed && (
        <form onSubmit={add} className="mb-6 flex gap-2">
          <Input placeholder="Add new..." required value={name} onChange={(e) => setName(e.target.value)} />
          <Button type="submit">Add</Button>
        </form>
      )}
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {failed ? (
        <EmptyState>This catalog is not available.</EmptyState>
      ) : items === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : items.length === 0 ? (
        <EmptyState>Nothing here yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {items.map((item) => (
            <div key={item.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <h2 className="font-semibold">{item.name}</h2>
                <p className="text-sm text-muted-foreground">{item.slug}</p>
              </div>
              <Button size="sm" variant="outline" onClick={() => remove(item.id)}>Delete</Button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
