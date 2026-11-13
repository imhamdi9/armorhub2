"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { hasRole } from "@/lib/navigation";
import {
  createUser,
  deleteUser,
  getCompanies,
  getPartners,
  getUsers,
  updateUser,
  type Company,
} from "@/services/platform.service";
import type { Partner, StaffUser } from "@/types/platform";
import { tr } from "@/lib/i18n";
import { getRoles } from "@/services/admin.service";

const ROLE_LABELS: Record<string, string> = {
  "platform-admin": "Platform admin",
  "company-owner": "Company owner",
  "partner-manager": "Partner manager",
  technician: "Technician",
  customer: "Customer",
  seller: "Seller",
};

/** The people list. For a platform admin it is split: "platform" is ArmorHub's own team, "users" is everyone else. */
export function TeamView({ scope }: { scope?: "platform" | "users" }) {
  const me = useCurrentUser();
  const isAdmin = hasRole(me, "platform-admin");
  const [customRoles, setCustomRoles] = useState<string[]>([]);
  const roles = isAdmin
    ? [...Object.keys(ROLE_LABELS), ...customRoles]
    : hasRole(me, "company-owner")
      ? ["company-owner", "partner-manager", "technician"]
      : ["technician"];

  const [users, setUsers] = useState<StaffUser[] | null>(null);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [partners, setPartners] = useState<Partner[]>([]);
  const [search, setSearch] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tick, setTick] = useState(0);
  const [form, setForm] = useState({
    name: "", email: "", password: "", role: roles[roles.length - 1], company_id: "", partner_id: "",
  });

  useEffect(() => {
    getUsers(search || undefined, scope).then((r) => setUsers(r.data)).catch(() => setUsers([]));
  }, [search, tick, scope]);

  useEffect(() => {
    if (me?.permissions?.includes("partners.view")) getPartners().then(setPartners).catch(() => setPartners([]));
    if (isAdmin) getRoles().then((r) => setCustomRoles(r.roles.filter((x) => !x.built_in).map((x) => x.name))).catch(() => undefined);
    // Only people who may see partners ask for them: owners can pick staff placement, others would just get an error.
    if (isAdmin) getCompanies().then(setCompanies).catch(() => setCompanies([]));
  }, [isAdmin, me]);

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await createUser({
        name: form.name,
        email: form.email,
        password: form.password,
        role: form.role,
        ...(form.company_id ? { company_id: Number(form.company_id) } : {}),
        ...(form.partner_id ? { partner_id: Number(form.partner_id) } : {}),
      });
      setForm({ ...form, name: "", email: "", password: "" });
      setShowForm(false);
      setTick((t) => t + 1);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function act(action: () => Promise<unknown>) {
    setError(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader title={tr(scope === "platform" ? "Platform team" : scope === "users" ? "Users" : "Team")} action={<Button onClick={() => setShowForm((v) => !v)}>{tr("Add member")}</Button>} />

      {showForm && (
        <form onSubmit={add} className="mb-6 grid grid-cols-2 gap-3 rounded-lg border p-4">
          <Input placeholder={tr("Full name")} required value={form.name} onChange={set("name")} />
          <Input placeholder={tr("Email")} type="email" required value={form.email} onChange={set("email")} />
          <Input placeholder={tr("Temporary password (min 8)")} type="text" minLength={8} required value={form.password} onChange={set("password")} />
          <select aria-label={tr("Role")} className="rounded-lg border px-3 py-2" value={form.role} onChange={set("role")}>
            {roles.map((r) => (
              <option key={r} value={r}>{tr(ROLE_LABELS[r] ?? r)}</option>
            ))}
          </select>
          {isAdmin && (
            <select aria-label={tr("Company")} className="rounded-lg border px-3 py-2" value={form.company_id} onChange={set("company_id")}>
              <option value="">{tr("No company")}</option>
              {companies.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>
          )}
          {!hasRole(me, "partner-manager") && (
            <select aria-label={tr("Partner")} className="rounded-lg border px-3 py-2" value={form.partner_id} onChange={set("partner_id")}>
              <option value="">{tr("No partner")}</option>
              {partners.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          )}
          {error && <p className="col-span-2 text-sm text-red-500">{error}</p>}
          <Button type="submit" className="col-span-2">{tr("Create account")}</Button>
          <p className="col-span-2 text-xs text-muted-foreground">
            Share the temporary password securely; they can change it with &ldquo;Forgot your password?&rdquo;.
          </p>
        </form>
      )}

      <Input className="mb-4 max-w-sm" placeholder={tr("Search by name or email")} value={search} onChange={(e) => setSearch(e.target.value)} />
      {!showForm && error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {users === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : users.length === 0 ? (
        <EmptyState>{tr("No team members found.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {users.map((u) => (
            <div key={u.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
              <div>
                <p className="font-semibold">
                  {u.name} {!u.is_active && <Badge variant="outline">{tr("Deactivated")}</Badge>}
                </p>
                <p className="text-sm text-muted-foreground">
                  {u.email} · {[u.company?.name, u.partner?.name].filter(Boolean).join(" / ") || "—"}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <select
                  aria-label={`${tr("Role")}: ${u.name}`}
                  className="rounded-lg border px-2 py-1 text-sm"
                  value={u.role ?? ""}
                  disabled={!u.manageable}
                  onChange={(e) => act(() => updateUser(u.id, { role: e.target.value }))}
                >
                  {[...new Set([...(u.role ? [u.role] : []), ...roles])].map((r) => (
                    <option key={r} value={r} disabled={!roles.includes(r)}>{tr(ROLE_LABELS[r] ?? r)}</option>
                  ))}
                </select>
                {u.manageable && (
                  <>
                    <Button size="sm" variant="outline" onClick={() => act(() => updateUser(u.id, { is_active: !u.is_active }))}>
                      {u.is_active ? "Deactivate" : "Activate"}
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => act(() => deleteUser(u.id))}>{tr("Delete")}</Button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
