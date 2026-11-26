"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { hasRole } from "@/lib/navigation";
import { STATUS_LABELS, statusLabel } from "@/lib/orders";
import { api } from "@/services/api";
import { resendVerification } from "@/services/auth.service";
import { getOrders } from "@/services/platform.service";
import type { Order, Paginated } from "@/types/platform";
import { formatDate, formatMoney, tr } from "@/lib/i18n";
import { LoadingBlock } from "@/components/ui/loading-block";

export default function OrdersPage() {
  const user = useCurrentUser();
  const [result, setResult] = useState<Paginated<Order> | null>(null);
  // A link such as /dashboard/orders?status=quotes_received opens the list already filtered. (This screen only renders after sign-in, in the browser.)
  const [status, setStatus] = useState(() => (typeof window === "undefined" ? "" : new URLSearchParams(window.location.search).get("status") ?? ""));
  const [page, setPage] = useState(1);

  const [waiting, setWaiting] = useState(0);
  const [sent, setSent] = useState(false);
  useEffect(() => { api.get<{ count: number }>("/orders/waiting-for-verification").then((r) => setWaiting(r.data.count)).catch(() => {}); }, []);

  useEffect(() => {
    getOrders({ page, ...(status ? { status } : {}) })
      .then(setResult)
      .catch(() => setResult({ data: [], current_page: 1, last_page: 1 }));
  }, [page, status]);

  return (
    <div>
      {waiting > 0 && (
        <div role="note" className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" data-testid="waiting-orders">
          <span>{tr("You have {n} earlier purchase(s) made with this e-mail. Verify your e-mail and they will appear here.", { n: waiting })}</span>
          <Button size="sm" disabled={sent} onClick={() => resendVerification().then(() => setSent(true))}>{sent ? tr("Link sent") : tr("Send verification link")}</Button>
        </div>
      )}
      <PageHeader
        title={tr("Orders")}
        action={
          hasRole(user, "customer") && !user?.is_guest && (
            <Link href="/dashboard/orders/new" className="rounded-lg bg-black px-4 py-2 text-white hover:opacity-90">
              {tr("New order")}
            </Link>
          )
        }
      />

      <select
        aria-label={tr("Filter by status")}
        className="mb-4 rounded-lg border px-3 py-2"
        value={status}
        onChange={(e) => {
          setStatus(e.target.value);
          setPage(1);
        }}
      >
        <option value="">{tr("All statuses")}</option>
        {Object.entries(STATUS_LABELS).map(([value, label]) => (
          <option key={value} value={value}>{tr(label)}</option>
        ))}
      </select>

      {result === null ? (
        <LoadingBlock />
      ) : result.data.length === 0 ? (
        <EmptyState>
          {tr("No orders found.")}
          {!status && user?.roles?.includes("customer") && !user.is_guest && <> <Link href="/dashboard/orders/new" className="font-medium underline underline-offset-2">{tr("Place your first order")}</Link></>}
        </EmptyState>
      ) : (
        <>
          <div className="space-y-3">
            {result.data.map((o) => (
              <Link
                key={o.id}
                href={`/dashboard/orders/${o.id}`}
                className="flex items-center justify-between rounded-lg border p-4 hover:bg-muted"
              >
                <div>
                  <h2 className="font-semibold">{tr("Order #{id}", { id: o.id })}{o.product ? ` · ${o.product.name}` : ""}{(o.quantity ?? 1) > 1 ? ` × ${o.quantity}` : ""}</h2>
                  <p className="text-sm text-muted-foreground">{formatMoney(o.total_amount, o.currency)}{o.created_at ? ` · ${formatDate(o.created_at)}` : ""}</p>
                </div>
                <Badge variant="secondary">{o.refund_status ? tr("Refunded") : statusLabel(o.status)}</Badge>
              </Link>
            ))}
          </div>

          {result.last_page > 1 && (
            <div className="mt-4 flex items-center justify-center gap-3">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                {tr("Previous")}
              </Button>
              <span className="text-sm">{tr("Page {page} of {last}", { page: result.current_page, last: result.last_page })}</span>
              <Button variant="outline" size="sm" disabled={page >= result.last_page} onClick={() => setPage((p) => p + 1)}>
                {tr("Next")}
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
