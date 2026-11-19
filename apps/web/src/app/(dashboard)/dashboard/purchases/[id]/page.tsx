"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { PurchasePixel } from "@/components/marketing/purchase-pixel";
import { PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { OrderStatusStrip } from "@/components/orders/status-strip";
import { cancelPurchase, getPurchase, payPurchase, type Purchase } from "@/services/cart.service";
import { LoadingBlock } from "@/components/ui/loading-block";

/** One purchase: what was bought, from whom, and where each part is. Each line is its own order with its own status. */
export default function PurchasePage() {
  const { id } = useParams<{ id: string }>();
  const [purchase, setPurchase] = useState<Purchase | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => getPurchase(id).then(setPurchase).catch((e) => setError(errorMessage(e))), [id]);
  useEffect(() => { void load(); }, [load]);

  async function pay() {
    if (!purchase) return;
    setError(null);
    try {
      const res = await payPurchase(purchase.id);
      if (res.checkout_url) { window.location.href = res.checkout_url; return; }
      setPurchase(res.group);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  async function cancel() {
    if (!purchase) return;
    try { setPurchase(await cancelPurchase(purchase.id)); } catch (e) { setError(errorMessage(e)); }
  }

  if (!purchase) return error ? <p role="alert" className="text-red-600">{error}</p> : <LoadingBlock />;

  // Every order of a cash-on-delivery purchase is confirmed, but the money is collected at the door.
  const cod = purchase.orders.length > 0 && purchase.orders.every((o) => o.statuses?.payment === "cash_on_delivery");

  return (
    <div>
      {purchase.status === "paid" && <PurchasePixel orders={purchase.orders.map((o) => ({ id: o.id, value: o.payable_amount ?? o.total_amount + o.service_fee_amount }))} currency={purchase.currency} market={purchase.market} />}
      <PageHeader title={`${tr("Purchase")} #${purchase.id}`} />
      <p className="mb-4"><Badge variant={purchase.status === "paid" ? "default" : "secondary"} data-testid="purchase-status">{purchase.status === "paid" ? tr(cod ? "Pay on delivery" : "Paid") : purchase.status === "cancelled" ? tr("Cancelled") : tr("Waiting for payment")}</Badge></p>

      <ul className="space-y-3">
        {purchase.orders.map((o) => (
          <li key={o.id} className="flex items-center justify-between gap-3 rounded-2xl border p-3">
            <div>
              <p className="font-semibold">{o.product} <span className="text-muted-foreground">× {o.quantity}</span></p>
              <div className="mt-1"><OrderStatusStrip statuses={o.statuses} /></div>
            </div>
            <div className="text-end">
              <p className="font-semibold">{formatMoney(o.total_amount, purchase.currency)}</p>
              <Link href={`/dashboard/orders/${o.id}`} className="text-sm underline">{tr("Order details")}</Link>
            </div>
          </li>
        ))}
      </ul>

      <dl className="mt-4 max-w-sm space-y-1 text-sm">
        <div className="flex justify-between"><dt>{tr("Items")}</dt><dd>{formatMoney(purchase.items_amount, purchase.currency)}</dd></div>
        {(purchase.discount_amount ?? 0) > 0 && <div className="flex justify-between text-green-700"><dt>{tr("Discount")}</dt><dd>−{formatMoney(purchase.discount_amount ?? 0, purchase.currency)}</dd></div>}
        {(purchase.shipping_amount ?? 0) > 0 && <div className="flex justify-between"><dt>{tr("Delivery")}</dt><dd>{formatMoney(purchase.shipping_amount ?? 0, purchase.currency)}</dd></div>}
        {purchase.service_fee_amount > 0 && <div className="flex justify-between"><dt>{tr("Platform fee")}</dt><dd>{formatMoney(purchase.service_fee_amount, purchase.currency)}</dd></div>}
        <div className="flex justify-between text-base font-bold"><dt>{tr(purchase.status === "paid" ? (cod ? "Total to pay on delivery" : "Total paid") : "Total to pay")}</dt><dd>{formatMoney(purchase.payable_amount, purchase.currency)}</dd></div>
      </dl>

      {error && <p className="mt-3 text-sm text-red-600" role="alert">{error}</p>}
      {purchase.status === "awaiting_payment" && (
        <div className="mt-4 flex gap-3">
          <Button onClick={pay}>{tr("Pay {amount}", { amount: formatMoney(purchase.payable_amount, purchase.currency) })}</Button>
          <Button variant="outline" onClick={cancel}>{tr("Cancel purchase")}</Button>
        </div>
      )}
    </div>
  );
}
