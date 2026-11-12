"use client";

import { useEffect, useState, useSyncExternalStore } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export const CONSENT_KEY = "cookie-consent";
export const CONSENT_EVENT = "cookie-consent";
export type ConsentDecision = "accepted" | "rejected";
export interface ConsentDetail { decision: ConsentDecision; timestamp: string; /** True when this is a remembered choice replayed on page load rather than a click. */ stored: boolean }

// ---- storage: every call is guarded, because private-mode browsers and blocked storage throw ------------------------------
let memory: { decision: ConsentDecision; timestamp: string } | null = null; // the fallback when storage is unavailable: lasts until the page closes

/** The saved choice, or null when there is none (or it cannot be read). Safe to call anywhere. */
export function readConsent(): { decision: ConsentDecision; timestamp: string } | null {
  try {
    const raw = window.localStorage.getItem(CONSENT_KEY);
    const v = raw ? JSON.parse(raw) : null;
    if (v && (v.decision === "accepted" || v.decision === "rejected") && typeof v.timestamp === "string") return v;
  } catch { /* unavailable or corrupt: fall through */ }

  return memory;
}

function writeConsent(value: { decision: ConsentDecision; timestamp: string }) {
  memory = value;
  try { window.localStorage.setItem(CONSENT_KEY, JSON.stringify(value)); } catch { /* the choice still holds for this page view */ }
}

const listeners = new Set<() => void>();
const subscribe = (cb: () => void) => { listeners.add(cb); return () => { listeners.delete(cb); }; };
const snapshot = () => (readConsent() ? "decided" : "undecided");

/**
 * Analytics, embeds and anything else that needs the visitor's consent should listen for this event instead of being wired into the banner:
 *   window.addEventListener("cookie-consent", (e) => { if (e.detail.decision === "accepted") startAnalytics(); });
 */
function announce(detail: ConsentDetail) {
  try { window.dispatchEvent(new CustomEvent<ConsentDetail>(CONSENT_EVENT, { detail })); } catch { /* very old browsers: nothing to notify */ }
}

/** A bottom cookie-consent bar. The decision and its time are stored, the bar slides away, and later visits never show it again. */
export function CookieConsentBanner({ policyHref = "#privacy" }: { policyHref?: string }) {
  // "unknown" on the server, so the bar is never in the server's HTML and can't flash for someone who already chose.
  const state = useSyncExternalStore(subscribe, snapshot, () => "unknown" as const);
  const [leaving, setLeaving] = useState(false);
  const [entered, setEntered] = useState(false);
  const [gone, setGone] = useState(false);

  // A returning visitor's choice is replayed once, so scripts that load later still hear about it.
  // (Deferred a tick: a child's effect runs before its parent's, so a page that subscribes in its own effect would otherwise miss it.)
  useEffect(() => {
    const saved = readConsent();
    if (!saved) return;
    const id = setTimeout(() => announce({ ...saved, stored: true }), 0);

    return () => clearTimeout(id);
  }, []);

  useEffect(() => { if (state === "undecided") { const f = requestAnimationFrame(() => setEntered(true)); return () => cancelAnimationFrame(f); } }, [state]);

  function choose(decision: ConsentDecision) {
    const value = { decision, timestamp: new Date().toISOString() };
    writeConsent(value);
    setLeaving(true);
    announce({ ...value, stored: false });
    // The slide-down is a CSS transition; the timeout removes the bar afterwards (and covers reduced motion, where no transition runs).
    setTimeout(() => { setGone(true); listeners.forEach((l) => l()); }, 320);
  }

  if (gone || (state !== "undecided" && !leaving)) return null;

  return (
    <div role="region" aria-label="Cookie consent" data-cookie-banner className={`fixed inset-x-0 bottom-0 z-50 p-3 transition duration-300 ease-out motion-reduce:transition-none sm:p-4 ${entered && !leaving ? "translate-y-0 opacity-100" : "translate-y-full opacity-0"}`}>
      <div className="mx-auto flex max-w-3xl flex-col gap-3 rounded-2xl border border-neutral-200 bg-white p-4 shadow-lg shadow-neutral-900/10 sm:flex-row sm:items-center sm:gap-5">
        <p className="text-sm leading-relaxed text-neutral-600">
          We use cookies to keep the site working and to understand how it is used. You can choose to accept them all or reject the optional ones.{" "}
          <a href={policyHref} className={`inline-flex min-h-11 items-center rounded font-medium text-neutral-900 underline underline-offset-2 sm:min-h-0 ${focus}`}>Read our privacy policy</a>
        </p>
        <div className="flex shrink-0 items-center gap-2">
          <button type="button" onClick={() => choose("rejected")} className={`inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 transition-colors hover:bg-neutral-50 sm:flex-none ${focus}`}>Reject</button>
          <button type="button" onClick={() => choose("accepted")} className={`inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 sm:flex-none ${focus}`}>Accept all</button>
        </div>
      </div>
    </div>
  );
}
