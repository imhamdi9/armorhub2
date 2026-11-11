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
import { tr } from "@/lib/i18n";

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
  const [profile, setProfile] = useState({ public_profile: false, description: "", logo_url: "" });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getPartners({ ...(search ? { search } : {}), ...(canManage ? { include_inactive: 1 } : {}) })
      .then(setPartners)
      .catch(() => setForbidden(true));
  }, [search, canManage]);

  function startEdit(p: Partner & { latitude?: number | null; longitude?: number | null }) {
    setEditing(p.id);
    setProfile({ public_profile: !!p.public_profile, description: p.description ?? "", logo_url: p.logo_url ?? "" });
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
    const body = {
      ...Object.fromEntries(Object.entries(form).filter(([, v]) => v !== "")),
      public_profile: profile.public_profile,
      description: profile.description || null,
      logo_url: profile.logo_url || null,
    };
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
        title={tr("Partners")}
        action={canManage && <Button onClick={() => { setEditing("new"); setForm(EMPTY); }}>{tr("Add partner")}</Button>}
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
          <fieldset className="space-y-3 rounded-lg border p-3">
            <legend className="px-1 text-sm font-medium">{tr("Public profile")}</legend>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" id="public_profile" checked={profile.public_profile} onChange={(e) => setProfile((v) => ({ ...v, public_profile: e.target.checked }))} />
              {tr("Show this partner on the public home page")}
            </label>
            <div>
              <Label htmlFor="description">{tr("Short description")}</Label>
              <textarea id="description" rows={2} maxLength={400} className="w-full rounded-lg border px-3 py-2" value={profile.description} onChange={(e) => setProfile((v) => ({ ...v, description: e.target.value }))} />
            </div>
            <div>
              <Label htmlFor="logo_url">{tr("Logo address (https)")}</Label>
              <Input id="logo_url" dir="ltr" placeholder="https://" value={profile.logo_url} onChange={(e) => setProfile((v) => ({ ...v, logo_url: e.target.value }))} />
            </div>
            <p className="text-xs text-muted-foreground">{tr("Only the name, city, description and logo are shown. Phone, address and location stay private.")}</p>
          </fieldset>
          <p className="text-xs text-muted-foreground">
            {tr("Latitude and longitude let customers be matched with this partner when they are nearby.")}
          </p>
          {error && <p className="text-sm text-red-500">{error}</p>}
          <div className="flex gap-2">
            <Button type="submit">{tr("Save")}</Button>
            <Button type="button" variant="outline" onClick={() => setEditing(null)}>{tr("Cancel")}</Button>
          </div>
        </form>
      )}

      <Input className="mb-4 max-w-sm" placeholder={tr("Search partners")} value={search} onChange={(e) => setSearch(e.target.value)} />
      {editing === null && error && <p className="mb-4 text-sm text-red-500">{error}</p>}

      {forbidden ? (
        <EmptyState>{tr("You do not have access to partners.")}</EmptyState>
      ) : partners === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : partners.length === 0 ? (
        <EmptyState>{tr("No partners found.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {partners.map((p) => {
            const active = (p as Partner & { is_active?: boolean }).is_active ?? true;
            return (
              <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                <div>
                  <h2 className="font-semibold">
                    {p.name} {!active && <Badge variant="outline">{tr("Inactive")}</Badge>}
                  </h2>
                  <p className="text-sm text-muted-foreground">
                    {[p.city, p.country, p.phone].filter(Boolean).join(" · ")}
                  </p>
                </div>
                {canManage && (
                  <div className="flex gap-2">
                    <Button size="sm" variant="outline" onClick={() => startEdit(p)}>{tr("Edit")}</Button>
                    <Button size="sm" variant="outline" onClick={() => toggle(p)}>{active ? "Deactivate" : "Activate"}</Button>
                    {hasRole(user, "platform-admin") && (
                      <Button size="sm" variant="outline" onClick={() => remove(p)}>{tr("Delete")}</Button>
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
