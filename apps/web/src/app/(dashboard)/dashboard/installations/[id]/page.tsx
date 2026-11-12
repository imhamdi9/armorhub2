"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { CallBox, field, Note, ProofGallery, Section, Stars, StatusBadge, TierBadge } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { DISPUTE_TYPES } from "@/lib/installation";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import {
  acceptOffer, cancelInstallation, confirmInstallation, disputeInstallation, getInstallation, getLoyalty, payInstallation, reportContact, reviewInstallation, sendEvidence,
  type CustomerJob, type Offer,
} from "@/services/installation.service";

const BADGE: Record<string, string> = { closest: "Closest", fastest: "Fastest", best_rated: "Best rated" };

export default function InstallationDetailPage() {
  const id = Number(useParams<{ id: string }>().id);
  const [job, setJob] = useState<CustomerJob | null>(null);
  const [points, setPoints] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [choosing, setChoosing] = useState<Offer | null>(null);
  const [terms, setTerms] = useState(false);
  const [use, setUse] = useState(0);
  const [disputing, setDisputing] = useState(false);
  const [dType, setDType] = useState("defect");
  const [dText, setDText] = useState("");
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState("");
  const [note, setNote] = useState("");
  const [msg, setMsg] = useState<string | null>(null);

  const load = useCallback(() => getInstallation(id).then(setJob).catch((e) => setError(errorMessage(e))), [id]);
  useEffect(() => { load(); getLoyalty().then((l) => setPoints(l.points)).catch(() => undefined); }, [load]);

  async function run(fn: () => Promise<unknown>) {
    setBusy(true); setError(null); setMsg(null);
    try { await fn(); await load(); } catch (e) { setError(errorMessage(e)); } finally { setBusy(false); }
  }

  if (error && !job) return <p role="alert" className="text-red-600">{error}</p>;
  if (!job) return <p className="text-muted-foreground">{tr("Loading...")}</p>;
  const cur = job.currency ?? "EGP";
  const picking = job.status === "open" || job.status === "offers_received";

  return (
    <div className="max-w-3xl">
      <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 className="text-3xl font-bold">{job.subject}</h1>
        <StatusBadge status={job.status} />
      </div>
      <p className="mb-1 text-muted-foreground">{job.parts.join(" · ")}</p>
      {job.notes && <p className="mb-4 text-sm text-muted-foreground">{job.notes}</p>}
      {error && <p role="alert" className="mb-4 text-sm text-red-600">{error}</p>}
      {msg && <Note tone="ok">{msg}</Note>}

      {job.product && job.product.installation !== "none" && (() => {
        const needsOffer = job.product.installation === "required";
        const chosen = ["awaiting_payment", "paid", "in_progress", "proof_submitted", "released"].includes(job.status);

        return (
          <Section title="The product">
            <p className="font-medium">{job.product.name}</p>
            {job.order_id ? (
              <p className="mt-1 text-sm text-muted-foreground">{tr("You ordered this product.")} <Link href={`/dashboard/orders/${job.order_id}`} className="underline">{tr("See the order")}</Link></p>
            ) : needsOffer && !chosen ? (
              <p className="mt-1 text-sm text-muted-foreground" data-testid="buy-locked">{tr("This product is sold only together with an installation offer. Choose an offer below, then you can buy the product.")}</p>
            ) : (
              <Button className="mt-2" render={<Link href={`/dashboard/orders/new?product=${job.product.id}&installation_request=${job.id}`} />}>{tr("Buy the product now")}</Button>
            )}
          </Section>
        );
      })()}

      {picking && (
        <Section title="Offers">
          {job.offers && job.offers.length > 0 ? (
            <>
              <p className="mb-3 text-sm text-muted-foreground">{tr("Nearest centres first, then the best record. Names and phone numbers are shared only after you pay.")}</p>
              <ul className="space-y-3">
                {job.offers.map((o) => (
                  <li key={o.id} className="rounded-xl border p-4" data-testid="offer">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <p className="font-medium">{tr("Centre")} {o.centre} <TierBadge tier={o.tier} /></p>
                      <p className="text-xl font-black tracking-tight">{formatMoney(o.price, cur)}</p>
                    </div>
                    <p className="mt-1 flex flex-wrap items-center gap-x-3 text-sm text-muted-foreground">
                      {o.rating !== null ? <span><Stars value={o.rating} /> {o.rating.toFixed(1)} ({o.rating_count})</span> : <span>{tr("No ratings yet")}</span>}
                      <span>{o.completed_jobs} {tr("jobs done")}</span>
                      <span>≈ {o.distance_km} km{o.same_city ? ` · ${tr("same city")}` : ""}</span>
                      <span>{o.eta_days} {tr("days")}</span>
                      {o.avg_response_minutes !== null && <span>{tr("answers in about")} {o.avg_response_minutes} {tr("min")}</span>}
                    </p>
                    {o.badges.length > 0 && <p className="mt-2 flex gap-2">{o.badges.map((b) => <span key={b} className="rounded-full bg-muted px-2 py-0.5 text-xs">{tr(BADGE[b] ?? b)}</span>)}</p>}
                    {o.note && <p className="mt-2 text-sm">{o.note}</p>}
                    <Button className="mt-3" variant="outline" onClick={() => { setChoosing(o); setUse(0); setTerms(false); }}>{tr("Choose this offer")}</Button>
                  </li>
                ))}
              </ul>
            </>
          ) : <p className="text-sm text-muted-foreground">{tr("No offers yet. Nearby centres have been told. This page updates when they answer.")}</p>}

          {choosing && (
            <div className="mt-4 rounded-xl border-2 border-primary p-4" role="dialog" aria-label={tr("Confirm your choice")}>
              <p className="mb-2 font-medium">{tr("Centre")} {choosing.centre} · {formatMoney(choosing.price, cur)}</p>
              <ul className="mb-3 list-disc space-y-1 ps-5 text-sm">{job.terms.lines.map((l) => <li key={l}>{tr(l)}</li>)}</ul>
              {points > 0 && (
                <div className="mb-3"><Label htmlFor="pts">{tr("Use loyalty points (you have")} {points})</Label>
                  <Input id="pts" type="number" min={0} max={points} value={use} onChange={(e) => setUse(Math.max(0, Math.min(points, Number(e.target.value))))} />
                  <p className="mt-1 text-xs text-muted-foreground">{tr("The discount is paid by the platform and is never more than its fee.")}</p></div>
              )}
              <label className="mb-3 flex items-start gap-2 text-sm"><input type="checkbox" className="mt-1" checked={terms} onChange={(e) => setTerms(e.target.checked)} /><span>{tr("I accept these terms")}</span></label>
              <div className="flex gap-2">
                <Button disabled={!terms || busy} onClick={() => run(async () => { await acceptOffer(job.id, { offer_id: choosing.id, accept_terms: true, use_points: use }); setChoosing(null); })}>{tr("Accept offer")}</Button>
                <Button variant="outline" onClick={() => setChoosing(null)}>{tr("Cancel")}</Button>
              </div>
            </div>
          )}
        </Section>
      )}

      {job.status === "awaiting_payment" && (
        <Section title="Pay to confirm">
          <p>{tr("Price")}: {formatMoney(job.price ?? 0, cur)}</p>
          {!!job.discount && job.discount > 0 && <p className="text-green-700">{tr("Loyalty discount")}: −{formatMoney(job.discount, cur)}</p>}
          <p className="mt-1 text-lg font-black">{tr("You pay")}: {formatMoney(job.pay_amount ?? 0, cur)}</p>
          <Note>{tr("Your payment is held by the platform. The centre receives it only after the work is proven and you confirm it (or 48 hours pass without a dispute).")}</Note>
          <Button disabled={busy} onClick={() => run(async () => { const r = await payInstallation(job.id); if (r.checkout_url) window.location.assign(r.checkout_url); })}>{tr("Pay now")}</Button>
        </Section>
      )}

      {job.centre && job.centre.name && (
        <Section title="Your centre">
          <p className="text-lg font-medium">{job.centre.name} <TierBadge tier={job.centre.tier} /></p>
          <p className="text-sm text-muted-foreground">{[job.centre.address, job.centre.city].filter(Boolean).join(", ")}</p>
          <div className="mt-3 grid gap-3 md:grid-cols-2">
            {job.centre.maps_url && <Button variant="outline" render={<a href={job.centre.maps_url} target="_blank" rel="noreferrer" />}>{tr("Open in Google Maps")}</Button>}
            <CallBox call={job.centre.call} />
          </div>
          <Note tone="warn">{tr("The platform warranty covers only work arranged and paid through the platform. Never pay or arrange work outside it.")}</Note>
          {["paid", "in_progress", "proof_submitted", "disputed"].includes(job.status) && (
            <button type="button" className="text-sm underline" onClick={() => { const n = window.prompt(tr("What happened?")); if (n) run(async () => { await reportContact(job.id, n); setMsg(tr("Thank you. The platform will review this.")); }); }}>{tr("Report: the centre asked me to deal outside the platform")}</button>
          )}
        </Section>
      )}

      {["paid", "in_progress"].includes(job.status) && <Note>{tr("The payment is held safely. When the work is finished the centre uploads proof and you will be asked to confirm it.")}</Note>}

      {(job.proofs.length > 0 || job.status === "proof_submitted") && (
        <Section title="Proof of the work"><ProofGallery proofs={job.proofs} /></Section>
      )}

      {job.status === "proof_submitted" && (
        <Section title="Is the work done?">
          {job.objection_deadline && <Note tone="warn">{tr("Please check the work. If you do nothing, the payment is released to the centre after")} {formatDateTime(job.objection_deadline)}.</Note>}
          <div className="flex flex-wrap gap-2">
            <Button disabled={busy} onClick={() => run(() => confirmInstallation(job.id))}>{tr("Yes, confirm and release the payment")}</Button>
            <Button variant="outline" onClick={() => setDisputing(true)}>{tr("There is a problem")}</Button>
          </div>
        </Section>
      )}

      {["paid", "in_progress"].includes(job.status) && job.can_dispute && !disputing && (
        <button type="button" className="mb-6 text-sm underline" onClick={() => setDisputing(true)}>{tr("There is a problem with this job")}</button>
      )}

      {disputing && job.can_dispute && (
        <Section title="Open a dispute">
          <p className="mb-3 text-sm text-muted-foreground">{tr("The payment stays on hold. The platform will check the work and hear both sides before deciding.")}</p>
          <div className="space-y-3">
            <div><Label htmlFor="dt">{tr("What is wrong?")}</Label><select id="dt" className={field} value={dType} onChange={(e) => setDType(e.target.value)}>{DISPUTE_TYPES.map(([v, l]) => <option key={v} value={v}>{tr(l)}</option>)}</select></div>
            <div><Label htmlFor="dd">{tr("Describe the problem")}</Label><textarea id="dd" rows={3} minLength={10} className="w-full rounded-[10px] border border-input bg-transparent p-2.5 text-sm" value={dText} onChange={(e) => setDText(e.target.value)} /></div>
            <Button disabled={busy || dText.trim().length < 10} onClick={() => run(async () => { await disputeInstallation(job.id, { type: dType, description: dText }); setDisputing(false); })}>{tr("Open dispute")}</Button>
          </div>
        </Section>
      )}

      {job.status === "disputed" && job.dispute && (
        <Section title="Dispute">
          <p className="text-sm">{tr(DISPUTE_TYPES.find(([v]) => v === job.dispute?.type)?.[1] ?? "Something else")}: {job.dispute.description}</p>
          <Note tone="warn">{tr("The payment is on hold while the platform reviews. Add anything that helps: photos or a note.")}</Note>
          <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); const f = new FormData(e.currentTarget); run(async () => { await sendEvidence(`/installation-requests/${job.id}/evidence`, f); (e.target as HTMLFormElement).reset(); setMsg(tr("Added.")); }); }}>
            <Input name="note" maxLength={300} placeholder={tr("A note")} value={note} onChange={(e) => setNote(e.target.value)} />
            <input name="photos[]" type="file" accept="image/*" multiple aria-label={tr("Photos")} />
            <Button type="submit" variant="outline" disabled={busy}>{tr("Add evidence")}</Button>
          </form>
        </Section>
      )}

      {job.dispute && job.dispute.status === "resolved" && (
        <Note>{tr("Decision")}: {tr(({ release: "The payment was released to the centre.", refund_full: "You are refunded in full.", refund_partial: "Partial refund" } as Record<string, string>)[job.dispute.decision ?? ""] ?? "")}
          {job.dispute.refund_amount ? ` ${formatMoney(job.dispute.refund_amount, cur)}` : ""}. {job.dispute.decision_note}</Note>
      )}

      {job.refund && <Note>{tr("Refund")}: {formatMoney(job.refund.amount, cur)} · {job.refund.status === "refunded" ? `${tr("sent")} (${job.refund.reference})` : tr("being sent")}</Note>}

      {job.status === "released" && (
        <Section title="Completed">
          <p>{tr("The platform warranty on this job lasts until")} <strong>{job.warranty_ends_at ? formatDateTime(job.warranty_ends_at) : ""}</strong>.</p>
          {job.review ? <p className="mt-2 text-sm">{tr("Your rating")}: <Stars value={job.review.rating} /> {job.review.comment}</p>
            : job.can_review && (
              <div className="mt-3 space-y-2">
                <Label htmlFor="rate">{tr("Rate the centre")}</Label>
                <select id="rate" className={field} value={rating} onChange={(e) => setRating(Number(e.target.value))}>{[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{"★".repeat(n)}</option>)}</select>
                <Input aria-label={tr("Comment")} placeholder={tr("A comment (optional)")} maxLength={500} value={comment} onChange={(e) => setComment(e.target.value)} />
                <Button disabled={busy} onClick={() => run(async () => { await reviewInstallation(job.id, { rating, comment }); setMsg(tr("Thank you for rating. You earned loyalty points.")); })}>{tr("Send rating")}</Button>
              </div>
            )}
        </Section>
      )}

      {job.can_cancel && <Button variant="outline" disabled={busy} onClick={() => run(() => cancelInstallation(job.id))}>{tr("Cancel this request")}</Button>}
    </div>
  );
}
