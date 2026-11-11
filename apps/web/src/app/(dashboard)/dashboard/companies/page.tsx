"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { hasRole } from "@/lib/navigation";
import {
  type Company,
  createCompany,
  deleteCompany,
  getCompanies,
  getTenants,
  updateCompany,
} from "@/services/platform.service";
import type { Tenant } from "@/types/platform";

/** Companies are the businesses on the platform. Customers sign up under a company's ID. */
export default function CompaniesPage() {
  const user = useCurrentUser();
  const isAdmin = hasRole(user, "platform-admin");
  const [companies, setCompanies] = useState<Company[] | null>(null);
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [name, setName] = useState("");
  const [tenantId, setTenantId] = useState("");
  const [type, setType] = useState("partner");
  const [editing, setEditing] = useState<number | null>(null);
  const [draft, setDraft] = useState({ name: "", status: "active" });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getCompanies().then(setCompanies).catch(() => setCompanies([]));
    if (isAdmin) getTenants().then(setTenants).catch(() => setTenants([]));
  }, [isAdmin]);

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

  async function save(c: Company) {
    setError(null);
    try {
      const updated = await updateCompany(c.id, isAdmin ? draft : { name: draft.name });
      setCompanies((prev) => prev?.map((x) => (x.id === c.id ? updated : x)) ?? null);
      setEditing(null);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function remove(c: Company) {
    if (!window.confirm(`Delete ${c.name}?`)) return;
    try {
      await deleteCompany(c.id);
      setCompanies((prev) => prev?.filter((x) => x.id !== c.id) ?? null);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader title="Companies" />

      {isAdmin && (
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
      )}
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {companies === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : companies.length === 0 ? (
        <EmptyState>No companies yet. Create a tenant first, then add a company.</EmptyState>
      ) : (
        <div className="space-y-3">
          {companies.map((c) => (
            <div key={c.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
              {editing === c.id ? (
                <div className="flex flex-wrap gap-2">
                  <Input aria-label="Company name" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
                  {isAdmin && (
                    <select aria-label="Status" className="rounded-lg border px-3 py-2" value={draft.status} onChange={(e) => setDraft({ ...draft, status: e.target.value })}>
                      {["pending", "active", "inactive", "suspended"].map((s) => (
                        <option key={s} value={s}>{s}</option>
                      ))}
                    </select>
                  )}
                  <Button size="sm" onClick={() => save(c)}>Save</Button>
                  <Button size="sm" variant="outline" onClick={() => setEditing(null)}>Cancel</Button>
                </div>
              ) : (
                <div>
                  <h2 className="font-semibold">{c.name}</h2>
                  <p className="text-sm text-muted-foreground">{c.type} · {c.status}</p>
                </div>
              )}
              <div className="flex items-center gap-2">
                <span className="rounded bg-muted px-2 py-1 text-sm">
                  Company ID: <strong>{c.id}</strong>
                </span>
                {editing !== c.id && (
                  <Button size="sm" variant="outline" onClick={() => { setEditing(c.id); setDraft({ name: c.name, status: c.status }); }}>
                    Edit
                  </Button>
                )}
                {isAdmin && editing !== c.id && (
                  <Button size="sm" variant="outline" onClick={() => remove(c)}>Delete</Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
