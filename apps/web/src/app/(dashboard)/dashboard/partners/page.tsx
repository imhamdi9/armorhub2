"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { hasRole } from "@/lib/navigation";
import { createPartner, deletePartner, getPartners } from "@/services/platform.service";
import type { Partner } from "@/types/platform";

export default function PartnersPage() {
  const user = useCurrentUser();
  const canManage = hasRole(user, "platform-admin", "partner-manager");
  const [partners, setPartners] = useState<Partner[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: "", city: "", country: "", phone: "", latitude: "", longitude: "" });
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getPartners().then(setPartners).catch(() => setForbidden(true));
  }, []);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const body = Object.fromEntries(Object.entries(form).filter(([, v]) => v !== ""));
      const created = await createPartner(body);
      setPartners((prev) => [...(prev ?? []), created]);
      setForm({ name: "", city: "", country: "", phone: "", latitude: "", longitude: "" });
      setShowForm(false);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function remove(id: number) {
    await deletePartner(id);
    setPartners((prev) => prev?.filter((p) => p.id !== id) ?? null);
  }

  return (
    <div>
      <PageHeader
        title="Partners"
        action={canManage && <Button onClick={() => setShowForm((v) => !v)}>Add partner</Button>}
      />

      {showForm && (
        <form onSubmit={onSubmit} className="mb-6 space-y-3 rounded-lg border p-4">
          {(["name", "city", "country", "phone", "latitude", "longitude"] as const).map((key) => (
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
          <Button type="submit">Save</Button>
        </form>
      )}

      {forbidden ? (
        <EmptyState>You do not have access to partners.</EmptyState>
      ) : partners === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : partners.length === 0 ? (
        <EmptyState>No partners yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {partners.map((p) => (
            <div key={p.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <h2 className="font-semibold">{p.name}</h2>
                <p className="text-sm text-muted-foreground">
                  {[p.city, p.country, p.phone].filter(Boolean).join(" · ")}
                </p>
              </div>
              {hasRole(user, "platform-admin") && (
                <Button size="sm" variant="outline" onClick={() => remove(p.id)}>Delete</Button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
