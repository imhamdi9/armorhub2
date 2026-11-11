"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { getPartners } from "@/services/platform.service";
import type { Partner } from "@/types/platform";

export default function PartnersPage() {
  const [partners, setPartners] = useState<Partner[] | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getPartners().then(setPartners).catch(() => setForbidden(true));
  }, []);

  return (
    <div>
      <PageHeader title="Partners" />

      {forbidden ? (
        <EmptyState>You do not have access to partners.</EmptyState>
      ) : partners === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : partners.length === 0 ? (
        <EmptyState>No partners yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {partners.map((p) => (
            <div key={p.id} className="rounded-lg border p-4">
              <h2 className="font-semibold">{p.name}</h2>
              <p className="text-sm text-muted-foreground">
                {[p.city, p.country, p.phone].filter(Boolean).join(" · ")}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
