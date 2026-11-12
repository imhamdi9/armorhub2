"use client";

import { useEffect, useState, useSyncExternalStore } from "react";

export type ToastVariant = "success" | "error" | "info";
interface ToastItem { id: number; title: string; message: string; variant: ToastVariant }

export const TOAST_MS = 5000;

// ---- a tiny store, so toast() can be called from anywhere (an event handler, a fetch callback, outside React) ---------------
let items: ToastItem[] = [];
let nextId = 1;
const listeners = new Set<() => void>();
const emit = () => listeners.forEach((l) => l());

/** Shows a toast. Several can be on screen at once; each removes itself after five seconds. Returns its id. */
export function toast(title: string, message = "", variant: ToastVariant = "info"): number {
  const id = nextId++;
  items = [...items, { id, title, message, variant }];
  emit();

  return id;
}
const remove = (id: number) => { items = items.filter((t) => t.id !== id); emit(); };
const subscribe = (cb: () => void) => { listeners.add(cb); return () => { listeners.delete(cb); }; };

const DOT = { success: "bg-emerald-100 text-emerald-600", error: "bg-rose-100 text-rose-600", info: "bg-sky-100 text-sky-600" } as const;
const BAR = { success: "bg-emerald-500", error: "bg-rose-500", info: "bg-sky-500" } as const;

function Icon({ variant }: { variant: ToastVariant }) {
  const p = { viewBox: "0 0 24 24", fill: "none", stroke: "currentColor", strokeWidth: 2.5, strokeLinecap: "round", strokeLinejoin: "round", "aria-hidden": true, className: "size-3.5" } as const;

  return variant === "success" ? <svg {...p}><path d="m5 12.5 4.5 4.5L19 7.5" /></svg> : variant === "error" ? <svg {...p}><path d="M12 7v6M12 17h.01" /></svg> : <svg {...p}><path d="M12 11v5M12 8h.01" /></svg>;
}

function ToastCard({ t }: { t: ToastItem }) {
  const [shown, setShown] = useState(false);   // slides in on the first frame after mounting
  const [leaving, setLeaving] = useState(false);

  useEffect(() => { const f = requestAnimationFrame(() => setShown(true)); return () => cancelAnimationFrame(f); }, []);

  // After sliding out, take it off the stack. A timeout backs up the transition event for browsers that skip it (reduced motion, hidden tab).
  useEffect(() => {
    if (!leaving) return;
    const id = setTimeout(() => remove(t.id), 260);

    return () => clearTimeout(id);
  }, [leaving, t.id]);

  return (
    <div
      role={t.variant === "error" ? "alert" : "status"}
      data-toast data-variant={t.variant}
      className={`group pointer-events-auto relative w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg shadow-neutral-900/10 transition duration-200 ease-out motion-reduce:transition-none ${shown && !leaving ? "translate-x-0 opacity-100" : "translate-x-8 opacity-0 rtl:-translate-x-8"}`}
    >
      <div className="flex items-start gap-3 p-4 pb-5">
        <span aria-hidden="true" className={`mt-0.5 grid size-6 shrink-0 place-items-center rounded-full ${DOT[t.variant]}`}><Icon variant={t.variant} /></span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold text-neutral-900">{t.title}</p>
          {t.message && <p className="mt-0.5 text-sm leading-snug text-neutral-500">{t.message}</p>}
        </div>
        <button type="button" aria-label="Dismiss notification" onClick={() => setLeaving(true)}
          className="-me-2 -mt-2 grid size-11 shrink-0 place-items-center rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900">
          <svg viewBox="0 0 24 24" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
        </button>
      </div>

      {/* The timer is a CSS animation, so hovering (which pauses it) needs no script. When it ends, the toast slides out. */}
      <div className="absolute inset-x-0 bottom-0 h-1 bg-neutral-100" aria-hidden="true">
        <div data-timer onAnimationEnd={() => setLeaving(true)} style={{ animationName: "toast-timer", animationDuration: `${TOAST_MS}ms`, animationTimingFunction: "linear", animationFillMode: "forwards" }} className={`h-full origin-left group-hover:[animation-play-state:paused] rtl:origin-right ${BAR[t.variant]}`} />
      </div>
    </div>
  );
}

/** Mount once near the root of the page. Toasts stack at the bottom-right, newest at the bottom. */
export function ToastViewport() {
  const list = useSyncExternalStore(subscribe, () => items, () => items);

  return (
    <div aria-label="Notifications" className="pointer-events-none fixed bottom-4 end-4 z-50 flex flex-col items-end gap-2">
      {list.map((t) => <ToastCard key={t.id} t={t} />)}
    </div>
  );
}
