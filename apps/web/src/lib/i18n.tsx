"use client";

import { createContext, useContext, useEffect, useSyncExternalStore } from "react";

import { AR } from "@/lib/i18n-ar";

export type Locale = "en" | "ar";

const STORAGE_KEY = "locale";
const CHANGE_EVENT = "locale-change";

export const LOCALE_TAGS: Record<Locale, string> = { en: "en-US", ar: "ar-SA" };

let current: Locale = "en";

/**
 * Translate an English UI string. Untranslated strings (and the English locale) fall
 * back to the English text, so screens can be translated gradually without breaking.
 * `{name}` placeholders are filled from `vars`.
 */
export function tr(text: string, vars?: Record<string, string | number>): string {
  const base = current === "ar" ? (AR[text] ?? text) : text;

  return vars ? base.replace(/\{(\w+)\}/g, (_, key) => String(vars[key] ?? `{${key}}`)) : base;
}

export const formatDate = (value: string | number | Date) =>
  new Date(value).toLocaleDateString(LOCALE_TAGS[current]);

export const formatDateTime = (value: string | number | Date) =>
  new Date(value).toLocaleString(LOCALE_TAGS[current]);

export const formatNumber = (n: number, options?: Intl.NumberFormatOptions) =>
  n.toLocaleString(LOCALE_TAGS[current], options);

// Also records the locale for tr(). It runs as the external-store snapshot reader (never on the
// server and not during hydration, which uses the English server snapshot), so text matches SSR.
export function applyLocale(locale: Locale): Locale {
  current = locale;
  return current;
}

function readLocale(): Locale {
  return applyLocale(window.localStorage.getItem(STORAGE_KEY) === "ar" ? "ar" : "en");
}

function serverLocale(): Locale {
  return applyLocale("en");
}

function subscribe(onChange: () => void) {
  window.addEventListener(CHANGE_EVENT, onChange);
  window.addEventListener("storage", onChange);
  return () => {
    window.removeEventListener(CHANGE_EVENT, onChange);
    window.removeEventListener("storage", onChange);
  };
}

export function setLocale(locale: Locale) {
  window.localStorage.setItem(STORAGE_KEY, locale);
  window.dispatchEvent(new Event(CHANGE_EVENT));
}

const LocaleContext = createContext<Locale>("en");

// False on the server and during hydration, true afterwards: lets us wait for the browser's
// stored language before drawing anything (see I18nProvider).
const noopSubscribe = () => () => undefined;
const useHydrated = () => useSyncExternalStore(noopSubscribe, () => true, () => false);

export const useLocale = () => useContext(LocaleContext);

/**
 * Provides the locale, flips the page direction for Arabic, and remounts the tree when the
 * user switches language so every `tr()` call re-renders with the new language.
 *
 * The app is drawn only once the browser's stored language is known. Otherwise a visitor who
 * chose Arabic would first get English and then a remount that wipes whatever they already typed.
 * Public pages pass `gate={false}`: they must be rendered on the server (search engines, first paint),
 * and a language switch after load is harmless there because they have no half-typed forms.
 */
export function I18nProvider({ children, gate = true }: { children: React.ReactNode; gate?: boolean }) {
  // The server (and the first client render) is English; the stored choice applies right after.
  const locale = useSyncExternalStore(subscribe, readLocale, serverLocale);
  const hydrated = useHydrated();

  useEffect(() => {
    document.documentElement.setAttribute("lang", locale);
    document.documentElement.setAttribute("dir", locale === "ar" ? "rtl" : "ltr");
  }, [locale]);

  return (
    <LocaleContext.Provider value={locale}>
      {(hydrated || !gate) && (
        <div key={locale} className="contents">
          {children}
        </div>
      )}
    </LocaleContext.Provider>
  );
}

export function LanguageSwitcher({ className = "" }: { className?: string }) {
  const locale = useLocale();

  return (
    <button
      type="button"
      onClick={() => setLocale(locale === "ar" ? "en" : "ar")}
      className={`rounded-md border px-3 py-1 text-sm hover:bg-muted ${className}`}
      aria-label={locale === "ar" ? "Switch to English" : "التبديل إلى العربية"}
    >
      {locale === "ar" ? "English" : "العربية"}
    </button>
  );
}
