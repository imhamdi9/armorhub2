"use client";

import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { CallBox, field, Note, ProofGallery, Section, StatusBadge } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { askLocation, DISPUTE_TYPES } from "@/lib/installation";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { disputeCentreJob, getCentreJob, sendEvidence, sendProof, startCentreJob, type CentreJob } from "@/services/installation.service";

export default function CentreJobPage() {
  const id = Number(useParams<{ id: string }>().id);
  const [job, setJob] = useState<CentreJob | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [where, setWhere] = useState<{ latitude: number; longitude: number } | null>(null);
  const [dType, setDType] = useState("other");
  const [dText, setDText] = useState("");
  const [disputing, setDisputing] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);

  const load = useCallback(() => getCentreJob(id).then(setJob).catch((e) => setError(errorMessage(e))), [id]);
  useEffect(() => { load(); }, [load]);

  async function run(fn: () => Promise<unknown>) {
    setBusy(true); setError(null); setMsg(null);
    try { await fn(); await load(); } catch (e) { setError(errorMessage(e)); } finally { setBusy(false); }
  }

  if (error && !job) return <p role="alert" className="text-red-600">{error}</p>;
  if (!job) return <p className="text-muted-foreground">{tr("Loading...")}</p>;
  const cur = job.currency ?? "EGP";
  const money = { held: "Held by the platform", available: "Available in your wallet", void: "Refunded to the customer" } as Record<string, string>;

  return (
    <div className="max-w-3xl">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2"><h1 className="text-3xl font-bold">{job.subject}</h1><StatusBadge status={job.status} side="centre" /></div>
      <p className="mb-1 text-muted-foreground">{job.parts.join(" · ")}</p>
      {job.notes && <p className="mb-3 text-sm">{job.notes}</p>}
      {error && <p role="alert" className="mb-3 text-sm text-red-600">{error}</p>}
      {msg && <Note tone="ok">{msg}</Note>}

      <Section title="Money">
        <p>{tr("Price")}: {formatMoney(job.price, cur)} · {tr("platform fee")} {job.fee_percent}% ({formatMoney(job.fee_amount, cur)})</p>
        <p className="text-lg font-black">{tr("You earn")}: {formatMoney(job.you_earn, cur)}</p>
        {job.money && <p className="text-sm text-muted-foreground">{tr(money[job.money] ?? job.money)}</p>}
        {job.status === "awaiting_payment" && <Note>{tr("The customer accepted your offer and has not paid yet. You will see their details after they pay.")}</Note>}
        {job.status === "proof_submitted" && job.objection_deadline && <Note>{tr("Waiting for the customer. The payment is released automatically after")} {formatDateTime(job.objection_deadline)} {tr("unless they open a dispute.")}</Note>}
      </Section>

      {job.customer && (
        <Section title="Customer">
          <p className="text-lg font-medium">{job.customer.name}{job.customer.city ? ` · ${job.customer.city}` : ""}</p>
          <div className="mt-3 grid gap-3 md:grid-cols-2">
            <Button variant="outline" render={<a href={job.customer.maps_url} target="_blank" rel="noreferrer" />}>{tr("Open the place in Google Maps")}</Button>
            <CallBox call={job.customer.call} />
          </div>
          <Note tone="warn">{tr("The platform warranty and your payment are protected only while the job stays on the platform. Do not arrange payment or work outside it: it can get your account suspended.")}</Note>
        </Section>
      )}

      {job.can_start && <Button className="mb-6" disabled={busy} onClick={() => run(() => startCentreJob(job.id))}>{tr("Start the work")}</Button>}

      {job.can_add_proof && (
        <Section title="Proof of the work">
          <p className="mb-3 text-sm text-muted-foreground">{tr("Take photos with your phone camera: before and after the work, and write the serial or warranty number of the part. The customer then has 48 hours to confirm or object.")}</p>
          <form className="space-y-3" onSubmit={(e) => {
            e.preventDefault(); const f = new FormData(e.currentTarget);
            if (where) { f.set("latitude", String(where.latitude)); f.set("longitude", String(where.longitude)); }
            f.set("taken_at", new Date().toISOString());
            run(async () => { await sendProof(job.id, f); (e.target as HTMLFormElement).reset(); setMsg(tr("Proof sent.")); });
          }}>
            <div><Label htmlFor="before">{tr("Before photos")}</Label><input id="before" name="before[]" type="file" accept="image/*" capture="environment" multiple className="block text-sm" /></div>
            <div><Label htmlFor="after">{tr("After photos")}</Label><input id="after" name="after[]" type="file" accept="image/*" capture="environment" multiple className="block text-sm" /></div>
            <div><Label htmlFor="serial">{tr("Serial or warranty number")}</Label><Input id="serial" name="serial" maxLength={120} /></div>
            <div><Label htmlFor="pnote">{tr("Note (optional)")}</Label><Input id="pnote" name="note" maxLength={300} /></div>
            <div className="flex flex-wrap items-center gap-3">
              <Button type="button" variant="outline" onClick={async () => { try { setWhere(await askLocation()); } catch { setError(tr("We could not get your location.")); } }}>{tr("Attach my location")}</Button>
              {where && <span className="text-sm text-green-700">✓ {tr("Location attached")}</span>}
              <Button type="submit" disabled={busy}>{tr("Send proof")}</Button>
            </div>
          </form>
          <div className="mt-4"><ProofGallery proofs={job.proofs.filter((p) => p.side === "centre")} /></div>
        </Section>
      )}

      {!job.can_add_proof && job.proofs.length > 0 && <Section title="Proof of the work"><ProofGallery proofs={job.proofs} /></Section>}

      {job.status === "disputed" && job.dispute && (
        <Section title="Dispute">
          <p className="text-sm">{job.dispute.description}</p>
          <Note tone="warn">{tr("The payment is on hold. Add your evidence: photos or a note.")}</Note>
          <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); const f = new FormData(e.currentTarget); run(async () => { await sendEvidence(`/partner/installation-jobs/${job.id}/evidence`, f); (e.target as HTMLFormElement).reset(); setMsg(tr("Added.")); }); }}>
            <Input name="note" maxLength={300} placeholder={tr("A note")} />
            <input name="photos[]" type="file" accept="image/*" multiple aria-label={tr("Photos")} />
            <Button type="submit" variant="outline" disabled={busy}>{tr("Add evidence")}</Button>
          </form>
          <div className="mt-4"><ProofGallery proofs={job.proofs.filter((p) => p.kind === "evidence")} /></div>
        </Section>
      )}

      {job.dispute?.status === "resolved" && <Note>{tr("Decision")}: {tr(({ release: "The payment was released to your wallet.", refund_full: "The customer was refunded in full.", refund_partial: "A partial refund was decided." } as Record<string, string>)[job.dispute.decision ?? ""] ?? "")} {job.dispute.decision_note}</Note>}

      {job.can_dispute && !disputing && job.status !== "disputed" && <button type="button" className="text-sm underline" onClick={() => setDisputing(true)}>{tr("There is a problem with this job")}</button>}
      {disputing && (
        <Section title="Open a dispute">
          <div className="space-y-3">
            <select aria-label={tr("What is wrong?")} className={field} value={dType} onChange={(e) => setDType(e.target.value)}>{DISPUTE_TYPES.map(([v, l]) => <option key={v} value={v}>{tr(l)}</option>)}</select>
            <textarea aria-label={tr("Describe the problem")} rows={3} className="w-full rounded-[10px] border border-input bg-transparent p-2.5 text-sm" value={dText} onChange={(e) => setDText(e.target.value)} />
            <Button disabled={busy || dText.trim().length < 10} onClick={() => run(async () => { await disputeCentreJob(job.id, { type: dType, description: dText }); setDisputing(false); })}>{tr("Open dispute")}</Button>
          </div>
        </Section>
      )}
    </div>
  );
}
