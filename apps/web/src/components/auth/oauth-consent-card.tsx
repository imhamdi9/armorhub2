"use client";

import type { ReactNode } from "react";

const focus =
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

const icon = "size-5";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const GLYPHS = {
  profile: <svg {...svg} className={icon}><circle cx="12" cy="8" r="3.5" /><path d="M5 20c.8-3.6 3.6-5.5 7-5.5s6.2 1.9 7 5.5" /></svg>,
  projects: <svg {...svg} className={icon}><path d="M3.5 7.5A1.5 1.5 0 0 1 5 6h4l2 2h8a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 19 19H5a1.5 1.5 0 0 1-1.5-1.5v-10Z" /></svg>,
  comments: <svg {...svg} className={icon}><path d="M5 5h14a1.5 1.5 0 0 1 1.5 1.5v8A1.5 1.5 0 0 1 19 16h-7l-4.5 3.5V16H5a1.5 1.5 0 0 1-1.5-1.5v-8A1.5 1.5 0 0 1 5 5Z" /></svg>,
  members: <svg {...svg} className={icon}><circle cx="9" cy="8.5" r="3" /><path d="M3.5 19c.6-3 2.9-4.7 5.5-4.7s4.9 1.7 5.5 4.7" /><path d="M16 5.8a3 3 0 0 1 0 5.4M17.5 14.5c1.7.6 2.7 2.2 3 4.5" /></svg>,
} satisfies Record<string, ReactNode>;

export interface Permission {
  /** A key into GLYPHS, or your own icon. */
  glyph: ReactNode;
  title: string;
  description: string;
  readOnly?: boolean;
}

export interface OAuthConsentCardProps {
  app: string;
  product: string;
  /** The account the app would be connected to. */
  account: string;
  permissions: Permission[];
  developer: string;
  redirectHost: string;
  onAllow?: () => void;
  onCancel?: () => void;
  busy?: boolean;
}

/** An OAuth authorisation screen. It only draws the screen: allowing and cancelling are wired through the callbacks. */
export function OAuthConsentCard({ app, product, account, permissions, developer, redirectHost, onAllow, onCancel, busy = false }: OAuthConsentCardProps) {
  const initial = (s: string) => (s.trim()[0] ?? "?").toUpperCase();

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="consent-title" className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="flex flex-col items-center text-center">
          <div className="flex items-center gap-3" aria-hidden="true">
            <span className="grid size-14 place-items-center rounded-2xl bg-amber-400 text-xl font-bold text-amber-950">{initial(app)}</span>
            <svg viewBox="0 0 24 24" className="size-5 text-neutral-400 rtl:-scale-x-100" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            <span className="grid size-14 place-items-center rounded-2xl bg-neutral-900 text-xl font-bold text-white">{initial(product)}</span>
          </div>

          <h1 id="consent-title" className="mt-5 text-xl font-semibold tracking-tight text-neutral-900">{app} wants to access your workspace</h1>
          <p className="mt-1.5 text-sm text-neutral-500">
            It would connect to <span className="font-medium text-neutral-700" dir="ltr">{account}</span>
          </p>
        </div>

        <ul className="mt-6 divide-y divide-neutral-200 rounded-xl border border-neutral-200" aria-label="Permissions requested">
          {permissions.map((p) => (
            <li key={p.title} className="flex items-start gap-3 px-4 py-3.5">
              <span aria-hidden="true" className="grid size-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-700">{p.glyph}</span>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                  <p className="text-sm font-medium text-neutral-900">{p.title}</p>
                  {p.readOnly && <span className="rounded-full border border-neutral-200 bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-600">Read only</span>}
                </div>
                <p className="mt-0.5 text-sm leading-snug text-neutral-500">{p.description}</p>
              </div>
            </li>
          ))}
        </ul>

        <p className="mt-4 flex items-start gap-2 rounded-lg bg-amber-50 px-3 py-2.5 text-sm leading-snug text-amber-900" role="note">
          <svg aria-hidden="true" viewBox="0 0 24 24" className="mt-0.5 size-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 4 3 19h18L12 4Z" /><path d="M12 10v4M12 17h.01" /></svg>
          You can revoke this access at any time from your settings.
        </p>

        <div className="mt-6 space-y-2">
          <button type="button" onClick={onAllow} disabled={busy} className={`inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60 ${focus}`}>
            Allow access
          </button>
          <button type="button" onClick={onCancel} disabled={busy} className={`inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-50 disabled:opacity-60 ${focus}`}>
            Cancel
          </button>
        </div>

        <p className="mt-5 text-center text-xs leading-relaxed text-neutral-500">
          Built by {developer}. You will be sent back to <span className="font-mono text-neutral-700" dir="ltr">{redirectHost}</span>.
        </p>
      </section>
    </div>
  );
}
