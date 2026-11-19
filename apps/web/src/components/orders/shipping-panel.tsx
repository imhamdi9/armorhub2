"use client";

import { useCallback, useEffect, useState } from "react";

import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import {
  bookShipment,
  getOrderShipping,
  getShippingChoices,
  getShippingRates,
  refreshShipment,
  rescheduleShipment,
  setShipmentStatus,
} from "@/services/platform.service";
import type { OrderShipping, Shipment, ShippingChoice, ShippingRate } from "@/types/platform";

const STATUS_LABELS: Record<string, string> = {
  created: "Booked",
  in_transit: "In transit",
  out_for_delivery: "Out for delivery",
  delivered: "Delivered",
  failed: "Delivery failed",
  returned: "Returned",
  cancelled: "Cancelled",
};
const STEPS = ["created", "in_transit", "out_for_delivery", "delivered"] as const;

/** Where the parcel is on its way: four steps, the finished ones filled. A failed, returned or cancelled parcel shows its badge instead. */
function Progress({ status }: { status: string }) {
  const at = STEPS.indexOf(status as (typeof STEPS)[number]);
  if (at < 0) return null;

  return (
    <ol className="mt-3 flex items-center gap-1 text-xs" aria-label={tr("Delivery progress")} data-testid="shipment-progress">
      {STEPS.map((step, i) => (
        <li key={step} className="flex flex-1 flex-col items-center gap-1 text-center" aria-current={i === at ? "step" : undefined}>
          <span className={`h-1.5 w-full rounded-full ${i <= at ? "bg-primary" : "bg-muted"}`} />
          <span className={i <= at ? "font-semibold" : "text-muted-foreground"}>{tr(STATUS_LABELS[step])}</span>
        </li>
      ))}
    </ol>
  );
}

const NEXT_STATUSES = ["in_transit", "out_for_delivery", "delivered", "failed", "returned"];

/**
 * Delivery address, parcel tracking and (for staff) booking. Shown on orders whose product is physical.
 * Customers only read; staff book through any ready provider, refresh tracking or set the status by hand.
 */
export function ShippingPanel({ orderId, paid }: { orderId: number; paid: boolean }) {
  const [info, setInfo] = useState<OrderShipping | null>(null);
  const [choices, setChoices] = useState<ShippingChoice | null>(null);
  const [provider, setProvider] = useState("");
  const [rates, setRates] = useState<ShippingRate[]>([]);
  const [service, setService] = useState("");
  const [carrier, setCarrier] = useState("");
  const [tracking, setTracking] = useState("");
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => getOrderShipping(orderId).then(setInfo).catch(() => setInfo(null)), [orderId]);

  useEffect(() => {
    load();
  }, [load, paid]);

  useEffect(() => {
    if (!info?.can_manage) return;
    getShippingChoices().then((c) => {
      setChoices(c);
      setProvider((p) => p || c.default);
    });
  }, [info?.can_manage]);

  async function run(action: () => Promise<unknown>, ok?: string) {
    setBusy(true);
    setMessage(null);
    try {
      await action();
      await load();
      if (ok) setMessage({ ok: true, text: tr(ok) });
    } catch (e) {
      setMessage({ ok: false, text: errorMessage(e) });
    } finally {
      setBusy(false);
    }
  }

  if (!info || !info.requires_shipping) return null;

  const live = info.shipments.find((s) => !["cancelled", "returned"].includes(s.status));
  const current = choices?.providers.find((p) => p.key === provider);

  return (
    <section className="mb-6 rounded-lg border p-4 text-sm" aria-label={tr("Shipping")}>
      <h2 className="mb-2 text-lg font-semibold">{tr("Delivery")}</h2>

      {info.address && (
        <address className="mb-3 not-italic text-muted-foreground">
          <strong className="text-foreground">{info.address.name}</strong>
          <br />
          {[info.address.line1, info.address.line2].filter(Boolean).join(", ")}
          <br />
          {[info.address.city, info.address.region, info.address.postal_code, info.address.country].filter(Boolean).join(", ")}
          <br />
          <span dir="ltr">{info.address.phone}</span>
        </address>
      )}

      {(info.delivery_speed === "express" || info.preferred_day || info.gift_note) && (
        <ul className="mb-3 space-y-1 rounded-lg bg-primary/5 p-3" data-testid="delivery-choices-view">
          {info.delivery_speed === "express" && <li>⚡ <b>{tr("Express delivery")}</b></li>}
          {info.preferred_day && <li>📅 {tr("Preferred day")}: <b>{info.preferred_day}</b></li>}
          {info.gift_note && <li>🎁 {tr("Gift note")}: “{info.gift_note}”</li>}
        </ul>
      )}

      {info.shipments.length === 0 && (
        <p className="text-muted-foreground">{paid ? tr("Your parcel has not been shipped yet.") : tr("Your parcel will be shipped after payment.")}</p>
      )}

      {info.shipments.map((s) => (
        <ShipmentView key={s.id} shipment={s} canManage={info.can_manage} busy={busy} run={run} />
      ))}

      {info.can_manage && paid && !live && choices && (
        <div className="mt-4 space-y-3 border-t pt-4">
          <h3 className="font-medium">{tr("Book a shipment")}</h3>
          <select aria-label={tr("Shipping provider")} className="rounded-lg border px-3 py-2" value={provider} onChange={(e) => { setProvider(e.target.value); setRates([]); }}>
            {choices.providers.map((p) => <option key={p.key} value={p.key}>{tr(p.label)}</option>)}
          </select>

          {current?.manual ? (
            <div className="flex flex-wrap gap-2">
              <Input className="w-48" placeholder={tr("Carrier name")} aria-label={tr("Carrier name")} value={carrier} onChange={(e) => setCarrier(e.target.value)} />
              <Input className="w-56" placeholder={tr("Tracking number")} aria-label={tr("Tracking number")} value={tracking} onChange={(e) => setTracking(e.target.value)} />
              <Button disabled={busy || !carrier || !tracking} onClick={() => run(() => bookShipment(orderId, { provider, carrier, tracking_number: tracking }), "Shipment recorded.")}>{tr("Save shipment")}</Button>
            </div>
          ) : (
            <div className="space-y-2">
              <Button variant="outline" size="sm" disabled={busy} onClick={() => run(async () => setRates(await getShippingRates(orderId, provider)))}>{tr("Get shipping prices")}</Button>
              {rates.length > 0 && (
                <div className="space-y-1">
                  {rates.map((r) => (
                    <label key={r.service} className="flex items-center gap-2">
                      <input type="radio" name="rate" checked={service === r.service} onChange={() => setService(r.service)} />
                      {tr(r.label)} · {formatMoney(r.price, r.currency)}{r.eta_days ? ` · ${tr("{n} days", { n: r.eta_days })}` : ""}
                    </label>
                  ))}
                </div>
              )}
              <Button disabled={busy} onClick={() => run(() => bookShipment(orderId, { provider, ...(service ? { service } : {}) }), "Shipment booked.")}>{tr("Book shipment")}</Button>
            </div>
          )}
        </div>
      )}

      {message && <p className={`mt-3 ${message.ok ? "text-green-600" : "text-red-500"}`}>{message.text}</p>}
    </section>
  );
}

function ShipmentView({
  shipment: s,
  canManage,
  busy,
  run,
}: {
  shipment: Shipment;
  canManage: boolean;
  busy: boolean;
  run: (action: () => Promise<unknown>, ok?: string) => Promise<void>;
}) {
  const final = ["delivered", "returned", "cancelled"].includes(s.status);
  const [pick, setPick] = useState("");
  const [code, setCode] = useState("");
  const [reason, setReason] = useState("");
  const [day, setDay] = useState("");
  const [note, setNote] = useState("");
  const [startedAt] = useState(() => Date.now());
  const tomorrow = new Date(startedAt + 86_400_000).toISOString().slice(0, 10);

  return (
    <div className="mt-3 rounded-md border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="font-medium">
          {s.carrier ?? tr("Carrier")} · <span dir="ltr">{s.tracking_number}</span>
        </p>
        <Badge variant={s.status === "delivered" ? "default" : "secondary"}>{tr(STATUS_LABELS[s.status] ?? s.status)}</Badge>
      </div>
      <div className="mt-1 flex flex-wrap gap-3">
        {s.tracking_url && <a className="underline" href={s.tracking_url} target="_blank" rel="noopener noreferrer">{tr("Track with the carrier")}</a>}
        {canManage && s.label_url && <a className="underline" href={s.label_url} target="_blank" rel="noopener noreferrer">{tr("Shipping label")}</a>}
      </div>

      <Progress status={s.status} />

      {!canManage && s.delivery_code && (
        <div className="mt-3 rounded-lg bg-primary/5 p-3" data-testid="delivery-code">
          <p className="text-muted-foreground">{tr("Give this code to the courier when you receive the parcel. Do not share it before.")}</p>
          <p className="mt-1 text-2xl font-bold tracking-[0.3em]" dir="ltr">{s.delivery_code}</p>
        </div>
      )}

      {s.status === "failed" && (
        <div className="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-950" data-testid="delivery-failed">
          <p className="font-medium">{tr("The delivery attempt failed")}{s.failed_reason ? `: ${s.failed_reason}` : ""}</p>
          {!canManage && s.attempts < 3 && (
            <div className="mt-2 flex flex-wrap items-end gap-2">
              <label className="text-xs">{tr("New delivery day")}
                <Input type="date" className="mt-1 w-44 bg-white" min={tomorrow} value={day} onChange={(e) => setDay(e.target.value)} />
              </label>
              <Input className="w-56 bg-white" placeholder={tr("A note for the courier (optional)")} aria-label={tr("A note for the courier (optional)")} value={note} onChange={(e) => setNote(e.target.value)} />
              <Button size="sm" disabled={busy || !day} onClick={() => run(() => rescheduleShipment(s.id, day, note || undefined), "We will deliver on the day you chose.")}>{tr("Reschedule delivery")}</Button>
            </div>
          )}
          {!canManage && s.attempts >= 3 && <p className="mt-1 text-sm">{tr("There were too many failed attempts. Please contact support.")}</p>}
        </div>
      )}

      {s.status !== "failed" && s.reschedule_for && !final && (
        <p className="mt-3 text-sm" data-testid="rescheduled">{tr("Delivery is planned for")} <b>{s.reschedule_for}</b></p>
      )}

      {s.events.length > 0 && (
        <ol className="mt-3 space-y-2 border-s ps-3">
          {[...s.events].reverse().map((e) => (
            <li key={e.id}>
              <p>{tr(STATUS_LABELS[e.status] ?? e.status)}{e.description ? ` · ${e.description}` : ""}</p>
              <p className="text-xs text-muted-foreground">{formatDateTime(e.occurred_at)}{e.location ? ` · ${e.location}` : ""}</p>
            </li>
          ))}
        </ol>
      )}

      {canManage && !final && (
        <div className="mt-3 flex flex-wrap items-center gap-2">
          {s.provider !== "manual" && (
            <Button size="sm" variant="outline" disabled={busy} onClick={() => run(() => refreshShipment(s.id), "Tracking updated.")}>{tr("Refresh tracking")}</Button>
          )}
          <select aria-label={tr("Set status")} className="rounded-lg border px-2 py-1" value="" onChange={(e) => {
            const v = e.target.value;
            if (v === "delivered" || v === "failed") setPick(v);
            else if (v) run(() => setShipmentStatus(s.id, v), "Status updated.");
          }}>
            <option value="">{tr("Set status…")}</option>
            {NEXT_STATUSES.map((st) => <option key={st} value={st}>{tr(STATUS_LABELS[st])}</option>)}
          </select>
          {pick === "delivered" && (
            <>
              <Input className="w-40" inputMode="numeric" placeholder={tr("Delivery code")} aria-label={tr("Delivery code")} value={code} onChange={(e) => setCode(e.target.value)} />
              <Button size="sm" disabled={busy || !code} onClick={() => run(async () => { await setShipmentStatus(s.id, "delivered", { code }); setPick(""); setCode(""); }, "Status updated.")}>{tr("Confirm delivery")}</Button>
            </>
          )}
          {pick === "failed" && (
            <>
              <Input className="w-56" placeholder={tr("Why did it fail?")} aria-label={tr("Why did it fail?")} value={reason} onChange={(e) => setReason(e.target.value)} />
              <Button size="sm" disabled={busy || !reason} onClick={() => run(async () => { await setShipmentStatus(s.id, "failed", { description: reason }); setPick(""); setReason(""); }, "Status updated.")}>{tr("Record failed attempt")}</Button>
            </>
          )}
        </div>
      )}
    </div>
  );
}
