"use client";

import { useMemo, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const CHANNELS = ["Email", "In-app", "Mobile push"] as const;
type Channel = (typeof CHANNELS)[number];

interface Category { id: string; label: string; required?: boolean; recommended: Channel[] }
export const CATEGORIES: Category[] = [
  { id: "product", label: "Product updates", recommended: ["In-app"] },
  { id: "mentions", label: "Mentions", recommended: ["Email", "In-app", "Mobile push"] },
  { id: "replies", label: "Comment replies", recommended: ["In-app", "Mobile push"] },
  { id: "digest", label: "Weekly digest", recommended: ["Email"] },
  { id: "billing", label: "Billing", recommended: ["Email"] },
  { id: "security", label: "Security alerts", required: true, recommended: ["Email", "In-app", "Mobile push"] },
];

type Matrix = Record<string, Record<Channel, boolean>>;
const HOURS = Array.from({ length: 24 }, (_, h) => `${String(h).padStart(2, "0")}:00`);
const empty = (): Matrix => Object.fromEntries(CATEGORIES.map((c) => [c.id, Object.fromEntries(CHANNELS.map((ch) => [ch, !!c.required])) as Record<Channel, boolean>]));
const recommended = (): Matrix => Object.fromEntries(CATEGORIES.map((c) => [c.id, Object.fromEntries(CHANNELS.map((ch) => [ch, c.recommended.includes(ch)])) as Record<Channel, boolean>]));

function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange?: (v: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <button type="button" role="switch" aria-checked={checked} aria-label={label} disabled={disabled} onClick={() => onChange?.(!checked)}
      className={`grid size-11 place-items-center rounded-full ${focus} disabled:cursor-not-allowed`}>
      <span className={`relative h-6 w-11 rounded-full transition-colors motion-reduce:transition-none ${checked ? "bg-neutral-900" : "bg-neutral-300"} ${disabled ? "opacity-50" : ""}`}>
        <span className={`absolute top-0.5 size-5 rounded-full bg-white shadow transition-[inset-inline-start] motion-reduce:transition-none ${checked ? "start-[1.375rem]" : "start-0.5"}`} />
      </span>
    </button>
  );
}

/** A notification preferences step: a category by channel grid of switches, quiet hours, and a running count. */
export function NotificationPreferencesStep({ step = 4, of = 4, onContinue }: { step?: number; of?: number; onContinue?: (v: { matrix: Matrix; quiet: { on: boolean; from: string; to: string } }) => void }) {
  const [matrix, setMatrix] = useState<Matrix>(empty);
  const [quiet, setQuiet] = useState({ on: false, from: "22:00", to: "07:00" });

  const enabled = useMemo(() => Object.values(matrix).reduce((t, row) => t + Object.values(row).filter(Boolean).length, 0), [matrix]);
  const total = CATEGORIES.length * CHANNELS.length;
  const set = (id: string, ch: Channel, v: boolean) => setMatrix((m) => ({ ...m, [id]: { ...m[id], [ch]: v } }));

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="notif-title" className="w-full max-w-2xl rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <p className="text-xs font-medium text-neutral-500">Step {step} of {of}</p>
        <h1 id="notif-title" className="mt-2 text-2xl font-semibold tracking-tight text-neutral-900">Choose your notifications</h1>
        <p className="mt-2 text-sm leading-relaxed text-neutral-500">Nothing is switched on by default, except the alerts that keep your account secure.</p>

        <div className="mt-6 overflow-x-auto rounded-xl border border-neutral-200">
          <table className="w-full min-w-[28rem] text-sm">
            <caption className="sr-only">Notification categories and the channels they use</caption>
            <thead className="bg-neutral-50 text-xs font-medium text-neutral-500">
              <tr>
                <th scope="col" className="px-4 py-2.5 text-start font-medium">Category</th>
                {CHANNELS.map((c) => <th key={c} scope="col" className="w-24 px-1 py-2.5 text-center font-medium">{c}</th>)}
              </tr>
            </thead>
            <tbody className="divide-y divide-neutral-100">
              {CATEGORIES.map((c) => (
                <tr key={c.id} data-category={c.id}>
                  <th scope="row" className="px-4 py-2 text-start font-medium text-neutral-900">
                    {c.label}
                    {c.required && <span className="block text-xs font-normal text-neutral-500">Required</span>}
                  </th>
                  {CHANNELS.map((ch) => (
                    <td key={ch} className="px-1 py-1 text-center">
                      <div className="flex justify-center">
                        <Switch checked={matrix[c.id][ch]} disabled={c.required} label={`${c.label} by ${ch}`} onChange={(v) => set(c.id, ch, v)} />
                      </div>
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="mt-5 rounded-xl border border-neutral-200 p-4">
          <div className="flex items-center gap-3">
            <div className="me-auto">
              <p id="quiet-label" className="text-sm font-medium text-neutral-900">Quiet hours</p>
              <p className="text-xs text-neutral-500">Pause push and email alerts overnight. Security alerts still come through.</p>
            </div>
            <Switch checked={quiet.on} label="Quiet hours" onChange={(v) => setQuiet((q) => ({ ...q, on: v }))} />
          </div>
          <div data-quiet-fields aria-disabled={!quiet.on} className={`mt-3 flex flex-wrap items-center gap-3 text-sm transition-opacity motion-reduce:transition-none ${quiet.on ? "" : "opacity-50"}`}>
            {([["from", "From"], ["to", "To"]] as const).map(([key, label]) => (
              <label key={key} className="flex items-center gap-2 text-neutral-600">
                {label}
                <select value={quiet[key]} disabled={!quiet.on} onChange={(e) => setQuiet((q) => ({ ...q, [key]: e.target.value }))}
                  className="min-h-11 rounded-lg border border-neutral-300 bg-white px-2 text-neutral-900 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20 disabled:cursor-not-allowed">
                  {HOURS.map((h) => <option key={h}>{h}</option>)}
                </select>
              </label>
            ))}
          </div>
        </div>

        <div className="mt-6 flex flex-wrap items-center gap-3">
          <p data-summary className="me-auto text-sm text-neutral-500" aria-live="polite">{enabled} of {total} notifications on</p>
          <button type="button" onClick={() => { setMatrix(recommended()); setQuiet((q) => ({ ...q, on: true })); }} className={`inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>Use recommended</button>
          <button type="button" onClick={() => onContinue?.({ matrix, quiet })} className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-6 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Continue</button>
        </div>
      </section>
    </div>
  );
}
