"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import {
  type Company,
  createCompany,
  getCompanies,
  getTenants,
} from "@/services/platform.service";
import type { Tenant } from "@/types/platform";

/** Companies are the businesses on the platform. Customers sign up under a company's ID. */
export default function CompaniesPage() {
  const [companies, setCompanies] = useState<Company[] | null>(null);
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [name, setName] = useState("");
  const [tenantId, setTenantId] = useState("");
  const [type, setType] = useState("partner");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getCompanies().then(setCompanies).catch(() => setCompanies([]));
    getTenants().then(setTenants).catch(() => setTenants([]));
  }, []);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const created = await createCompany({ name, tenant_id: Number(tenantId), type });
      setCompanies((prev) => [...(prev ?? []), created]);
      setName("");
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader title="Companies" />

      <form onSubmit={add} className="mb-6 flex flex-wrap gap-2">
        <Input className="max-w-xs" placeholder="Company name" required value={name} onChange={(e) => setName(e.target.value)} />
        <select required className="rounded-lg border px-3 py-2" value={tenantId} onChange={(e) => setTenantId(e.target.value)}>
          <option value="" disabled>Tenant</option>
          {tenants.map((t) => (
            <option key={t.id} value={t.id}>{t.name}</option>
          ))}
        </select>
        <select className="rounded-lg border px-3 py-2" value={type} onChange={(e) => setType(e.target.value)}>
          <option value="partner">Partner / service provider</option>
          <option value="supplier">Supplier</option>
          <option value="platform">Storefront / platform</option>
        </select>
        <Button type="submit">Add company</Button>
      </form>
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {companies === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : companies.length === 0 ? (
        <EmptyState>No companies yet. Create a tenant first, then add a company.</EmptyState>
      ) : (
        <div className="space-y-3">
          {companies.map((c) => (
            <div key={c.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <h2 className="font-semibold">{c.name}</h2>
                <p className="text-sm text-muted-foreground">{c.type} · {c.status}</p>
              </div>
              <span className="rounded bg-muted px-2 py-1 text-sm">
                Company ID: <strong>{c.id}</strong>
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
