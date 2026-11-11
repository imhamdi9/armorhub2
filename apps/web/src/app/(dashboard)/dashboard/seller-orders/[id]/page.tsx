"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";

import { ShippingPanel } from "@/components/orders/shipping-panel";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { eventLabel, statusLabel } from "@/lib/orders";
import { getSellerOrder, type SellerOrder } from "@/services/platform.service";

/** One sale: what was bought, where it goes, and the shipment (book it, track it). */
export default function SellerOrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [order, setOrder] = useState<SellerOrder | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    getSellerOrder(id).then(setOrder).catch(() => setMissing(true));
  }, [id]);

  if (missing) return <EmptyState>{tr("This order was not found.")}</EmptyState>;
  if (!order) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-2xl">
      <Link href="/dashboard/seller-orders" className="text-sm underline">← {tr("Sales")}</Link>
      <PageHeader title={tr("Order #{id}", { id: order.id })} action={<Badge variant="secondary">{statusLabel(order.status)}</Badge>} />

      <div className="mb-6 rounded-lg border p-4 text-sm">
        <p className="font-semibold">{order.product?.name}</p>
        <p>{tr("Total")}: {formatMoney(order.total_amount, order.currency)}</p>
        <p className="text-muted-foreground">{order.customer_name} · {formatDateTime(order.created_at)}</p>
      </div>

      <ShippingPanel orderId={order.id} paid />

      {order.events && order.events.length > 0 && (
        <>
          <h2 className="mb-3 mt-8 text-xl font-semibold">{tr("Timeline")}</h2>
          <ol className="space-y-3 border-s ps-4">
            {order.events.map((ev) => (
              <li key={ev.id}>
                <p className="font-medium">{eventLabel(ev)}</p>
                <p className="text-xs text-muted-foreground">{formatDateTime(ev.created_at)}</p>
              </li>
            ))}
          </ol>
        </>
      )}
    </div>
  );
}
