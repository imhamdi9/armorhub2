"use client";

import { useCallback, useEffect, useId, useRef, useState, useSyncExternalStore, type ReactNode } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const WELCOME_GLYPHS = {
  sparkle: <svg {...svg} className="size-16" strokeWidth={1.25}><path d="M12 3l1.900 5.100L19 10l-5.100 1.900L12 17l-1.900-5.100L5 10l5.100-1.900L12 3ZM18.500 16l.8 2.200 2.200.8-2.200.8-.8 2.200-.8-2.200-2.200-.8 2.200-.8.8-2.200Z" /></svg>,
  project: <svg {...svg} className="size-5"><path d="M3.500 7.500A1.500 1.500 0 0 1 5 6h4l2 2h8a1.500 1.500 0 0 1 1.500 1.500v8A1.500 1.500 0 0 1 19 19H5a1.500 1.500 0 0 1-1.500-1.500v-10Z" /></svg>,
  import: <svg {...svg} className="size-5"><path d="M12 4v11M7 11l5 5 5-5M5 20h14" /></svg>,
  team: <svg {...svg} className="size-5"><circle cx="9" cy="8.500" r="3" /><path d="M3.500 19c.6-3 2.900-4.700 5.500-4.700s4.900 1.700 5.500 4.700M16 5.800a3 3 0 0 1 0 5.400M17.500 14.500c1.700.6 2.700 2.200 3 4.500" /></svg>,
} satisfies Record<string, ReactNode>;

export interface WelcomeAction { title: string; description: string; href: string; glyph: ReactNode }

// ---- "do not show again", remembered in this browser ----------------------------------------------------------------------
const listeners = new Set<() => void>();
const readFlag = (key: string): "show" | "hidden" => { try { return window.localStorage.getItem(key) === "1" ? "hidden" : "show"; } catch { return "show"; } };
const subscribe = (cb: () => void) => { listeners.add(cb); window.addEventListener("storage", cb); return () => { listeners.delete(cb); window.removeEventListener("storage", cb); }; };

/** A first-run welcome dialog. It closes on either button, on Escape and on a click outside the card; ticking the box keeps it from returning. */
export function WelcomeModal({ name, version, actions, storageKey = "welcome.dismissed", onTour, onSkip }: {
  name: string; version: string; actions: [WelcomeAction, WelcomeAction, WelcomeAction]; storageKey?: string; onTour?: () => void; onSkip?: () => void;
}) {
  // "unknown" until the browser has been asked, so the server never renders (and then flashes) the dialog.
  const stored = useSyncExternalStore(subscribe, () => readFlag(storageKey), () => "unknown" as const);
  const [closed, setClosed] = useState(false);
  const [never, setNever] = useState(false);
  const card = useRef<HTMLDivElement>(null);
  const opener = useRef<Element | null>(null);
  const titleId = useId();
  const open = stored === "show" && !closed;

  const close = useCallback((via?: () => void) => {
    if (never) {
      try { window.localStorage.setItem(storageKey, "1"); } catch { /* the choice just lasts for this visit */ }
      listeners.forEach((l) => l());
    }
    setClosed(true);
    via?.();
  }, [never, storageKey]);

  // Focus moves into the dialog, Tab stays inside it, Escape closes it, the page behind does not scroll, and focus goes back afterwards.
  useEffect(() => {
    if (!open) return;
    opener.current = document.activeElement;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    card.current?.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") { e.preventDefault(); close(); return; }
      if (e.key !== "Tab" || !card.current) return;
      const items = [...card.current.querySelectorAll<HTMLElement>("a[href], button, input")].filter((el) => !el.hasAttribute("disabled"));
      if (items.length === 0) return;
      const first = items[0], last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    document.addEventListener("keydown", onKey);

    return () => { document.removeEventListener("keydown", onKey); document.body.style.overflow = previousOverflow; (opener.current as HTMLElement | null)?.focus?.(); };
  }, [open, close]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-neutral-900/50 p-4 backdrop-blur-sm" onMouseDown={(e) => { if (e.target === e.currentTarget) close(); }} data-backdrop>
      <div ref={card} role="dialog" aria-modal="true" aria-labelledby={titleId} className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div className="relative grid h-40 place-items-center bg-gradient-to-br from-neutral-100 via-neutral-100 to-neutral-200 text-neutral-400" aria-hidden="true">
          {WELCOME_GLYPHS.sparkle}
          <span className="absolute end-3 top-3 rounded-md bg-white/70 px-2 py-0.5 font-mono text-xs text-neutral-600 ring-1 ring-neutral-200">v{version}</span>
        </div>

        <div className="p-6 sm:p-8">
          <h2 id={titleId} className="text-xl font-semibold tracking-tight text-neutral-900">Welcome, {name}</h2>
          <p className="mt-2 text-sm leading-relaxed text-neutral-500">Your workspace is ready. Here are three good places to begin, and you can always find the tour again from the help menu.</p>

          <ul className="mt-5 space-y-2">
            {actions.map((a, i) => (
              <li key={a.title}>
                <a href={a.href} {...(i === 0 ? { "data-autofocus": true } : {})} onClick={() => close()} className={`group flex min-h-16 items-center gap-3 rounded-xl border border-neutral-200 p-3 transition-colors hover:border-neutral-300 hover:bg-neutral-50 ${focus}`}>
                  <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-700">{a.glyph}</span>
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2 text-sm font-medium text-neutral-900">{a.title}{i === 0 && <span className="rounded-full bg-neutral-900 px-2 py-0.5 text-[11px] font-medium text-white">Start here</span>}</span>
                    <span className="block truncate text-sm text-neutral-500">{a.description}</span>
                  </span>
                  <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 shrink-0 text-neutral-400 rtl:-scale-x-100" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m9 6 6 6-6 6" /></svg>
                </a>
              </li>
            ))}
          </ul>

          <div className="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label className="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-neutral-600">
              <input type="checkbox" checked={never} onChange={(e) => setNever(e.target.checked)} className="size-4 rounded border-neutral-300 accent-neutral-900" />
              Do not show this again
            </label>
            <div className="flex items-center gap-2 sm:justify-end">
              <button type="button" onClick={() => close(onSkip)} className={`inline-flex min-h-11 flex-1 items-center justify-center rounded-xl px-4 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 sm:flex-none ${focus}`}>Skip the tour</button>
              <button type="button" onClick={() => close(onTour)} className={`inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 sm:flex-none ${focus}`}>Take the tour</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
