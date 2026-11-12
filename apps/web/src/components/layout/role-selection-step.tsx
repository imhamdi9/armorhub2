"use client";

import { useId, useState, type ReactNode } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const ROLE_ICONS = {
  design: <svg {...svg} className="size-5"><path d="M12 3a9 9 0 1 0 0 18c1.400 0 2-.8 2-1.700 0-1.500-1.500-1.800-1.500-3.100 0-1 .8-1.700 1.800-1.700H17a4 4 0 0 0 4-4C21 6.500 17 3 12 3Z" /><circle cx="7.500" cy="11.500" r="1" fill="currentColor" /><circle cx="10.500" cy="7.500" r="1" fill="currentColor" /><circle cx="15" cy="8" r="1" fill="currentColor" /></svg>,
  engineering: <svg {...svg} className="size-5"><path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.500 5l-3 14" /></svg>,
  marketing: <svg {...svg} className="size-5"><path d="M4 10v4h3l7 4V6L7 10H4ZM17.500 9a4 4 0 0 1 0 6" /></svg>,
  product: <svg {...svg} className="size-5"><path d="m12 3 8 4.200v9.600L12 21l-8-4.200V7.200L12 3Z" /><path d="m4.500 7.500 7.500 4 7.500-4M12 11.500V21" /></svg>,
  operations: <svg {...svg} className="size-5"><path d="M4 20V10M10 20V4M16 20v-7M21 20H3" /></svg>,
  other: <svg {...svg} className="size-5"><circle cx="12" cy="8.500" r="3.500" /><path d="M5 20c.8-3.600 3.600-5.500 7-5.500s6.200 1.900 7 5.500" /></svg>,
} satisfies Record<string, ReactNode>;

export interface Role { id: string; name: string; description: string; icon: ReactNode }

/** A role picker built from real radio inputs, so keyboard and screen-reader behaviour comes for free. */
export function RoleSelectionStep({ roles, onContinue, onSkip }: { roles: Role[]; onContinue?: (roleId: string) => void; onSkip?: () => void }) {
  const [role, setRole] = useState<string>("");
  const name = useId();

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <form className="w-full max-w-2xl" onSubmit={(e) => { e.preventDefault(); if (role) onContinue?.(role); }}>
        <fieldset>
          <legend className="w-full text-center">
            <span className="block text-2xl font-semibold tracking-tight text-neutral-900">What do you do?</span>
            <span className="mx-auto mt-2 block max-w-md text-sm leading-relaxed text-neutral-500">We use this to tailor your workspace with the right templates and shortcuts.</span>
          </legend>

          <div className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {roles.map((r) => (
              <label key={r.id} className="relative block cursor-pointer">
                <input type="radio" name={name} value={r.id} checked={role === r.id} onChange={() => setRole(r.id)} className="peer sr-only" />
                <span className="flex h-full min-h-28 flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 transition-colors hover:border-neutral-300 peer-checked:border-neutral-900 peer-checked:ring-2 peer-checked:ring-neutral-900 peer-focus-visible:ring-2 peer-focus-visible:ring-neutral-900 peer-focus-visible:ring-offset-2 peer-checked:[&_[data-badge]]:scale-100 peer-checked:[&_[data-badge]]:opacity-100">
                  <span className="grid size-10 place-items-center rounded-xl bg-neutral-100 text-neutral-700">{r.icon}</span>
                  <span>
                    <span className="block text-sm font-semibold text-neutral-900">{r.name}</span>
                    <span className="mt-0.5 block text-sm leading-snug text-neutral-500">{r.description}</span>
                  </span>
                  <span data-badge aria-hidden="true" className="absolute end-3 top-3 grid size-5 scale-75 place-items-center rounded-full bg-neutral-900 text-white opacity-0 transition duration-150 motion-reduce:transition-none">
                    <svg viewBox="0 0 24 24" className="size-3" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
                  </span>
                </span>
              </label>
            ))}
          </div>
        </fieldset>

        <div className="mt-8 flex items-center justify-between gap-4">
          <button type="button" onClick={onSkip} className={`inline-flex min-h-11 items-center rounded-lg px-2 text-sm font-medium text-neutral-500 underline underline-offset-4 hover:text-neutral-900 ${focus}`}>Skip for now</button>
          <button type="submit" disabled={!role} className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-6 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-50 ${focus}`}>Continue</button>
        </div>
      </form>
    </div>
  );
}
