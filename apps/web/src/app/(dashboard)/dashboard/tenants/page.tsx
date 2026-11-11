"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import {
  getIndustries,
  getTenants,
  updateTenant,
} from "@/services/platform.service";
import type { Industry } from "@/types/auth";
import type { Tenant } from "@/types/platform";

export default function TenantsPage() {
  const [tenants, setTenants] = useState<Tenant[] | null>(null);
  const [industries, setIndustries] = useState<Industry[]>([]);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getTenants().then(setTenants).catch(() => setForbidden(true));
    getIndustries().then(setIndustries);
  }, []);

  async function assign(tenant: Tenant, industryId: number) {
    const updated = await updateTenant(tenant.id, { industry_id: industryId });
    setTenants((prev) => prev?.map((t) => (t.id === tenant.id ? updated : t)) ?? null);
  }

  return (
    <div>
      <PageHeader title="Tenants" />

      {forbidden ? (
        <EmptyState>Only platform admins can manage tenants.</EmptyState>
      ) : tenants === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : tenants.length === 0 ? (
        <EmptyState>No tenants yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {tenants.map((t) => (
            <div key={t.id} className="flex items-center justify-between rounded-lg border p-4">
              <h2 className="font-semibold">{t.name}</h2>
              <select
                className="rounded-lg border px-3 py-2"
                value={t.industry_id ?? ""}
                onChange={(e) => assign(t, Number(e.target.value))}
              >
                <option value="" disabled>
                  Choose industry
                </option>
                {industries.map((i) => (
                  <option key={i.id} value={i.id}>
                    {i.name}
                  </option>
                ))}
              </select>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
