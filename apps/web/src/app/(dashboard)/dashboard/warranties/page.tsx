"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { getWarranties } from "@/services/platform.service";
import type { Warranty } from "@/types/platform";

export default function WarrantiesPage() {
  const [items, setItems] = useState<Warranty[] | null>(null);

  useEffect(() => {
    getWarranties().then(setItems).catch(() => setItems([]));
  }, []);

  return (
    <div>
      <PageHeader title="Warranties" />

      {items === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : items.length === 0 ? (
        <EmptyState>No warranties yet. One is issued when a warranted service is completed.</EmptyState>
      ) : (
        <div className="space-y-3">
          {items.map((w) => (
            <div key={w.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <p className="font-semibold">{w.product?.name}</p>
                <p className="text-sm text-muted-foreground">
                  Order #{w.order_id} · {new Date(w.starts_at).toLocaleDateString()} –{" "}
                  {new Date(w.ends_at).toLocaleDateString()}
                </p>
              </div>
              <Badge variant={w.active ? "default" : "outline"}>{w.active ? "Active" : "Expired"}</Badge>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
