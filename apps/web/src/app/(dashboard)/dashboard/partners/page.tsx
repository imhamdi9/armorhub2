"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { hasRole } from "@/lib/navigation";
import {
  createPartner,
  deletePartner,
  getPartners,
  updatePartner,
} from "@/services/platform.service";
import type { Partner } from "@/types/platform";

const FIELDS = ["name", "city", "country", "phone", "latitude", "longitude"] as const;
type Draft = Record<(typeof FIELDS)[number], string>;
const EMPTY: Draft = { name: "", city: "", country: "", phone: "", latitude: "", longitude: "" };

export default function PartnersPage() {
  const user = useCurrentUser();
  const canManage = hasRole(user, "platform-admin", "partner-manager");
  const [partners, setPartners] = useState<Partner[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [search, setSearch] = useState("");
  const [editing, setEditing] = useState<number | "new" | null>(null);
  const [form, setForm] = useState<Draft>(EMPTY);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getPartners({ ...(search ? { search } : {}), ...(canManage ? { include_inactive: 1 } : {}) })
      .then(setPartners)
      .catch(() => setForbidden(true));
  }, [search, canManage]);

  function startEdit(p: Partner & { latitude?: number | null; longitude?: number | null }) {
    setEditing(p.id);
    setForm({
      name: p.name,
      city: p.city ?? "",
      country: p.country ?? "",
      phone: p.phone ?? "",
      latitude: p.latitude == null ? "" : String(p.latitude),
      longitude: p.longitude == null ? "" : String(p.longitude),
    });
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const body = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ""));
    try {
      if (editing === "new") {
        const created = await createPartner(body);
        setPartners((prev) => [...(prev ?? []), created]);
      } else if (editing !== null) {
        const updated = await updatePartner(editing, body);
        setPartners((prev) => prev?.map((p) => (p.id === editing ? updated : p)) ?? null);
      }
      setEditing(null);
      setForm(EMPTY);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function toggle(p: Partner & { is_active?: boolean }) {
    const updated = await updatePartner(p.id, { is_active: !(p.is_active ?? true) });
    setPartners((prev) => prev?.map((x) => (x.id === p.id ? updated : x)) ?? null);
  }

  async function remove(p: Partner) {
    setError(null);
    try {
      await deletePartner(p.id);
      setPartners((prev) => prev?.filter((x) => x.id !== p.id) ?? null);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div>
      <PageHeader
        title="Partners"
        action={canManage && <Button onClick={() => { setEditing("new"); setForm(EMPTY); }}>Add partner</Button>}
      />

      {editing !== null && (
        <form onSubmit={onSubmit} className="mb-6 space-y-3 rounded-lg border p-4">
          {FIELDS.map((key) => (
            <div key={key}>
              <Label htmlFor={key} className="capitalize">{key}</Label>
              <Input
                id={key}
                type={key === "latitude" || key === "longitude" ? "number" : "text"}
                step="any"
                required={key === "name" || key === "city" || key === "country"}
                value={form[key]}
                onChange={(e) => setForm((f) => ({ ...f, [key]: e.target.value }))}
              />
            </div>
          ))}
          <p className="text-xs text-muted-foreground">
            Latitude and longitude let customers be matched with this partner when they are nearby.
          </p>
          {error && <p className="text-sm text-red-500">{error}</p>}
          <div className="flex gap-2">
            <Button type="submit">Save</Button>
            <Button type="button" variant="outline" onClick={() => setEditing(null)}>Cancel</Button>
          </div>
        </form>
      )}

      <Input className="mb-4 max-w-sm" placeholder="Search partners" value={search} onChange={(e) => setSearch(e.target.value)} />
      {editing === null && error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {forbidden ? (
        <EmptyState>You do not have access to partners.</EmptyState>
      ) : partners === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : partners.length === 0 ? (
        <EmptyState>No partners found.</EmptyState>
      ) : (
        <div className="space-y-3">
          {partners.map((p) => {
            const active = (p as Partner & { is_active?: boolean }).is_active ?? true;
            return (
              <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                <div>
                  <h2 className="font-semibold">
                    {p.name} {!active && <Badge variant="outline">Inactive</Badge>}
                  </h2>
                  <p className="text-sm text-muted-foreground">
                    {[p.city, p.country, p.phone].filter(Boolean).join(" · ")}
                  </p>
                </div>
                {canManage && (
                  <div className="flex gap-2">
                    <Button size="sm" variant="outline" onClick={() => startEdit(p)}>Edit</Button>
                    <Button size="sm" variant="outline" onClick={() => toggle(p)}>{active ? "Deactivate" : "Activate"}</Button>
                    {hasRole(user, "platform-admin") && (
                      <Button size="sm" variant="outline" onClick={() => remove(p)}>Delete</Button>
                    )}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
