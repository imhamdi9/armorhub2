"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { hasRole } from "@/lib/navigation";
import { STATUS_LABELS, statusLabel } from "@/lib/orders";
import { getOrders } from "@/services/platform.service";
import type { Order, Paginated } from "@/types/platform";

export default function OrdersPage() {
  const user = useCurrentUser();
  const [result, setResult] = useState<Paginated<Order> | null>(null);
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);

  useEffect(() => {
    getOrders({ page, ...(status ? { status } : {}) })
      .then(setResult)
      .catch(() => setResult({ data: [], current_page: 1, last_page: 1 }));
  }, [page, status]);

  return (
    <div>
      <PageHeader
        title="Orders"
        action={
          hasRole(user, "customer") && (
            <Link href="/dashboard/orders/new" className="rounded-lg bg-black px-4 py-2 text-white hover:opacity-90">
              New order
            </Link>
          )
        }
      />

      <select
        className="mb-4 rounded-lg border px-3 py-2"
        value={status}
        onChange={(e) => {
          setStatus(e.target.value);
          setPage(1);
        }}
      >
        <option value="">All statuses</option>
        {Object.entries(STATUS_LABELS).map(([value, label]) => (
          <option key={value} value={value}>{label}</option>
        ))}
      </select>

      {result === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : result.data.length === 0 ? (
        <EmptyState>No orders found.</EmptyState>
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
                  <h2 className="font-semibold">Order #{o.id}{o.product ? ` · ${o.product.name}` : ""}</h2>
                  <p className="text-sm text-muted-foreground">{o.total_amount} {o.currency}</p>
                </div>
                <Badge variant="secondary">{statusLabel(o.status)}</Badge>
              </Link>
            ))}
          </div>

          {result.last_page > 1 && (
            <div className="mt-4 flex items-center justify-center gap-3">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </Button>
              <span className="text-sm">Page {result.current_page} of {result.last_page}</span>
              <Button variant="outline" size="sm" disabled={page >= result.last_page} onClick={() => setPage((p) => p + 1)}>
                Next
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
