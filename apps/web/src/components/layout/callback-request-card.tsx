"use client";

import { useState, useSyncExternalStore } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const COUNTRIES = [["+1", "US/CA"], ["+44", "UK"], ["+20", "EG"], ["+966", "SA"], ["+971", "AE"], ["+49", "DE"], ["+33", "FR"], ["+91", "IN"]] as const;
const ZONES = ["Africa/Cairo", "America/Chicago", "America/Los_Angeles", "America/New_York", "Asia/Dubai", "Asia/Kolkata", "Asia/Riyadh", "Europe/Berlin", "Europe/London", "Europe/Paris", "UTC"];

export type SlotId = "this-morning" | "this-afternoon" | "this-evening" | "tomorrow-morning" | "tomorrow-afternoon" | "any";
export const SLOTS: { id: SlotId; label: string; /** Latest local hour (0-24) at which the slot can still be booked today; tomorrow's are always open. */ cutoff?: number }[] = [
  { id: "this-morning", label: "This morning", cutoff: 11 }, { id: "this-afternoon", label: "This afternoon", cutoff: 16 }, { id: "this-evening", label: "This evening", cutoff: 18 },
  { id: "tomorrow-morning", label: "Tomorrow morning" }, { id: "tomorrow-afternoon", label: "Tomorrow afternoon" }, { id: "any", label: "Any time" },
];

/** The hour (0-23) it is right now in a time zone, or -1 when the clock is not known yet (on the server). */
const hourIn = (zone: string, minute: number) => {
  if (!minute) return -1;
  try { return Number(new Intl.DateTimeFormat("en-GB", { hour: "2-digit", hourCycle: "h23", timeZone: zone }).format(minute * 60000)); } catch { return -1; }
};
const never = () => () => undefined;

/** A callback request card: a phone number, a time zone and a preferred window. Slots that can no longer be met today are dimmed. */
export function CallbackRequestCard({ onSubmit }: { onSubmit?: (r: { phone: string; zone: string; slot: SlotId; topic: string }) => void }) {
  const detected = useSyncExternalStore(never, () => Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC", () => "UTC");
  const minute = useSyncExternalStore(never, () => Math.floor(Date.now() / 60000), () => 0);
  const [country, setCountry] = useState("+1");
  const [phone, setPhone] = useState("");
  const [zoneChoice, setZoneChoice] = useState<string | null>(null);
  const [slotChoice, setSlotChoice] = useState<SlotId | null>(null);
  const [topic, setTopic] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [sent, setSent] = useState(false);

  const zone = zoneChoice ?? detected;
  const zones = ZONES.includes(zone) ? ZONES : [zone, ...ZONES];
  const hour = hourIn(zone, minute);
  const available = (s: (typeof SLOTS)[number]) => s.cutoff === undefined || hour < 0 || hour < s.cutoff;
  // Until the person picks, the soonest open slot is chosen for them.
  const slot = slotChoice && available(SLOTS.find((s) => s.id === slotChoice)!) ? slotChoice : (SLOTS.find(available)?.id ?? "any");
  const digits = phone.replace(/\D/g, "");

  function submit(e: React.FormEvent) {
    e.preventDefault();
    if (digits.length < 6 || digits.length > 14) { setError("Enter a phone number we can call, without the country code."); return; }
    setError(null);
    onSubmit?.({ phone: `${country}${digits.replace(/^0+/, "")}`, zone, slot, topic: topic.trim() });
    setSent(true);
  }

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="callback-title" className="w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <span aria-hidden="true" className="grid size-12 place-items-center rounded-xl bg-neutral-900 text-white">
          <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z" /></svg>
        </span>
        <h1 id="callback-title" className="mt-5 text-2xl font-semibold tracking-tight text-neutral-900">We will call you</h1>
        <p className="mt-2 text-sm leading-relaxed text-neutral-500">Most calls happen within the hour during office hours.</p>

        {sent ? (
          <p role="status" data-sent className="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            Thanks. We will call {country} {digits} {slot === "any" ? "as soon as we can" : SLOTS.find((s) => s.id === slot)!.label.toLowerCase()}.
          </p>
        ) : (
          <form className="mt-6 space-y-5" onSubmit={submit} noValidate>
            <div>
              <label htmlFor="cb-phone" className="mb-1.5 block text-sm font-medium text-neutral-800">Phone number</label>
              <div className={`flex min-h-11 items-stretch overflow-hidden rounded-xl border bg-white focus-within:ring-2 ${error ? "border-rose-400 focus-within:ring-rose-200" : "border-neutral-300 focus-within:border-neutral-900 focus-within:ring-neutral-900/20"}`}>
                <select aria-label="Country code" value={country} onChange={(e) => setCountry(e.target.value)} className="min-h-11 border-0 border-e border-neutral-200 bg-neutral-50 ps-3 pe-2 text-sm text-neutral-700 focus:outline-none" dir="ltr">
                  {COUNTRIES.map(([code, name]) => <option key={code} value={code}>{name} {code}</option>)}
                </select>
                <input id="cb-phone" type="tel" inputMode="tel" autoComplete="tel-national" value={phone} onChange={(e) => { setPhone(e.target.value); setError(null); }} placeholder="555 010 2030" aria-invalid={error ? true : undefined} aria-describedby={error ? "cb-error" : undefined} dir="ltr"
                  className="min-h-11 min-w-0 flex-1 bg-transparent px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none" />
              </div>
              {error && <p id="cb-error" role="alert" className="mt-1.5 text-xs text-rose-600">{error}</p>}
            </div>

            <div>
              <label htmlFor="cb-zone" className="mb-1.5 block text-sm font-medium text-neutral-800">Your time zone</label>
              <select id="cb-zone" value={zone} onChange={(e) => setZoneChoice(e.target.value)} className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm text-neutral-900 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20">
                {zones.map((z) => <option key={z} value={z}>{z.replace(/_/g, " ")}</option>)}
              </select>
            </div>

            <fieldset>
              <legend className="mb-1.5 text-sm font-medium text-neutral-800">Preferred time</legend>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {SLOTS.map((s) => {
                  const ok = available(s);

                  return (
                    <label key={s.id} className={`relative block ${ok ? "cursor-pointer" : "cursor-not-allowed"}`}>
                      <input type="radio" name="slot" value={s.id} checked={slot === s.id} disabled={!ok} onChange={() => setSlotChoice(s.id)} className="peer sr-only" />
                      <span className={`flex min-h-11 items-center justify-center rounded-xl border px-2 text-center text-sm font-medium transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-neutral-900 peer-focus-visible:ring-offset-2 peer-checked:border-neutral-900 peer-checked:bg-neutral-900 peer-checked:text-white ${ok ? "border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50" : "border-neutral-200 bg-neutral-50 text-neutral-400 opacity-60"}`}>{s.label}</span>
                    </label>
                  );
                })}
              </div>
            </fieldset>

            <div>
              <label htmlFor="cb-topic" className="mb-1.5 block text-sm font-medium text-neutral-800">What is it about? <span className="font-normal text-neutral-400">(optional)</span></label>
              <input id="cb-topic" type="text" maxLength={100} value={topic} onChange={(e) => setTopic(e.target.value)} placeholder="A question about pricing" className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20" />
            </div>

            <button type="submit" className={`inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Request a call</button>
            <p className="flex items-start gap-2 text-xs leading-relaxed text-neutral-500">
              <svg aria-hidden="true" viewBox="0 0 24 24" className="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 5 6v5.500c0 4.400 2.900 8.200 7 9.500 4.100-1.300 7-5.100 7-9.500V6l-7-3Z" /></svg>
              We use your number only for this call. It is never added to a marketing list.
            </p>
          </form>
        )}
      </section>
    </div>
  );
}
