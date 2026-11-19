"use client";

import { useEffect, useState } from "react";

import { Note } from "@/components/installations/shared";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { LOCALE_TAGS, formatDateTime, tr, useLocale } from "@/lib/i18n";
import { bookSlot, getBooking, getSlots, rescheduleSlot, type BookingView, type SlotsView } from "@/services/ops.service";

/** After paying, the customer picks a time the centre is actually free; one change is allowed with enough notice. */
export function BookingPanel({ jobId }: { jobId: number }) {
  const tag = LOCALE_TAGS[useLocale()];
  // Times and days follow the app's language (not the browser's), so Arabic reads "٩:٠٠ ص" in the right order instead of "AM 09:00" reversed.
  const clock = (iso: string, tz: string) => new Date(iso).toLocaleTimeString(tag, { hour: "2-digit", minute: "2-digit", timeZone: tz });
  const dayLabel = (date: string) => new Date(`${date}T12:00:00`).toLocaleDateString(tag, { weekday: "short", day: "numeric", month: "short" });
  const [booking, setBooking] = useState<BookingView | null | undefined>(undefined);
  const [slots, setSlots] = useState<SlotsView | null>(null);
  const [day, setDay] = useState("");
  const [changing, setChanging] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const load = () => getBooking(jobId).then(setBooking).catch(() => setBooking(null));
  useEffect(() => { load(); }, [jobId]);
  useEffect(() => {
    if (booking === undefined || (booking && !changing)) return;
    getSlots(jobId).then((s) => { setSlots(s); setDay(s.days[0]?.date ?? ""); }).catch(() => setSlots(null));
  }, [booking, changing, jobId]);

  async function pick(time: string) {
    setErr(null);
    try { await (booking ? rescheduleSlot(jobId, time) : bookSlot(jobId, time)); setChanging(false); await load(); } catch (e) { setErr(errorMessage(e)); }
  }

  if (booking === undefined) return null;
  const times = slots?.days.find((d) => d.date === day)?.times ?? [];

  return (
    <section className="mb-6 rounded-xl border p-4" data-testid="booking-panel">
      <h2 className="mb-2 text-lg font-semibold">{tr("Appointment")}</h2>
      {err && <Note tone="warn">{err}</Note>}
      {booking && booking.status === "booked" && !changing ? (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
          <span data-testid="booked-time">{tr("Booked for")} <b>{booking.timezone ? new Date(booking.starts_at).toLocaleString(tag, { dateStyle: "medium", timeStyle: "short", timeZone: booking.timezone }) : formatDateTime(booking.starts_at)}</b></span>
          {booking.can_change ? <Button variant="outline" size="sm" onClick={() => setChanging(true)}>{tr("Change time")}</Button> : <span className="text-muted-foreground">{tr("It is too close to change the time.")}</span>}
        </div>
      ) : (
        <div>
          <p className="mb-3 text-sm text-muted-foreground">{tr("Choose a time when the centre is free.")}</p>
          {!slots || slots.days.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No free times in the next days. Check again soon.")}</p> : (
            <>
              <div className="mb-3 flex flex-wrap gap-2">{slots.days.map((d) => <button key={d.date} type="button" onClick={() => setDay(d.date)} className={`rounded-full border px-3 py-1.5 text-sm ${day === d.date ? "bg-primary text-primary-foreground" : ""}`}>{dayLabel(d.date)}</button>)}</div>
              <div className="flex flex-wrap gap-2">{times.map((t) => <button key={t} type="button" onClick={() => pick(t)} className="rounded-full border border-primary px-3 py-1.5 text-sm text-primary hover:bg-primary/10" data-testid="slot">{clock(t, slots.timezone)}</button>)}</div>
            </>
          )}
          {changing && <Button variant="outline" size="sm" className="mt-3" onClick={() => setChanging(false)}>{tr("Cancel")}</Button>}
        </div>
      )}
    </section>
  );
}
