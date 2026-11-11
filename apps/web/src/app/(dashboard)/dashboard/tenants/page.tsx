"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import {
  createTenant,
  getIndustries,
  getTenants,
  updateTenant,
} from "@/services/platform.service";
import type { Industry } from "@/types/auth";
import type { Tenant } from "@/types/platform";
import { tr } from "@/lib/i18n";

const MARKETS = [
  { code: "SA", label: "Saudi Arabia" },
  { code: "EG", label: "Egypt" },
];

function MarketSelect({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    <select className="rounded-lg border px-3 py-2" value={value} onChange={(e) => onChange(e.target.value)} aria-label={tr("Country")}>
      <option value="">{tr("Default country")}</option>
      {MARKETS.map((m) => (
        <option key={m.code} value={m.code}>{tr(m.label)}</option>
      ))}
    </select>
  );
}

export default function TenantsPage() {
  const [tenants, setTenants] = useState<Tenant[] | null>(null);
  const [industries, setIndustries] = useState<Industry[]>([]);
  const [forbidden, setForbidden] = useState(false);
  const [name, setName] = useState("");
  const [industryId, setIndustryId] = useState("");
  const [market, setMarket] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getTenants().then(setTenants).catch(() => setForbidden(true));
    getIndustries().then(setIndustries);
  }, []);

  async function assign(tenant: Tenant, id: number) {
    const updated = await updateTenant(tenant.id, { industry_id: id });
    setTenants((prev) => prev?.map((t) => (t.id === tenant.id ? updated : t)) ?? null);
  }

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const created = await createTenant({ name, industry_id: Number(industryId), market: market || null });
      setTenants((prev) => [...(prev ?? []), created]);
      setName("");
      setIndustryId("");
      setMarket("");
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader title={tr("Markets")} />

      {!forbidden && (
        <form onSubmit={add} className="mb-6 flex gap-2">
          <Input placeholder={tr("New market name")} required value={name} onChange={(e) => setName(e.target.value)} />
          <select
            required
            className="rounded-lg border px-3 py-2"
            value={industryId}
            onChange={(e) => setIndustryId(e.target.value)}
          >
            <option value="" disabled>{tr("Industry")}</option>
            {industries.map((i) => (
              <option key={i.id} value={i.id}>{tr(i.name)}</option>
            ))}
          </select>
          <MarketSelect value={market} onChange={setMarket} />
          <Button type="submit">{tr("Add")}</Button>
        </form>
      )}
      {error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {forbidden ? (
        <EmptyState>{tr("Only platform admins can manage markets.")}</EmptyState>
      ) : tenants === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : tenants.length === 0 ? (
        <EmptyState>{tr("No markets yet. Add one above.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {tenants.map((t) => (
            <div key={t.id} className="flex items-center justify-between rounded-lg border p-4">
              <h2 className="font-semibold">{t.name}</h2>
              <MarketSelect
                value={t.market ?? ""}
                onChange={async (m) => {
                  const updated = await updateTenant(t.id, { market: m || null });
                  setTenants((prev) => prev?.map((x) => (x.id === t.id ? updated : x)) ?? null);
                }}
              />
              <select
                className="rounded-lg border px-3 py-2"
                value={t.industry_id ?? ""}
                onChange={(e) => assign(t, Number(e.target.value))}
              >
                <option value="" disabled>{tr("Choose industry")}</option>
                {industries.map((i) => (
                  <option key={i.id} value={i.id}>{tr(i.name)}</option>
                ))}
              </select>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
