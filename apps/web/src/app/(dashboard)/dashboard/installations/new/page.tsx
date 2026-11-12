"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { askLocation, WORK_TYPES } from "@/lib/installation";
import { tr } from "@/lib/i18n";
import { createInstallation } from "@/services/installation.service";

/** A request for help. The place is the phone's own position, shared only after the person allows it: it is never typed. */
export default function NewInstallationPage() {
  const router = useRouter();
  const [subject, setSubject] = useState("");
  const [parts, setParts] = useState("");
  const [workType, setWorkType] = useState("install");
  const [notes, setNotes] = useState("");
  const [consent, setConsent] = useState(false);
  const [where, setWhere] = useState<{ latitude: number; longitude: number } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function share() {
    setError(null);
    try {
      setWhere(await askLocation());
    } catch {
      setError(tr("We could not get your location. Allow location access in your browser and try again."));
    }
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!where) return setError(tr("Please share your location so we can find nearby centres."));
    setBusy(true);
    setError(null);
    try {
      const job = await createInstallation({
        subject, parts: parts.split("\n").map((p) => p.trim()).filter(Boolean), work_type: workType, notes: notes || null,
        latitude: where.latitude, longitude: where.longitude, location_consent: consent,
        ...(new URLSearchParams(window.location.search).get("product") ? { product_id: Number(new URLSearchParams(window.location.search).get("product")) } : {}),
      });
      router.push(`/dashboard/installations/${job.id}`);
    } catch (err) {
      setError(errorMessage(err));
      setBusy(false);
    }
  }

  return (
    <div className="max-w-xl">
      <PageHeader title="New installation request" />
      <Note>{tr("Centres see what must be done and roughly how far away you are. They never see your name, phone or exact place until you accept an offer and pay.")}</Note>
      <form onSubmit={submit} className="space-y-4">
        <div><Label htmlFor="subject">{tr("What is it for? (car or device model)")}</Label><Input id="subject" required maxLength={160} value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="Toyota Camry 2020" /></div>
        <div><Label htmlFor="parts">{tr("What must be done? One item per line")}</Label><textarea id="parts" required rows={3} className="w-full rounded-[10px] border border-input bg-transparent p-2.5 text-sm" value={parts} onChange={(e) => setParts(e.target.value)} /></div>
        <div><Label htmlFor="type">{tr("Type of work")}</Label>
          <select id="type" className={field} value={workType} onChange={(e) => setWorkType(e.target.value)}>{WORK_TYPES.map(([v, l]) => <option key={v} value={v}>{tr(l)}</option>)}</select></div>
        <div><Label htmlFor="notes">{tr("Notes (optional, no phone numbers or links)")}</Label><textarea id="notes" rows={2} maxLength={500} className="w-full rounded-[10px] border border-input bg-transparent p-2.5 text-sm" value={notes} onChange={(e) => setNotes(e.target.value)} /></div>

        <div className="rounded-xl border p-3 text-sm">
          <label className="flex items-start gap-2"><input type="checkbox" className="mt-1" checked={consent} onChange={(e) => setConsent(e.target.checked)} />
            <span>{tr("I allow ArmorHub to use my phone's location to find nearby centres. It is shown to a centre only after I accept its offer and pay.")}</span></label>
          <div className="mt-3 flex items-center gap-3">
            <Button type="button" variant="outline" disabled={!consent} onClick={share}>{tr("Share my location")}</Button>
            {where && <span className="text-green-700" data-testid="location-ok">✓ {tr("Location shared")}</span>}
          </div>
        </div>

        {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={busy || !consent || !where}>{tr("Send request")}</Button>
      </form>
    </div>
  );
}
