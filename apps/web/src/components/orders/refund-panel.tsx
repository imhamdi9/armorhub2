"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { getRefundState, requestRefund, type RefundState } from "@/services/ops.service";

/** Cancelling a paid order or returning it, and where the customer's money stands. Goods only; services have their own protections. */
export function RefundPanel({ orderId, currency, onChange }: { orderId: number; currency: string; onChange?: () => void }) {
  const [s, setS] = useState<RefundState | null>(null);
  const [open, setOpen] = useState(false);
  const [err, setErr] = useState<string | null>(null);
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
  if (s.request?.status === "requested") return <Note tone="info"><span data-testid="refund-status">{tr("We received your return request. We will answer soon, and the seller is not paid until we do.")}</span></Note>;

  return (
    <section className="mb-6 rounded-lg border p-4 text-sm" data-testid="refund-panel">
      {s.request?.status === "rejected" && <Note tone="warn">{tr("Your last request was declined")}{s.request.note ? `: ${s.request.note}` : ""}</Note>}
      {s.can_request ? (
        open ? (
          <form
            className="grid gap-2"
            onSubmit={async (e) => {
              e.preventDefault();
              setErr(null);
              try { await requestRefund(orderId, String(new FormData(e.currentTarget).get("reason"))); setOpen(false); await load(); onChange?.(); } catch (x) { setErr(errorMessage(x)); }
            }}
          >
            <p className="font-semibold">{tr("Cancel or return this order")}</p>
            <p className="text-muted-foreground">{tr("Before it ships, you are refunded at once. After it ships, we review your request. You can return goods up to {days} days after delivery.", { days: s.return_days })}</p>
            {err && <Note tone="warn">{err}</Note>}
            <textarea name="reason" required minLength={3} maxLength={300} className="rounded-[10px] border p-2" placeholder={tr("Why do you want to cancel or return it?")} />
            <div className="flex gap-2"><Button type="submit">{tr("Send request")}</Button><Button type="button" variant="outline" onClick={() => setOpen(false)}>{tr("Cancel")}</Button></div>
          </form>
        ) : (
          <Button variant="outline" onClick={() => setOpen(true)} data-testid="refund-open">{tr("Cancel or return this order")}</Button>
        )
      ) : null}
    </section>
  );
}
