"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { addToCart } from "@/services/cart.service";
import { imageSrc } from "@/lib/surfaces";
import { getRefundState, requestRefund, scheduleReturnPickup, uploadRefundPhoto, type RefundState } from "@/services/ops.service";

/** Cancelling a paid order or returning it, and where the customer's money stands. Goods only; services have their own protections. */
export function RefundPanel({ orderId, currency, onChange }: { orderId: number; currency: string; onChange?: () => void }) {
  const [s, setS] = useState<RefundState | null>(null);
  const [open, setOpen] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [day, setDay] = useState("");
  const [photos, setPhotos] = useState<string[]>([]);
  const [startedAt] = useState(() => Date.now());
  const dayAfter = (n: number) => new Date(startedAt + n * 86_400_000).toISOString().slice(0, 10);
  const load = () => getRefundState(orderId).then(setS).catch(() => setS(null));
  useEffect(() => { load(); }, [orderId]);

  if (!s) return null;
  if (s.refund_status) {
    return (
      <Note tone="ok">
        <span data-testid="refund-status">
          {s.refund_status === "pending" ? tr("Your refund of {amount} is being sent back to you.", { amount: formatMoney(s.refund_amount ?? 0, currency) }) : tr("Your refund of {amount} was sent.", { amount: formatMoney(s.refund_amount ?? 0, currency) })}
        </span>
      </Note>
    );
  }
  if (s.request?.status === "awaiting_pickup") {
    return (
      <section className="mb-6 rounded-lg border p-4 text-sm" data-testid="return-pickup">
        <p className="font-semibold">{tr("Your return was approved. Hand the parcel over to the courier.")}</p>
        <div className="my-3 rounded-lg bg-primary/5 p-3">
          <p className="text-muted-foreground">{tr("Give this code to whoever collects the parcel. Do not share it before.")}</p>
          <p className="mt-1 text-2xl font-bold tracking-[0.3em]" dir="ltr" data-testid="return-code">{s.request.return_code}</p>
        </div>
        {s.request.pickup_day ? (
          <p>{tr("Collection day")}: <b>{s.request.pickup_day}</b></p>
        ) : (
          <div className="flex flex-wrap items-end gap-2">
            <label className="text-xs text-muted-foreground">{tr("Choose a collection day")}
              <input type="date" className="mt-1 block h-10 rounded-[10px] border bg-background px-2 text-sm text-foreground" min={dayAfter(1)} max={dayAfter(7)} value={day} onChange={(e) => setDay(e.target.value)} data-testid="pickup-day" />
            </label>
            <Button size="sm" disabled={!day} onClick={async () => { setErr(null); try { await scheduleReturnPickup(orderId, day); await load(); } catch (x) { setErr(errorMessage(x)); } }}>{tr("Confirm day")}</Button>
          </div>
        )}
        {err && <Note tone="warn">{err}</Note>}
      </section>
    );
  }
  if (s.request?.status === "collected") return <Note tone="info"><span data-testid="refund-status">{tr("We collected your parcel and are inspecting it. Your refund follows once it passes.")}</span></Note>;
  if (s.request?.status === "requested") return <Note tone="info"><span data-testid="refund-status">{tr("We received your return request. We will answer soon, and the seller is not paid until we do.")}</span></Note>;

  return (
    <section className="mb-6 rounded-lg border p-4 text-sm" data-testid="refund-panel">
      {s.request?.status === "rejected" && <Note tone="warn">{s.request.inspection === "failed" ? tr("Your returned parcel did not pass inspection") : tr("Your last request was declined")}{s.request.note ? `: ${s.request.note}` : ""}</Note>}
      {s.can_request ? (
        open ? (
          <form
            className="grid gap-2"
            onSubmit={async (e) => {
              e.preventDefault();
              setErr(null);
              try { await requestRefund(orderId, String(new FormData(e.currentTarget).get("reason")), photos); setPhotos([]); setOpen(false); await load(); onChange?.(); } catch (x) { setErr(errorMessage(x)); }
            }}
          >
            <p className="font-semibold">{tr("Cancel or return this order")}</p>
            <p className="text-muted-foreground">{tr("Before it ships, you are refunded at once. After it ships, we review your request. You can return goods up to {days} days after delivery.", { days: s.return_days })}</p>
            {s.return_to && <p className="text-muted-foreground" data-testid="return-to">{s.return_to.kind === "armorhub" ? tr("Returned goods go back to the ArmorHub warehouse") : tr("Returned goods go back to the seller")}{s.return_to.city ? ` · ${s.return_to.city}` : ""}</p>}
            {err && <Note tone="warn">{err}</Note>}
            <textarea name="reason" required minLength={3} maxLength={300} className="rounded-[10px] border p-2" placeholder={tr("Why do you want to cancel or return it?")} />
            <div className="flex flex-wrap items-center gap-2" data-testid="refund-photos">
              {photos.map((p) => (
                // eslint-disable-next-line @next/next/no-img-element -- a picture the customer just uploaded
                <img key={p} src={imageSrc(p) ?? ""} alt="" className="size-14 rounded-lg border object-cover" />
              ))}
              {photos.length < 4 && (
                <label className="grid size-14 cursor-pointer place-items-center rounded-lg border border-dashed text-xl text-muted-foreground">+
                  <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" className="sr-only" aria-label={tr("Add a photo of the problem")}
                    onChange={async (e) => { const f = e.target.files?.[0]; e.target.value = ""; if (!f) return; try { const path = await uploadRefundPhoto(orderId, f); setPhotos((all) => [...all, path].slice(0, 4)); } catch (x) { setErr(errorMessage(x)); } }} />
                </label>
              )}
              <span className="text-xs text-muted-foreground">{tr("Photos of the problem help us decide faster (up to 4).")}</span>
            </div>
            <div className="flex gap-2"><Button type="submit">{tr("Send request")}</Button><Button type="button" variant="outline" onClick={() => setOpen(false)}>{tr("Cancel")}</Button></div>
          </form>
        ) : (
          <Button variant="outline" onClick={() => setOpen(true)} data-testid="refund-open">{tr("Cancel or return this order")}</Button>
        )
      ) : null}
    </section>
  );
}

/** Puts the same goods back in the cart, so a repeat purchase is one tap. */
export function BuyAgain({ productId, quantity }: { productId: number; quantity?: number }) {
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  return (
    <div className="mb-6">
      {err && <Note tone="warn">{err}</Note>}
      <Button
        variant="outline"
        disabled={busy}
        data-testid="buy-again"
        onClick={async () => {
          setBusy(true);
          setErr(null);
          try { await addToCart(productId, null, quantity ?? 1); window.location.assign("/dashboard/cart"); } catch (e) { setErr(errorMessage(e)); setBusy(false); }
        }}
      >
        {tr("Buy again")}
      </Button>
    </div>
  );
}
