"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { getOpenRequests, sendOffer, type OpenRequest } from "@/services/installation.service";

function OfferForm({ r, onDone }: { r: OpenRequest; onDone: () => void }) {
  const [price, setPrice] = useState(r.my_offer ? String(r.my_offer.price) : "");
  const [days, setDays] = useState(r.my_offer ? String(r.my_offer.eta_days) : "1");
  const [note, setNote] = useState(r.my_offer?.note ?? "");
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault(); setBusy(true); setErr(null);
    try { await sendOffer(r.id, { price: Number(price), eta_days: Number(days), note: note || undefined }); onDone(); } catch (x) { setErr(errorMessage(x)); } finally { setBusy(false); }
  }

  return (
    <form onSubmit={submit} className="mt-3 grid gap-2 md:grid-cols-[1fr_8rem_2fr_auto]">
      <Input aria-label={tr("Your price")} type="number" min={1} step="0.01" required placeholder={tr("Your price")} value={price} onChange={(e) => setPrice(e.target.value)} />
      <Input aria-label={tr("Days needed")} type="number" min={0} max={60} required placeholder={tr("Days")} value={days} onChange={(e) => setDays(e.target.value)} />
      <Input aria-label={tr("Note")} maxLength={300} placeholder={tr("Note (no phone numbers or links)")} value={note} onChange={(e) => setNote(e.target.value)} />
      <Button type="submit" disabled={busy}>{r.my_offer ? tr("Update offer") : tr("Send offer")}</Button>
      {err && <p role="alert" className="text-sm text-red-600 md:col-span-4">{err}</p>}
    </form>
  );
}

/** Requests from customers near the centre. The customer's identity and exact place stay hidden until they accept an offer and pay. */
export default function CentreRequestsPage() {
  const [rows, setRows] = useState<OpenRequest[] | null>(null);
  const [failed, setFailed] = useState(false);
  const load = () => getOpenRequests().then(setRows).catch(() => setFailed(true));
  useEffect(() => { load(); }, []);

  return (
    <div className="max-w-3xl">
      <PageHeader title="Installation requests" />
      <Note>{tr("You see what must be done and roughly how far away it is. Customer details are shared after the customer accepts your offer and pays. Do not put phone numbers or links in offers.")}</Note>
      {failed ? <EmptyState>{tr("Could not load requests.")}</EmptyState> : rows === null ? <p className="text-muted-foreground">{tr("Loading...")}</p>
        : rows.length === 0 ? <EmptyState>{tr("No open requests near you right now.")}</EmptyState>
        : (
          <ul className="space-y-3">
            {rows.map((r) => (
              <li key={r.id} className="rounded-xl border p-4" data-testid="open-request">
                <div className="flex flex-wrap justify-between gap-2"><p className="font-medium">{r.subject}</p><p className="text-sm text-muted-foreground">≈ {r.distance_km} km{r.area ? ` · ${r.area}` : ""}</p></div>
                <p className="text-sm text-muted-foreground">{tr(({ install: "Installation", repair: "Repair", maintenance: "Maintenance" } as Record<string, string>)[r.work_type] ?? r.work_type)}: {r.parts.join(" · ")}</p>
                {r.notes && <p className="mt-1 text-sm">{r.notes}</p>}
                <p className="mt-1 text-xs text-muted-foreground">{formatDateTime(r.created_at)}{r.my_offer ? ` · ${tr("your offer")}: ${formatMoney(r.my_offer.price, "EGP")}` : ""}</p>
                <OfferForm r={r} onDone={load} />
              </li>
            ))}
          </ul>
        )}
    </div>
  );
}
