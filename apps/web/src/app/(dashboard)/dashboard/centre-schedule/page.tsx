"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import { addTimeOff, getCalendar, getCentreSettings, removeTimeOff, saveCentreSettings, saveHours, type CalendarItem, type CentreSettings } from "@/services/ops.service";

const DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];

/** How a centre works: its reach and capacity, opening hours, days off, and the jobs booked for the next two weeks. */
export default function CentreSchedulePage() {
  const [s, setS] = useState<CentreSettings | null>(null);
  const [cal, setCal] = useState<CalendarItem[]>([]);
  const [hours, setHours] = useState<Record<number, { on: boolean; opens: string; closes: string }>>({});
  const [msg, setMsg] = useState<{ tone: "ok" | "warn"; text: string } | null>(null);
  const [forbidden, setForbidden] = useState(false);

  function apply(x: CentreSettings) {
    setS(x);
    const h: Record<number, { on: boolean; opens: string; closes: string }> = {};
    for (let d = 0; d < 7; d++) {
      const w = x.hours.find((i) => i.weekday === d);
      h[d] = { on: !!w, opens: w?.opens ?? "09:00", closes: w?.closes ?? "18:00" };
    }
    setHours(h);
  }
  useEffect(() => {
    getCentreSettings().then(apply).catch(() => setForbidden(true));
    const from = new Date();
    const to = new Date(from.getTime() + 14 * 86400000);
    getCalendar(from.toISOString().slice(0, 10), to.toISOString().slice(0, 10)).then(setCal).catch(() => {});
  }, []);

  async function run(fn: () => Promise<CentreSettings>) {
    setMsg(null);
    try { apply(await fn()); setMsg({ tone: "ok", text: tr("Saved") }); } catch (e) { setMsg({ tone: "warn", text: errorMessage(e) }); }
  }

  if (forbidden) return <EmptyState>{tr("This page is for service centres.")}</EmptyState>;
  if (!s) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="space-y-8">
      <PageHeader title={tr("Schedule and settings")} />
      {msg && <Note tone={msg.tone}>{msg.text}</Note>}

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Booked jobs, next 14 days")}</h2>
        {cal.length === 0 ? <EmptyState>{tr("No booked jobs yet.")}</EmptyState> : (
          <ul className="divide-y rounded-xl border" data-testid="calendar">
            {cal.map((b) => (
              <li key={b.id} className="flex flex-wrap items-center justify-between gap-2 p-3 text-sm">
                <span className="font-medium">{formatDateTime(b.starts_at)}</span>
                <span>{b.subject} · {(b.parts ?? []).join(", ")}</span>
                <span className="text-muted-foreground">{b.customer}</span>
                {b.job_id && <a className="text-primary underline" href={`/dashboard/work-orders/${b.job_id}`}>{tr("Work order")}</a>}
              </li>
            ))}
          </ul>
        )}
      </section>

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Service settings")}</h2>
        <form
          className="grid gap-3 rounded-xl border p-4 md:grid-cols-2"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            const min = String(f.get("min") || "");
            run(() => saveCentreSettings({ service_radius_km: Number(f.get("radius")), daily_capacity: Number(f.get("capacity")), bays: Number(f.get("bays")), job_hours: Number(f.get("job_hours")), min_job_price: min === "" ? null : Number(min), accepts_requests: f.get("accepts") === "on" }));
          }}
        >
          {([["radius", "Service radius (km)", s.service_radius_km], ["capacity", "Jobs per day", s.daily_capacity], ["bays", "Bays", s.bays], ["job_hours", "Hours per job", s.job_hours]] as const).map(([n, l, v]) => (
            <label key={n} className="text-sm">{tr(l)}<input name={n} type="number" min={1} defaultValue={v} className={field} required /></label>
          ))}
          <label className="text-sm">{tr("Minimum job price")}<input name="min" type="number" min={0} defaultValue={s.min_job_price ?? ""} className={field} /></label>
          <label className="flex items-center gap-2 text-sm"><input name="accepts" type="checkbox" defaultChecked={s.accepts_requests} />{tr("Accepting new requests")}</label>
          <div><Button type="submit">{tr("Save settings")}</Button></div>
        </form>
      </section>

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Opening hours")}</h2>
        <div className="space-y-2 rounded-xl border p-4">
          {DAYS.map((name, d) => (
            <div key={d} className="flex flex-wrap items-center gap-3 text-sm">
              <label className="flex w-36 items-center gap-2"><input type="checkbox" checked={hours[d]?.on ?? false} onChange={(e) => setHours({ ...hours, [d]: { ...hours[d], on: e.target.checked } })} />{tr(name)}</label>
              <input type="time" className="h-9 rounded-lg border px-2" value={hours[d]?.opens ?? ""} disabled={!hours[d]?.on} onChange={(e) => setHours({ ...hours, [d]: { ...hours[d], opens: e.target.value } })} />
              <input type="time" className="h-9 rounded-lg border px-2" value={hours[d]?.closes ?? ""} disabled={!hours[d]?.on} onChange={(e) => setHours({ ...hours, [d]: { ...hours[d], closes: e.target.value } })} />
            </div>
          ))}
          <Button onClick={() => run(() => saveHours(Object.entries(hours).filter(([, v]) => v.on).map(([d, v]) => ({ weekday: Number(d), opens: v.opens, closes: v.closes }))))}>{tr("Save hours")}</Button>
        </div>
      </section>

      <section>
        <h2 className="mb-2 text-lg font-semibold">{tr("Days off")}</h2>
        <form
          className="mb-3 flex flex-wrap gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            run(() => addTimeOff(String(f.get("date")), String(f.get("reason") || "") || undefined));
            e.currentTarget.reset();
          }}
        >
          <input name="date" type="date" className="h-10 rounded-[10px] border px-2" required />
          <input name="reason" className="h-10 rounded-[10px] border px-2" placeholder={tr("Reason (optional)")} maxLength={120} />
          <Button type="submit">{tr("Add day off")}</Button>
        </form>
        <ul className="divide-y rounded-xl border">
          {s.time_off.length === 0 && <li className="p-3 text-sm text-muted-foreground">{tr("No days off planned.")}</li>}
          {s.time_off.map((t) => (
            <li key={t.id} className="flex items-center justify-between p-3 text-sm"><span>{t.date}{t.reason ? ` · ${t.reason}` : ""}</span><Button size="sm" variant="outline" onClick={() => run(() => removeTimeOff(t.id))}>{tr("Remove")}</Button></li>
          ))}
        </ul>
      </section>
    </div>
  );
}
