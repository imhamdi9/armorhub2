"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { getOrders } from "@/services/platform.service";
import type { Order } from "@/types/platform";

export default function OrdersPage() {
  const [orders, setOrders] = useState<Order[] | null>(null);

  useEffect(() => {
    getOrders().then(setOrders);
  }, []);

  return (
    <div>
      <PageHeader title="Orders" />

      {orders === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : orders.length === 0 ? (
        <EmptyState>No orders yet.</EmptyState>
      ) : (
        <div className="space-y-3">
          {orders.map((o) => (
            <div key={o.id} className="flex items-center justify-between rounded-lg border p-4">
              <div>
                <h2 className="font-semibold">Order #{o.id}</h2>
                <p className="text-sm text-muted-foreground">
                  {o.total_amount} {o.currency}
                </p>
              </div>
              <Badge variant="secondary">{o.status.replace(/_/g, " ")}</Badge>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
