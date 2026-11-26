"use client";

import { useEffect, useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr, useLocale } from "@/lib/i18n";
import {
  createDepartmentRole, deleteDepartmentRole, getDepartmentMembers, openMarketDepartments, updateDepartmentRole, updateDepartmentScope, getDepartmentRoles, getDepartments, setDepartmentHead, updateDepartmentMember,
  type DepartmentMember, type DepartmentRole, type DepartmentRow,
} from "@/services/platform.service";

function Department({ d, isAdmin, onChanged }: { d: DepartmentRow; isAdmin: boolean; onChanged: () => void }) {
  const locale = useLocale();
  const [members, setMembers] = useState<DepartmentMember[]>([]);
  const [roles, setRoles] = useState<DepartmentRole[]>([]);
  const [tick, setTick] = useState(0);
  const [name, setName] = useState("");
  const [perms, setPerms] = useState<string[]>([]);
  const [roleEdits, setRoleEdits] = useState<Record<number, string[]>>({});
  const [scopeDraft, setScopeDraft] = useState<string[] | null>(null);
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    Promise.all([getDepartmentMembers(d.id), getDepartmentRoles(d.id)]).then(([m, r]) => { if (live) { setMembers(m); setRoles(r); } }).catch(() => undefined);
    return () => { live = false; };
  }, [d.id, tick]);

  const run = async (fn: () => Promise<unknown>) => {
    setMsg(null);
    try { await fn(); setTick((t) => t + 1); onChanged(); } catch (e) { setMsg(errorMessage(e)); }
  };

  return (
    <section className="rounded-xl border p-4" aria-label={d.name_en} data-testid="department">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-lg font-semibold">{locale === "ar" && d.name_ar ? d.name_ar : d.name_en}{d.market ? <span className="ms-2 text-sm font-normal text-muted-foreground">· {d.market}</span> : null}</h2>
        <Badge variant="outline">{d.head ? tr("Head: {name}", { name: d.head.name }) : tr("No head yet")}</Badge>
      </div>
      {msg && <p className="mt-2 text-sm text-red-600" role="alert">{msg}</p>}

      <h3 className="mt-4 text-sm font-semibold">{tr("Members")}</h3>
      {members.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No members yet. Invite people from the Invitations page.")}</p> : (
        <ul className="divide-y text-sm">
          {members.map((m) => (
            <li key={m.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
              <span>{m.name} <span className="text-muted-foreground">{m.email}</span> {m.is_head && <Badge>{tr("Head")}</Badge>}</span>
              <span className="flex items-center gap-2">
                {!m.is_head && roles.length > 0 && (
                  <select aria-label={`${tr("Role")} ${m.name}`} className="h-8 rounded-md border border-input bg-background px-2 text-sm" value={m.roles[0] ?? ""} onChange={(e) => run(() => updateDepartmentMember(d.id, m.id, { role: e.target.value }))}>
                    {roles.map((r) => <option key={r.id} value={r.name}>{r.label}</option>)}
                  </select>
                )}
                {!m.is_head && <Button size="sm" variant="outline" onClick={() => run(() => updateDepartmentMember(d.id, m.id, { is_active: !m.is_active }))}>{tr(m.is_active ? "Switch off" : "Switch on")}</Button>}
                {isAdmin && m.is_active && !m.is_head && <Button size="sm" variant="outline" onClick={() => run(() => setDepartmentHead(d.id, m.id))}>{tr("Make head")}</Button>}
              </span>
            </li>
          ))}
        </ul>
      )}

      {d.can_edit_scope && (
        <>
          <h3 className="mt-4 text-sm font-semibold">{tr("What this department covers")}</h3>
          <p className="text-xs text-muted-foreground">{tr("Roles of this department can only be built from these permissions. Change them whenever the work changes.")}</p>
          <fieldset className="mt-2 flex flex-wrap gap-3 text-sm">
            <legend className="sr-only">{tr("What this department covers")}</legend>
            {(d.ceiling ?? []).map((p) => {
              const cur = scopeDraft ?? d.scope;
              return <label key={p} className="flex items-center gap-1"><input type="checkbox" checked={cur.includes(p)} onChange={(e) => setScopeDraft(e.target.checked ? [...cur, p] : cur.filter((x) => x !== p))} />{p}</label>;
            })}
          </fieldset>
          <Button size="sm" className="mt-2" onClick={() => run(async () => { await updateDepartmentScope(d.id, scopeDraft ?? d.scope); setScopeDraft(null); })}>{tr("Save what this department covers")}</Button>
        </>
      )}

      <h3 className="mt-4 text-sm font-semibold">{tr("Roles in this department")}</h3>
      <ul className="mb-3 space-y-3 text-sm">
        {roles.map((r) => (
          <li key={r.id} className="rounded-lg border p-2">
            <strong>{r.label}</strong>
            <div className="mt-1 flex flex-wrap gap-3">
              {d.scope.map((p) => {
                const cur = roleEdits[r.id] ?? r.permissions;
                return <label key={p} className="flex items-center gap-1"><input type="checkbox" checked={cur.includes(p)} onChange={(e) => setRoleEdits({ ...roleEdits, [r.id]: e.target.checked ? [...cur, p] : cur.filter((x) => x !== p) })} />{p}</label>;
              })}
            </div>
            <div className="mt-2 flex gap-2">
              <Button size="sm" onClick={() => run(async () => { await updateDepartmentRole(d.id, r.id, roleEdits[r.id] ?? r.permissions); setRoleEdits({}); })}>{tr("Save")}</Button>
              <Button size="sm" variant="outline" onClick={() => run(() => deleteDepartmentRole(d.id, r.id))}>{tr("Delete")}</Button>
            </div>
          </li>
        ))}
      </ul>
      <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); run(async () => { await createDepartmentRole(d.id, { name: name.trim().toLowerCase().replace(/\s+/g, "-"), permissions: perms }); setName(""); setPerms([]); }); }}>
        <div><Label htmlFor={`rn-${d.id}`}>{tr("New role name")}</Label><Input id={`rn-${d.id}`} required value={name} onChange={(e) => setName(e.target.value)} /></div>
        <fieldset className="flex flex-wrap gap-3 text-sm">
          <legend className="sr-only">{tr("What this role may do")}</legend>
          {d.scope.map((p) => <label key={p} className="flex items-center gap-1"><input type="checkbox" checked={perms.includes(p)} onChange={(e) => setPerms(e.target.checked ? [...perms, p] : perms.filter((x) => x !== p))} />{p}</label>)}
        </fieldset>
        <Button size="sm" type="submit" disabled={perms.length === 0}>{tr("Create role")}</Button>
      </form>
    </section>
  );
}

/** Platform departments. A head runs only their own: its members and the roles they hand out. */
export default function DepartmentsPage() {
  const [rows, setRows] = useState<DepartmentRow[] | null>(null);
  const [isAdmin, setIsAdmin] = useState(false);
  const user = useCurrentUser();
  const isMarketAdmin = !!user?.roles?.includes("company-owner") && !isAdmin;
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let live = true;
    getDepartments().then((r) => { if (live) setRows(r); }).catch(() => { if (live) setRows([]); });
    return () => { live = false; };
  }, [tick]);
  useEffect(() => { import("@/services/auth.service").then(({ me }) => me()).then((u) => setIsAdmin(!!u.roles?.includes("platform-admin"))).catch(() => undefined); }, []);

  if (!rows) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  return (
    <div className="space-y-4">
      <PageHeader title="Departments" />
      {rows.length === 0 && isMarketAdmin ? (
        <div className="rounded-xl border p-4">
          <p className="mb-3 text-sm">{tr("Your market has no departments yet. Open the standard ones (customer service, procurement, sales, warehouse, disputes and more), then name a head for each.")}</p>
          <Button onClick={() => openMarketDepartments().then(() => setTick((t) => t + 1))}>{tr("Open the departments of my market")}</Button>
        </div>
      ) : rows.length === 0 ? <EmptyState>{tr("Only platform admins and department heads can open this page.")}</EmptyState> : rows.map((d) => <Department key={d.id} d={d} isAdmin={isAdmin} onChanged={() => setTick((t) => t + 1)} />)}
    </div>
  );
}
