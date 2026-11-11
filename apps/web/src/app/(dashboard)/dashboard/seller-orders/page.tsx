"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { statusLabel } from "@/lib/orders";
import { getSellerOrders, type SellerOrder } from "@/services/platform.service";

/** Paid orders for the signed-in seller's own products: these are the ones to ship. */
export default function SellerOrdersPage() {
  const [orders, setOrders] = useState<SellerOrder[] | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getSellerOrders().then((r) => setOrders(r.data)).catch(() => setForbidden(true));
  }, []);

  if (forbidden) return <EmptyState>{tr("You do not have access to sales.")}</EmptyState>;

  return (
    <div className="max-w-3xl">
      <PageHeader title={tr("Sales")} />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Orders for your products appear here once the customer has paid. Open one to see the delivery address and book the shipment.")}</p>

      {orders === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : orders.length === 0 ? (
        <EmptyState>{tr("No paid orders yet.")}</EmptyState>
      ) : (
        <div className="space-y-3">
          {orders.map((o) => (
            <Link key={o.id} href={`/dashboard/seller-orders/${o.id}`} className="block rounded-lg border p-4 hover:bg-muted">
              <div className="flex items-center justify-between gap-2">
                <h2 className="font-semibold">{tr("Order #{id}", { id: o.id })} · {o.product?.name}</h2>
                <Badge variant="secondary">{statusLabel(o.status)}</Badge>
              </div>
              <p className="text-sm text-muted-foreground">{o.customer_name} · {formatMoney(o.total_amount, o.currency)} · {formatDateTime(o.created_at)}</p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
