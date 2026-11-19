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
  viewAsUser,
  type Company,
  type UserFilters,
} from "@/services/platform.service";
import type { Partner, StaffUser } from "@/types/platform";
import { formatDateTime, tr } from "@/lib/i18n";
import { downloadExport, getRoles } from "@/services/admin.service";
import { LoadingBlock } from "@/components/ui/loading-block";

const ROLE_LABELS: Record<string, string> = {
  "platform-admin": "Platform admin",
  "company-owner": "Market admin",
  "partner-manager": "Partner manager",
  technician: "Technician",
  customer: "Customer",
  seller: "Company owner (seller)",
  "warehouse-staff": "Warehouse staff",
  "operations-agent": "Operations agent",
  "support-agent": "Support agent",
  "catalog-reviewer": "Catalog reviewer",
  "marketing-manager": "Marketing manager",
};

/** A change to someone's account waiting for a second click: deleting, or giving them another role. */
type Pending = { id: number; kind: "delete" } | { id: number; kind: "role"; role: string };

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
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState<number | null>(null);
  const [loadingMore, setLoadingMore] = useState(false);
  const [companies, setCompanies] = useState<Company[]>([]);
  const [partners, setPartners] = useState<Partner[]>([]);
  const [search, setSearch] = useState("");
  const [filters, setFilters] = useState<Omit<UserFilters, "search" | "scope" | "page">>({});
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState<Pending | null>(null);
  const [tick, setTick] = useState(0);
  const [form, setForm] = useState({
    name: "", email: "", password: "", role: roles[roles.length - 1], company_id: "", partner_id: "",
  });

  // A new search, filter or change starts again from the first page.
  useEffect(() => {
    let live = true;
    getUsers(search || undefined, scope, filters)
      .then((r) => { if (live) { setUsers(r.data); setPage(r.current_page); setLastPage(r.last_page); setTotal(r.total ?? r.data.length); } })
      .catch(() => { if (live) setUsers([]); });

    return () => { live = false; };
  }, [search, tick, scope, filters]);

  async function loadMore() {
    setLoadingMore(true);
    try {
      const r = await getUsers(search || undefined, scope, { ...filters, page: page + 1 });
      setUsers((cur) => [...(cur ?? []), ...r.data]);
      setPage(r.current_page);
      setLastPage(r.last_page);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setLoadingMore(false);
    }
  }

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
    setPending(null);
    try {
      await action();
      setTick((t) => t + 1);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function viewAs(u: StaffUser) {
    setError(null);
    try {
      await viewAsUser(u.id);
      // A full load, so every screen starts again as that person.
      window.location.assign("/dashboard");
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  const filtering = !!search || Object.values(filters).some(Boolean);
  const setFilter = <K extends keyof typeof filters>(key: K, value: (typeof filters)[K]) => { setUsers(null); setFilters((f) => ({ ...f, [key]: value || undefined })); };

  return (
    <div>
      <PageHeader
        title={tr(scope === "platform" ? "Platform team" : scope === "users" ? "Users" : "Team")}
        action={
          <div className="flex gap-2">
            {isAdmin && <Button variant="outline" onClick={() => downloadExport("users").catch((err) => setError(errorMessage(err)))}>{tr("Export CSV")}</Button>}
            <Button onClick={() => setShowForm((v) => !v)}>{tr("Add member")}</Button>
          </div>
        }
      />

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

      <div className="mb-4 flex flex-wrap items-center gap-2">
        <Input className="max-w-xs" placeholder={tr("Search by name or email")} value={search} onChange={(e) => { setUsers(null); setSearch(e.target.value); }} />
        {isAdmin && (
          <>
            <select aria-label={tr("Filter by role")} className="rounded-lg border px-3 py-2 text-sm" value={filters.role ?? ""} onChange={(e) => setFilter("role", e.target.value)}>
              <option value="">{tr("Any role")}</option>
              {roles.map((r) => <option key={r} value={r}>{tr(ROLE_LABELS[r] ?? r)}</option>)}
            </select>
            <select aria-label={tr("Filter by status")} className="rounded-lg border px-3 py-2 text-sm" value={filters.status ?? ""} onChange={(e) => setFilter("status", e.target.value as "active" | "inactive")}>
              <option value="">{tr("Any status")}</option>
              <option value="active">{tr("Active")}</option>
              <option value="inactive">{tr("Deactivated")}</option>
            </select>
            <select aria-label={tr("Filter by company")} className="rounded-lg border px-3 py-2 text-sm" value={filters.company_id ?? ""} onChange={(e) => setFilter("company_id", e.target.value ? Number(e.target.value) : undefined)}>
              <option value="">{tr("Any company")}</option>
              {companies.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={!!filters.guests} onChange={(e) => setFilter("guests", e.target.checked)} />
              {tr("Include guest shoppers")}
            </label>
            {filtering && <Button size="sm" variant="ghost" onClick={() => { setUsers(null); setSearch(""); setFilters({}); }}>{tr("Clear filters")}</Button>}
          </>
        )}
      </div>
      {total !== null && users !== null && <p className="mb-3 text-sm text-muted-foreground" data-testid="user-count">{total === 1 ? tr("1 person") : tr("{n} people", { n: total })}</p>}
      {!showForm && error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      {users === null ? (
        <LoadingBlock />
      ) : users.length === 0 ? (
        <EmptyState>{tr("No team members found.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {users.map((u) => (
            <div key={u.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
              <div>
                <p className="font-semibold">
                  {u.name} {!u.is_active && <Badge variant="outline">{tr("Deactivated")}</Badge>} {u.is_guest && <Badge variant="outline">{tr("Guest")}</Badge>}
                </p>
                <p className="text-sm text-muted-foreground">
                  {u.email} · {[u.company?.name, u.partner?.name].filter(Boolean).join(" / ") || "—"}
                </p>
                {isAdmin && <p className="text-xs text-muted-foreground">{u.last_login_at ? tr("Last signed in {when}", { when: formatDateTime(u.last_login_at) }) : tr("Never signed in")}</p>}
              </div>

              {pending?.id === u.id ? (
                <div className="flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 p-2 text-sm" role="alertdialog" aria-label={tr("Confirm")}>
                  <span>
                    {pending.kind === "delete"
                      ? tr("Delete {name} ({email}) for good? This cannot be undone.", { name: u.name, email: u.email })
                      : tr("Change {name} to {role}? Their access changes straight away.", { name: u.name, role: tr(ROLE_LABELS[pending.role] ?? pending.role) })}
                  </span>
                  <Button size="sm" onClick={() => act(() => (pending.kind === "delete" ? deleteUser(u.id) : updateUser(u.id, { role: pending.role })))}>
                    {pending.kind === "delete" ? tr("Yes, delete") : tr("Yes, change")}
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => setPending(null)}>{tr("Cancel")}</Button>
                </div>
              ) : (
                <div className="flex flex-wrap items-center gap-2">
                  <select
                    aria-label={`${tr("Role")}: ${u.name}`}
                    className="rounded-lg border px-2 py-1 text-sm"
                    value={u.role ?? ""}
                    disabled={!u.manageable}
                    onChange={(e) => setPending({ id: u.id, kind: "role", role: e.target.value })}
                  >
                    {[...new Set([...(u.role ? [u.role] : []), ...roles])].map((r) => (
                      <option key={r} value={r} disabled={!roles.includes(r)}>{tr(ROLE_LABELS[r] ?? r)}</option>
                    ))}
                  </select>
                  {isAdmin && u.is_active && u.role !== "platform-admin" && u.id !== me?.id && (
                    <Button size="sm" variant="outline" onClick={() => viewAs(u)} title={tr("See the platform exactly as this person sees it (read-only)")}>{tr("View as")}</Button>
                  )}
                  {u.manageable && (
                    <>
                      <Button size="sm" variant="outline" onClick={() => act(() => updateUser(u.id, { is_active: !u.is_active }))}>
                        {tr(u.is_active ? "Deactivate" : "Activate")}
                      </Button>
                      <Button size="sm" variant="outline" onClick={() => setPending({ id: u.id, kind: "delete" })}>{tr("Delete")}</Button>
                    </>
                  )}
                </div>
              )}
            </div>
          ))}
          {page < lastPage && (
            <div className="pt-2 text-center">
              <Button variant="outline" disabled={loadingMore} onClick={loadMore}>{tr(loadingMore ? "Loading..." : "Show more")}</Button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
