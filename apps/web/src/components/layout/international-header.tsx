"use client";

import { useCallback, useEffect, useId, useRef, useState, useSyncExternalStore } from "react";

export const LANGUAGES = [
  { code: "en", name: "English" }, { code: "es", name: "Español" }, { code: "fr", name: "Français" }, { code: "de", name: "Deutsch" },
  { code: "it", name: "Italiano" }, { code: "pt", name: "Português" }, { code: "nl", name: "Nederlands" }, { code: "ar", name: "العربية" },
  { code: "ja", name: "日本語" }, { code: "ko", name: "한국어" }, { code: "zh", name: "简体中文" }, { code: "tr", name: "Türkçe" },
] as const;

export const CURRENCIES = [
  { code: "USD", name: "US dollar", symbol: "$", region: "the United States" },
  { code: "EUR", name: "Euro", symbol: "€", region: "the Eurozone" },
  { code: "GBP", name: "British pound", symbol: "£", region: "the United Kingdom" },
  { code: "JPY", name: "Japanese yen", symbol: "¥", region: "Japan" },
  { code: "CAD", name: "Canadian dollar", symbol: "CA$", region: "Canada" },
  { code: "AED", name: "UAE dirham", symbol: "د.إ", region: "the United Arab Emirates" },
  { code: "SAR", name: "Saudi riyal", symbol: "ر.س", region: "Saudi Arabia" },
  { code: "EGP", name: "Egyptian pound", symbol: "E£", region: "Egypt" },
] as const;

const LANG_KEY = "site.language";
const CUR_KEY = "site.currency";
const NAV = ["Products", "Solutions", "Pricing", "Resources", "Company"];

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const ringed = "ring-2 ring-neutral-900 border-transparent bg-neutral-50";

// ---- the stored choice, shared between tabs and between the two pills -----------------------------------------------------
const listeners = new Set<() => void>();
const read = (key: string, allowed: readonly string[], fallback: string) => {
  try {
    const v = window.localStorage.getItem(key);

    return v && allowed.includes(v) ? v : fallback;
  } catch {
    return fallback; // storage blocked (private mode): the choice simply lasts until the page is closed
  }
};
const memory: Record<string, string> = {};

function useStored(key: string, allowed: readonly string[], fallback: string): [string, (v: string) => void] {
  const value = useSyncExternalStore(
    (cb) => {
      listeners.add(cb);
      window.addEventListener("storage", cb);

      return () => { listeners.delete(cb); window.removeEventListener("storage", cb); };
    },
    () => memory[key] ?? read(key, allowed, fallback),
    () => fallback,
  );
  const set = useCallback((v: string) => {
    memory[key] = v;
    try { window.localStorage.setItem(key, v); } catch { /* kept in memory only */ }
    listeners.forEach((l) => l());
  }, [key]);

  return [value, set];
}

const Globe = () => <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c2.600 2.500 3.900 5.500 3.900 9s-1.300 6.500-3.900 9c-2.600-2.500-3.900-5.500-3.900-9S9.400 5.500 12 3Z" /></svg>;
const Chevron = ({ open }: { open: boolean }) => <svg aria-hidden="true" viewBox="0 0 24 24" className={`size-4 shrink-0 text-neutral-500 transition-transform motion-reduce:transition-none ${open ? "rotate-180" : ""}`} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6" /></svg>;
const Tick = () => <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12.500 4.500 4.500L19 7.500" /></svg>;

type Which = "language" | "currency";

/** A site header with a language and currency picker. The choice is stored in the browser; wiring it to the site's routing is up to the page. */
export function InternationalHeader({ onChange }: { onChange?: (choice: { language: string; currency: string }) => void }) {
  const [language, setLanguage] = useStored(LANG_KEY, LANGUAGES.map((l) => l.code), "en");
  const [currency, setCurrency] = useStored(CUR_KEY, CURRENCIES.map((c) => c.code), "USD");
  const [open, setOpen] = useState<Which | null>(null);
  const root = useRef<HTMLElement>(null);
  const pills = useRef<Record<Which, HTMLButtonElement | null>>({ language: null, currency: null });
  const panelId = useId();
  const cur = CURRENCIES.find((c) => c.code === currency) ?? CURRENCIES[0];

  const close = useCallback((returnFocus = false) => {
    setOpen((was) => {
      if (was && returnFocus) pills.current[was]?.focus();

      return null;
    });
  }, []);

  // Escape and a click anywhere outside the header close the panel.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => { if (e.key === "Escape") close(true); };
    const onPointer = (e: PointerEvent) => { if (root.current && !root.current.contains(e.target as Node)) close(); };
    document.addEventListener("keydown", onKey);
    document.addEventListener("pointerdown", onPointer);

    return () => { document.removeEventListener("keydown", onKey); document.removeEventListener("pointerdown", onPointer); };
  }, [open, close]);

  const toggle = (which: Which) => setOpen((was) => (was === which ? null : which));
  const pickLanguage = (code: string) => { setLanguage(code); onChange?.({ language: code, currency }); };
  const pickCurrency = (code: string) => { setCurrency(code); onChange?.({ language, currency: code }); };

  const pill = (which: Which, children: React.ReactNode, label: string) => (
    <button
      ref={(el) => { pills.current[which] = el; }}
      type="button"
      aria-expanded={open !== null}
      aria-controls={panelId}
      aria-label={label}
      onClick={() => toggle(which)}
      className={`inline-flex min-h-11 items-center gap-1.5 rounded-full border px-3 text-sm font-medium text-neutral-800 transition-colors hover:bg-neutral-50 ${open === which ? "border-neutral-900 bg-neutral-50" : "border-neutral-300 bg-white"} ${focus}`}
    >
      {children}
      <Chevron open={open === which} />
    </button>
  );

  return (
    <header ref={root} className="relative border-b border-neutral-200 bg-white">
      <div className="mx-auto flex min-h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
        <a href="#" className={`inline-flex min-h-11 items-center rounded-md text-lg font-semibold tracking-tight text-neutral-900 ${focus}`}>Meridian</a>

        <nav aria-label="Main" className="mx-auto hidden items-center gap-1 lg:flex">
          {NAV.map((item) => (
            <a key={item} href="#" className={`inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>{item}</a>
          ))}
        </nav>

        <div className="ms-auto flex items-center gap-2 lg:ms-0">
          {pill("language", <><Globe /><span className="uppercase">{language}</span></>, `Language: ${LANGUAGES.find((l) => l.code === language)?.name}. Change language or currency`)}
          {pill("currency", <><span>{currency}</span><span className="text-neutral-500" dir="ltr">{cur.symbol}</span></>, `Currency: ${cur.name}. Change language or currency`)}
          <span aria-hidden="true" className="mx-1 hidden h-6 w-px bg-neutral-200 sm:block" />
          <a href="#" className={`hidden min-h-11 items-center rounded-lg px-2 text-sm font-medium text-neutral-700 hover:text-neutral-900 sm:inline-flex ${focus}`}>Sign in</a>
          <a href="#" className={`hidden min-h-11 items-center rounded-full bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 sm:inline-flex ${focus}`}>Get started</a>
        </div>
      </div>

      {/* The panel is always in the page but collapsed and inert until a pill opens it. */}
      <div
        id={panelId}
        role="region"
        aria-label="Language and currency"
        inert={open === null}
        className={`grid border-neutral-200 bg-white transition-[grid-template-rows] duration-200 motion-reduce:transition-none ${open ? "grid-rows-[1fr] border-t" : "grid-rows-[0fr]"}`}
      >
        <div className="overflow-hidden">
          <div className="mx-auto grid max-w-6xl gap-8 px-4 py-6 sm:px-6 md:grid-cols-2">
            <div role="radiogroup" aria-labelledby={`${panelId}-lang`}>
              <h2 id={`${panelId}-lang`} className="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Language</h2>
              <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {LANGUAGES.map((l) => (
                  <button key={l.code} type="button" role="radio" aria-checked={l.code === language} onClick={() => pickLanguage(l.code)}
                    className={`flex min-h-11 items-center justify-between gap-2 rounded-lg border border-neutral-200 px-3 text-start text-sm text-neutral-800 transition-colors hover:bg-neutral-50 ${l.code === language ? ringed : ""} ${focus}`}>
                    <span className="truncate">{l.name}</span>
                    <span className="text-xs uppercase text-neutral-500">{l.code}</span>
                  </button>
                ))}
              </div>
            </div>

            <div role="radiogroup" aria-labelledby={`${panelId}-cur`}>
              <h2 id={`${panelId}-cur`} className="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Currency</h2>
              <div className="space-y-2">
                {CURRENCIES.map((c) => (
                  <button key={c.code} type="button" role="radio" aria-checked={c.code === currency} onClick={() => pickCurrency(c.code)}
                    className={`flex min-h-11 w-full items-center gap-3 rounded-lg border border-neutral-200 px-3 text-start text-sm text-neutral-800 transition-colors hover:bg-neutral-50 ${c.code === currency ? ringed : ""} ${focus}`}>
                    <span className="w-10 font-semibold">{c.code}</span>
                    <span className="flex-1 truncate text-neutral-600">{c.name}</span>
                    <span className="text-neutral-500" dir="ltr">{c.symbol}</span>
                    {c.code === currency && <Tick />}
                  </button>
                ))}
              </div>
              <p className="mt-3 text-xs text-neutral-500" aria-live="polite">Shipping to {cur.region}.</p>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
}
