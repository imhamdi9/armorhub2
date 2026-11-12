"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { createRole, deleteRole, getRoles, updateRole, type RoleInfo } from "@/services/admin.service";

const PERMISSION_LABELS: Record<string, string> = {
  "dashboard.view": "See the dashboard", "orders.view": "See orders", "products.view": "See products", "products.create": "Create products",
  "products.update": "Edit products", "partners.view": "See partners", "partners.update": "Edit partners", "companies.view": "See the company",
  "commissions.view": "See commissions", "pricing.calculate": "Calculate prices", "vehicle-makes.view": "See makes and models",
};

function PermissionPicker({ grantable, value, onChange }: { grantable: string[]; value: string[]; onChange: (v: string[]) => void }) {
  return (
    <div className="grid gap-1 sm:grid-cols-2">
      {grantable.map((p) => (
        <label key={p} className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={value.includes(p)} onChange={(e) => onChange(e.target.checked ? [...value, p] : value.filter((x) => x !== p))} />
          {tr(PERMISSION_LABELS[p] ?? p)}
        </label>
      ))}
    </div>
  );
}

/** Custom roles are bundles of everyday permissions for company staff. Admin, destructive and money-moving powers cannot be granted. */
export default function RolesPage() {
  const [roles, setRoles] = useState<RoleInfo[] | null>(null);
  const [grantable, setGrantable] = useState<string[]>([]);
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);
  const [name, setName] = useState("");
  const [perms, setPerms] = useState<string[]>([]);
  const [edits, setEdits] = useState<Record<number, string[]>>({});

  useEffect(() => {
    getRoles().then((r) => { setRoles(r.roles); setGrantable(r.grantable); setEdits({}); }).catch(() => setForbidden(true));
  }, [tick]);

  async function run(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!roles) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-3xl">
      <PageHeader title="Roles" />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Create your own roles for company staff and choose what each may do. Built-in roles cannot be changed, and administrator, delete and payment powers cannot be given to a custom role.")}</p>
      {error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      <form onSubmit={(e) => { e.preventDefault(); run(async () => { await createRole(name, perms); setName(""); setPerms([]); }); }} className="mb-6 space-y-3 rounded-xl border p-5">
        <h2 className="font-bold">{tr("New role")}</h2>
        <Input aria-label={tr("Role name")} placeholder={tr("Role name (lowercase, e.g. sales-viewer)")} value={name} onChange={(e) => setName(e.target.value)} required />
        <PermissionPicker grantable={grantable} value={perms} onChange={setPerms} />
        <Button type="submit">{tr("Create role")}</Button>
      </form>

      <div className="space-y-3">
        {roles.map((r) => (
          <article key={r.id} className="rounded-xl border p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <h2 className="font-bold">{r.name} {r.built_in && <Badge variant="outline">{tr("Built-in")}</Badge>}</h2>
              <span className="text-xs text-muted-foreground">{r.users} {tr("people")}</span>
            </div>
            {r.built_in ? (
              <p className="mt-2 text-sm text-muted-foreground">{r.permissions.length} {tr("permissions")}</p>
            ) : (
              <div className="mt-3 space-y-3">
                <PermissionPicker grantable={grantable} value={edits[r.id] ?? r.permissions} onChange={(v) => setEdits((e) => ({ ...e, [r.id]: v }))} />
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => run(() => updateRole(r.id, edits[r.id] ?? r.permissions))}>{tr("Save")}</Button>
                  <Button size="sm" variant="outline" onClick={() => run(() => deleteRole(r.id))}>{tr("Delete")}</Button>
                </div>
              </div>
            )}
          </article>
        ))}
      </div>
    </div>
  );
}
