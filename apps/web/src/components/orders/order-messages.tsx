"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { getOrderMessages, sendOrderMessage, type OrderMessage } from "@/services/ops.service";

/** The conversation about one order. The customer can flag a message as a problem for ArmorHub. */
export function OrderMessages({ orderId, canFlag = false }: { orderId: number; canFlag?: boolean }) {
  const [rows, setRows] = useState<OrderMessage[] | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const [problem, setProblem] = useState(false);
  const [body, setBody] = useState("");
  const load = () => getOrderMessages(orderId).then(setRows).catch(() => setRows(null));
  useEffect(() => { load(); }, [orderId]);

  if (rows === null) return null;
  const who = (m: OrderMessage) => (m.mine ? tr("You") : m.side === "support" ? "ArmorHub" : m.side === "seller" ? tr("The seller") : tr("The customer"));

  return (
    <section className="mb-6 rounded-xl border p-4" data-testid="order-messages">
      <h2 className="mb-3 text-lg font-semibold">{tr("Messages about this order")}</h2>
      {rows.length === 0 ? <p className="mb-3 text-sm text-muted-foreground">{tr("No messages yet.")}</p> : (
        <ul className="mb-3 space-y-2">
          {rows.map((m) => (
            <li key={m.id} className={`rounded-lg p-3 text-sm ${m.mine ? "bg-primary/10" : "bg-muted/50"}`}>
              <p className="mb-1 text-xs text-muted-foreground">{who(m)} · {formatDateTime(m.created_at)}{m.kind === "problem" ? ` · ${tr("Problem reported")}` : ""}</p>
              <p className="whitespace-pre-wrap">{m.body}</p>
            </li>
          ))}
        </ul>
      )}
      {err && <Note tone="warn">{err}</Note>}
      <form
        className="grid gap-2"
        onSubmit={async (e) => {
          e.preventDefault();
          setErr(null);
          try { await sendOrderMessage(orderId, body, problem); setBody(""); setProblem(false); await load(); } catch (x) { setErr(errorMessage(x)); }
        }}
      >
        <textarea value={body} onChange={(e) => setBody(e.target.value)} required minLength={2} maxLength={1000} className="rounded-[10px] border p-2 text-sm" placeholder={tr("Write a message (no phone numbers or links)")} />
        {canFlag && <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={problem} onChange={(e) => setProblem(e.target.checked)} />{tr("This is a problem. Tell ArmorHub too.")}</label>}
        <div><Button type="submit">{tr("Send")}</Button></div>
      </form>
    </section>
  );
}
