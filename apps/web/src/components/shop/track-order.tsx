"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";

import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { cancelGuestOrder, getGuestOrder, payGuestOrder, type GuestOrder } from "@/services/guest-orders.service";

const card = "rounded-[28px] bg-white p-6";

/** A guest's private order page: reached only by its secret link. Shows the steps, the delivery, tracking, and an optional account. */
export function TrackOrder({ token }: { token: string }) {
  const [order, setOrder] = useState<GuestOrder | null>(null);
  const [missing, setMissing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [gaveUp, setGaveUp] = useState(false);

  const load = useCallback(() => getGuestOrder(token).then(setOrder).catch(() => setMissing(true)), [token]);
  useEffect(() => { load(); }, [load]);

  // Coming back from a card payment page: the provider confirms by a webhook, so look again for a short while.
  const returned = typeof window !== "undefined" && new URLSearchParams(window.location.search).get("checkout") === "success";
  const waiting = returned && order?.status === "awaiting_payment" && !gaveUp;
  useEffect(() => {
    if (!waiting) return;
    const t = setInterval(load, 4000);
    const stop = setTimeout(() => setGaveUp(true), 120_000);

    return () => { clearInterval(t); clearTimeout(stop); };
  }, [waiting, load]);

  if (missing) {
    return (
      <ShopShell store={{ name: "ArmorHub", slug: "" }}>
        <p className="mx-auto mt-16 max-w-md rounded-[28px] bg-white p-8 text-center" style={{ boxShadow: SHOP_SHADOW }}>{tr("We could not find this order. Check the link in your e-mail.")}</p>
      </ShopShell>
    );
  }
  if (!order) return <p className="p-8 text-center text-[#5f5c5b]">{tr("Loading...")}</p>;

  const has = (type: string) => order.timeline.some((e) => e.type === type);
  const shipped = has("shipped") || order.shipments.length > 0;
  const delivered = has("shipment_delivered");
  const cancelled = order.status === "cancelled";
  const steps: [string, boolean][] = [["Ordered", true], ["Paid", order.status !== "awaiting_payment" && !cancelled], ["Shipped", shipped], ["Delivered", delivered]];
  const image = imageSrc(order.product.image);
  const a = order.shipping_address;
  const slug = order.store.slug ?? "";

  async function run(fn: () => Promise<unknown>) {
    setBusy(true); setError(null);
    try { await fn(); await load(); } catch (e) { setError(errorMessage(e)); } finally { setBusy(false); }
  }

  return (
    <ShopShell store={{ name: order.store.name ?? "ArmorHub", slug }} active="products">
      <div className="mx-auto mt-8 max-w-3xl space-y-4">
        <h1 className="text-center text-4xl tracking-[-0.05em]">{tr("Your order")} #{order.id}</h1>

        {waiting && <p role="status" className="rounded-[20px] bg-white p-4 text-center text-sm" style={{ boxShadow: SHOP_SHADOW }}>{tr("We are waiting for the payment to be confirmed. This page updates by itself.")}</p>}
        {cancelled && <p role="status" className="rounded-[20px] bg-white p-4 text-center text-sm" style={{ boxShadow: SHOP_SHADOW }}>{tr("This order was cancelled.")}</p>}

        {!cancelled && (
          <ol className={`${card} grid grid-cols-4 gap-2 text-center text-sm`} style={{ boxShadow: SHOP_SHADOW }} aria-label={tr("Progress")}>
            {steps.map(([label, done]) => (
              <li key={label} aria-current={done ? "step" : undefined}>
                <span className={`mx-auto mb-2 grid size-9 place-items-center rounded-full ${done ? "btn-brand" : "bg-[#f2f4f5] text-[#5f5c5b]"}`} aria-hidden="true">{done ? "✓" : "·"}</span>
                <span className={done ? "" : "text-[#5f5c5b]"}>{tr(label)}</span>
              </li>
            ))}
          </ol>
        )}

        <section className={`${card} flex items-center gap-4`} style={{ boxShadow: SHOP_SHADOW }}>
          <div className="size-20 shrink-0 overflow-hidden rounded-[20px] bg-[#f2f4f5]">
            {/* eslint-disable-next-line @next/next/no-img-element -- the seller's own product photo */}
            {image ? <img src={image} alt="" className="size-full object-cover" /> : null}
          </div>
          <div className="min-w-0 flex-1"><p className="truncate">{order.product.name}</p>{order.product.seller && <p className="text-sm text-[#5f5c5b]">{tr("Sold by")} {order.product.seller}</p>}<p className="text-xs text-[#5f5c5b]">{formatDateTime(order.created_at)}</p></div>
          <p className="text-xl tracking-[-0.04em]">{formatMoney(order.total, order.currency, true)}</p>
        </section>

        {order.can_pay && (
          <section className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <p className="mb-3 text-sm text-[#5f5c5b]">{tr("Your order is waiting for payment.")}</p>
            <div className="flex flex-wrap gap-2">
              <Button className="btn-brand h-11 rounded-full px-6" disabled={busy} onClick={() => run(async () => { const r = await payGuestOrder(token); if (r.checkout_url) window.location.assign(r.checkout_url); })}>{tr("Pay now")}</Button>
              {order.can_cancel && <Button variant="outline" className="h-11 rounded-full px-6" disabled={busy} onClick={() => run(() => cancelGuestOrder(token))}>{tr("Cancel the order")}</Button>}
            </div>
          </section>
        )}

        {order.shipments.length > 0 && (
          <section className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <h2 className="mb-2 text-xl tracking-[-0.05em]">{tr("Tracking")}</h2>
            {order.shipments.map((s, i) => (
              <p key={i} className="text-sm">{s.carrier} · {s.tracking_number} · {s.tracking_url && <a className="underline" href={s.tracking_url} target="_blank" rel="noreferrer">{tr("Track the parcel")}</a>}</p>
            ))}
          </section>
        )}

        {a && (
          <section className={card} style={{ boxShadow: SHOP_SHADOW }}>
            <h2 className="mb-2 text-xl tracking-[-0.05em]">{tr("Delivery address")}</h2>
            <p className="text-sm">{a.name} · <span dir="ltr">{a.phone}</span></p>
            <p className="text-sm text-[#5f5c5b]">{[a.line1, a.line2, a.city, a.region, a.postal_code].filter(Boolean).join(", ")}</p>
          </section>
        )}

        {error && <p role="alert" className="text-center text-sm text-red-600">{error}</p>}

        <section className={`${card} text-center`} style={{ boxShadow: SHOP_SHADOW }}>
          <h2 className="text-xl tracking-[-0.05em]">{tr("Keep all your orders in one place")}</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-[#5f5c5b]">{tr("Create a free account with the e-mail you used")} (<span dir="ltr">{order.email}</span>) {tr("and verify it: this order and your future ones will be there. You can also just keep this page's link.")}</p>
          <Link href={`/register?store=${encodeURIComponent(slug)}`} className="mt-4 inline-flex h-11 items-center rounded-full border border-[#ebebeb] px-6">{tr("Create an account")}</Link>
        </section>
      </div>
    </ShopShell>
  );
}
