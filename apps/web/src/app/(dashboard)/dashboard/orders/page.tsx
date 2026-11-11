"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { hasRole } from "@/lib/navigation";
import { getOrders } from "@/services/platform.service";
import type { Order } from "@/types/platform";

export default function OrdersPage() {
  const user = useCurrentUser();
  const [orders, setOrders] = useState<Order[] | null>(null);

  useEffect(() => {
    getOrders().then(setOrders);
  }, []);

  return (
    <div>
      <PageHeader
        title="Orders"
        action={
          hasRole(user, "customer") && (
            <Link
              href="/dashboard/orders/new"
              className="rounded-lg bg-black px-4 py-2 text-white hover:opacity-90"
            >
              New order
            </Link>
          )
        }
      />

      {orders === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : orders.length === 0 ? (
        <EmptyState>No orders yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {orders.map((o) => (
            <Link
              key={o.id}
              href={`/dashboard/orders/${o.id}`}
              className="flex items-center justify-between rounded-lg border p-4 hover:bg-muted"
            >
              <div>
                <h2 className="font-semibold">Order #{o.id}</h2>
                <p className="text-sm text-muted-foreground">
                  {o.total_amount} {o.currency}
                </p>
              </div>
              <Badge variant="secondary">{o.status.replace(/_/g, " ")}</Badge>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
