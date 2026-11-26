"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { cancelInvitation, getInvitations, getInviteOptions, sendInvitation, type InvitationRow, type InviteOptions } from "@/services/platform.service";

const label = (role: string) => role.replace(/^dept-[a-z0-9-]+?-(?=[a-z0-9]+$)/, "").replace(/-/g, " ");

/** Invite people to your own team. You only see the roles your area allows. */
export default function InvitationsPage() {
  const user = useCurrentUser();
  const isAdmin = !!user?.roles?.includes("platform-admin");
  const [opts, setOpts] = useState<InviteOptions | null>(null);
  const [rows, setRows] = useState<InvitationRow[]>([]);
  const [tick, setTick] = useState(0);
  const [form, setForm] = useState({ email: "", role: "", department_id: "", as_head: false });
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    Promise.all([getInviteOptions(), getInvitations()]).then(([o, r]) => { if (live) { setOpts(o); setRows(r); } }).catch(() => { if (live) setOpts({ roles: [], departments: [] }); });
    return () => { live = false; };
  }, [tick]);

  if (!opts) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;
  const dept = opts.departments.find((d) => String(d.id) === form.department_id);
  const roles = dept ? dept.roles : opts.roles;
  const canInvite = opts.roles.length > 0 || opts.departments.some((d) => d.roles.length > 0);
  if (!canInvite) return <EmptyState>{tr("You cannot invite people.")}</EmptyState>;

  async function send(e: React.FormEvent) {
    e.preventDefault();
    setMsg(null);
    try {
      await sendInvitation({ email: form.email, role: form.role, department_id: form.department_id ? Number(form.department_id) : null, as_head: form.as_head || undefined });
      setForm({ email: "", role: "", department_id: form.department_id, as_head: false });
      setMsg(tr("Invitation sent. The link works for 7 days."));
      setTick((t) => t + 1);
    } catch (err) { setMsg(errorMessage(err)); }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="Invitations" />
      <form onSubmit={send} className="grid gap-3 rounded-xl border p-4 md:grid-cols-4">
        <div><Label htmlFor="iv-email">{tr("Email")}</Label><Input id="iv-email" type="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
        {opts.departments.length > 0 && (
          <div>
            <Label htmlFor="iv-dept">{tr("Department")}</Label>
            <select id="iv-dept" className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={form.department_id} onChange={(e) => setForm({ ...form, department_id: e.target.value, role: "" })}>
              {isAdmin || opts.roles.length > 0 ? <option value="">{tr("No department")}</option> : null}
              {opts.departments.map((d) => <option key={d.id} value={d.id}>{d.name}</option>)}
            </select>
          </div>
        )}
        <div>
          <Label htmlFor="iv-role">{tr("Role")}</Label>
          <select id="iv-role" required className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value })}>
            <option value="" disabled>{tr("Choose a role")}</option>
            {roles.map((r) => <option key={r} value={r}>{label(r)}</option>)}
          </select>
        </div>
        <div className="flex items-end gap-3">
          {isAdmin && dept && <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.as_head} onChange={(e) => setForm({ ...form, as_head: e.target.checked })} />{tr("Head of the department")}</label>}
          <Button type="submit">{tr("Send invitation")}</Button>
        </div>
      </form>
      {msg && <p className="text-sm" role="status">{msg}</p>}

      {rows.length === 0 ? <EmptyState>{tr("No invitations yet.")}</EmptyState> : (
        <ul className="divide-y rounded-xl border" data-testid="invitation-list">
          {rows.map((i) => (
            <li key={i.id} className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
              <span>{i.email} · {label(i.role)}</span>
              <span className="flex items-center gap-2">
                <Badge variant={i.status === "pending" ? "default" : "secondary"}>{tr(i.status === "pending" ? "Pending" : i.status === "accepted" ? "Accepted" : "Expired")}</Badge>
                {i.status === "pending" && <Button size="sm" variant="outline" onClick={() => cancelInvitation(i.id).then(() => setTick((t) => t + 1))}>{tr("Cancel")}</Button>}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
