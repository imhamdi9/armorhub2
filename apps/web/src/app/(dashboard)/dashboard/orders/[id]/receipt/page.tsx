"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { formatDate, formatMoney, tr } from "@/lib/i18n";
import { getOrder } from "@/services/platform.service";
import type { Order } from "@/types/platform";

/** A plain receipt for a paid order: what was bought, what was paid and where it goes. Printed or saved as a PDF from the browser. */
export default function ReceiptPage() {
  const id = Number(useParams<{ id: string }>().id);
  const [order, setOrder] = useState<Order | null | undefined>(undefined);
  useEffect(() => { getOrder(id).then(setOrder).catch(() => setOrder(null)); }, [id]);

  if (order === undefined) return <p className="text-muted-foreground">{tr("Loading...")}</p>;
  if (!order) return <EmptyState>{tr("This order was not found.")}</EmptyState>;

  const a = (order as Order & { shipping_address?: { name: string; line1: string; city: string; phone: string } }).shipping_address;
  const fee = Number(order.service_fee_amount ?? 0);
  const qty = order.quantity ?? 1;

  return (
    <div className="mx-auto max-w-2xl rounded-xl border p-6 print:border-0 print:p-0" data-testid="receipt">
      <div className="mb-6 flex items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{tr("Receipt for order #{id}", { id: order.id })}</h1>
          {order.created_at && <p className="text-sm text-muted-foreground">{formatDate(order.created_at)}</p>}
        </div>
        <div className="flex gap-2 print:hidden">
          <Link href={`/dashboard/orders/${order.id}`} className="inline-flex h-8 items-center rounded-full border px-4 text-sm">{tr("Back to the order")}</Link>
          <Button size="sm" onClick={() => window.print()}>{tr("Print")}</Button>
        </div>
      </div>

      <table className="mb-4 w-full text-sm">
        <thead><tr className="border-b text-start"><th className="py-2 text-start">{tr("Item")}</th><th className="py-2 text-start">{tr("Quantity")}</th><th className="py-2 text-end">{tr("Invoice")}</th></tr></thead>
        <tbody>
          <tr className="border-b"><td className="py-2">{order.product?.name}</td><td className="py-2">{qty}</td><td className="py-2 text-end">{formatMoney(order.total_amount, order.currency)}</td></tr>
        </tbody>
      </table>

      <dl className="ms-auto mb-6 max-w-xs space-y-1 text-sm">
        {fee > 0 && <div className="flex justify-between"><dt>{tr("Platform fee ({rate}%)", { rate: Number(order.service_fee_rate) })}</dt><dd>{formatMoney(fee, order.currency)}</dd></div>}
        {Number(order.tax_rate) > 0 && <div className="flex justify-between text-muted-foreground"><dt>{tr("Includes VAT ({rate}%)", { rate: Number(order.tax_rate) })}</dt><dd>{formatMoney(order.tax_amount ?? 0, order.currency)}</dd></div>}
        <div className="flex justify-between border-t pt-1 text-base font-bold"><dt>{tr("Total paid")}</dt><dd>{formatMoney(order.payable_amount ?? order.total_amount, order.currency)}</dd></div>
      </dl>

      {a && <p className="text-sm"><b>{tr("Delivered to")}:</b> {a.name}, {a.line1}, {a.city}</p>}
    </div>
  );
}
