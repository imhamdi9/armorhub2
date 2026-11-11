"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { aliasLabel } from "@/lib/navigation";
import { getCatalog } from "@/services/platform.service";
import type { CatalogItem } from "@/types/platform";

export default function CatalogPage() {
  const { alias } = useParams<{ alias: string }>();
  const [items, setItems] = useState<CatalogItem[] | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    getCatalog(alias).then(setItems).catch(() => setFailed(true));
  }, [alias]);

  return (
    <div>
      <PageHeader title={aliasLabel(alias)} />

      {failed ? (
        <EmptyState>This catalog is not available.</EmptyState>
      ) : items === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : items.length === 0 ? (
        <EmptyState>Nothing here yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {items.map((item) => (
            <div key={item.id} className="rounded-lg border p-4">
              <h2 className="font-semibold">{item.name}</h2>
              <p className="text-sm text-muted-foreground">{item.slug}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
