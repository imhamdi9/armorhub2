"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getSellerProfile, saveSellerProfile, type SellerProfile } from "@/services/platform.service";

/** What shoppers read on the shop page, and where returned goods go (private). */
export default function SellerProfilePage() {
  const [p, setP] = useState<SellerProfile | null>(null);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  useEffect(() => { getSellerProfile().then((r) => setP(r.profile)).catch(() => setP(null)); }, []);
  if (!p) return <LoadingBlock />;
  const set = (patch: Partial<SellerProfile>) => setP({ ...p, ...patch });

  return (
    <div className="max-w-2xl space-y-4">
      <PageHeader title={tr("Shop profile")} />
      {msg && <Note tone={msg.ok ? "ok" : "warn"}>{msg.text}</Note>}
      <form className="grid gap-3" data-testid="profile-form" onSubmit={async (e) => {
        e.preventDefault();
        setMsg(null);
        try { await saveSellerProfile({ about: p.about, city: p.city, hours: p.hours, ships_within_days: p.ships_within_days, return_address: p.return_address }); setMsg({ ok: true, text: tr("Saved") }); } catch (x) { setMsg({ ok: false, text: errorMessage(x) }); }
      }}>
        <label className="text-sm">{tr("About your shop")}<textarea className="mt-1 w-full rounded-[10px] border p-2" rows={3} maxLength={600} value={p.about ?? ""} onChange={(e) => set({ about: e.target.value })} /></label>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm">{tr("City")}<Input value={p.city ?? ""} onChange={(e) => set({ city: e.target.value })} /></label>
          <label className="text-sm">{tr("Opening hours")}<Input value={p.hours ?? ""} onChange={(e) => set({ hours: e.target.value })} /></label>
          <label className="text-sm">{tr("You ship within (days)")}<Input type="number" min={1} max={14} value={p.ships_within_days ?? ""} onChange={(e) => set({ ships_within_days: e.target.value ? Number(e.target.value) : null })} /></label>
        </div>
        <h2 className="mt-2 text-lg font-semibold">{tr("Where returned goods go (private)")}</h2>
        <div className="grid gap-3 sm:grid-cols-2">
          <label className="text-sm">{tr("Address")}<Input value={p.return_address.line1 ?? ""} onChange={(e) => set({ return_address: { ...p.return_address, line1: e.target.value } })} /></label>
          <label className="text-sm">{tr("City")}<Input value={p.return_address.city ?? ""} onChange={(e) => set({ return_address: { ...p.return_address, city: e.target.value } })} /></label>
        </div>
        <label className="text-sm">{tr("Notes for the courier")}<Input value={p.return_address.notes ?? ""} onChange={(e) => set({ return_address: { ...p.return_address, notes: e.target.value } })} /></label>
        <div><Button type="submit">{tr("Save")}</Button></div>
      </form>
    </div>
  );
}
