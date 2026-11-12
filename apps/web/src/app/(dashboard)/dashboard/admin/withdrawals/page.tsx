"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { getAdminWithdrawals, payWithdrawal, rejectWithdrawal } from "@/services/installation.service";

type Row = Awaited<ReturnType<typeof getAdminWithdrawals>>[number];

function Row({ w, onDone }: { w: Row; onDone: () => void }) {
  const [ref, setRef] = useState("");

  return (
    <div className="space-y-2 p-3" data-testid="withdrawal">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="font-medium">{w.company} · {formatMoney(w.amount, w.currency)}</p>
        <Badge variant={w.status === "paid" ? "default" : w.status === "rejected" ? "destructive" : "secondary"}>{tr(w.status === "paid" ? "Paid" : w.status === "rejected" ? "Not paid" : "Requested")}</Badge>
      </div>
      <p className="text-sm text-muted-foreground">{tr(w.method === "bank" ? "Bank transfer" : "Mobile wallet")}: {w.account_name} · <span dir="ltr">{w.account_number}</span>{w.bank_name ? ` · ${w.bank_name}` : ""} · {formatDateTime(w.created_at)}</p>
      {w.reference && <p className="text-sm">{tr("Reference")}: {w.reference}</p>}
      {w.note && <p className="text-sm">{w.note}</p>}
      {w.status === "requested" && (
        <div className="flex flex-wrap gap-2">
          <Input className="max-w-xs" aria-label={tr("Reference of the transfer you made")} placeholder={tr("Reference of the transfer you made")} value={ref} onChange={(e) => setRef(e.target.value)} />
          <Button size="sm" disabled={!ref} onClick={async () => { await payWithdrawal(w.id, ref); onDone(); }}>{tr("Mark as paid")}</Button>
          <Button size="sm" variant="outline" onClick={async () => { const n = window.prompt(tr("Why is it not paid?")); if (n) { await rejectWithdrawal(w.id, n); onDone(); } }}>{tr("Reject")}</Button>
        </div>
      )}
    </div>
  );
}

export default function AdminWithdrawalsPage() {
  const [rows, setRows] = useState<Row[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const load = () => getAdminWithdrawals().then(setRows).catch(() => setForbidden(true));
  useEffect(() => { load(); }, []);

  if (forbidden) return <EmptyState>{tr("Only platform admins can open this page.")}</EmptyState>;
  if (!rows) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-3xl">
      <PageHeader title="Withdrawals" />
      <p className="mb-6 text-sm text-muted-foreground">{tr("Centres ask to withdraw what they earned. Pay by bank transfer or mobile wallet outside the app, then record the reference here. Money is not moved by this page.")}</p>
      {rows.length === 0 ? <EmptyState>{tr("No withdrawal requests.")}</EmptyState> : <div className="divide-y rounded-xl border">{rows.map((w) => <Row key={w.id} w={w} onDone={load} />)}</div>}
    </div>
  );
}
