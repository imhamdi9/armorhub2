"use client";

import { createContext, useContext, useEffect, useState, useSyncExternalStore } from "react";

import { categoryNamesReady, loadCategoryNames } from "@/lib/category-names";
import { AR } from "@/lib/i18n-ar";
import { loadPlatformBrand, withPlatformName } from "@/lib/platform-brand";

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
  const base = withPlatformName(current === "ar" ? (AR[text] ?? text) : text, current);

  return vars ? base.replace(/\{(\w+)\}/g, (_, key) => String(vars[key] ?? `{${key}}`)) : base;
}

export const formatDate = (value: string | number | Date) =>
  new Date(value).toLocaleDateString(LOCALE_TAGS[current]);

export const formatDateTime = (value: string | number | Date) =>
  new Date(value).toLocaleString(LOCALE_TAGS[current]);

export const formatNumber = (n: number, options?: Intl.NumberFormatOptions) =>
  n.toLocaleString(LOCALE_TAGS[current], options);

/** Money in the viewer's language with the right symbol, e.g. "SAR 250.00" or "٢٥٠٫٠٠ ر.س.‏". */
export function formatMoney(amount: number | string, currency: string, whole = false): string {
  const n = Number(amount);
  const digits = whole ? { minimumFractionDigits: 0, maximumFractionDigits: 2 } : { minimumFractionDigits: 2, maximumFractionDigits: 2 };
  try {
    return n.toLocaleString(LOCALE_TAGS[current], { style: "currency", currency, ...digits });
  } catch {
    return `${formatNumber(n, digits)} ${currency}`; // unknown currency code
  }
}

// Also records the locale for tr(). It runs as the external-store snapshot reader (never on the
// server and not during hydration, which uses the English server snapshot), so text matches SSR.
export const currentLocale = (): Locale => current;

export function applyLocale(locale: Locale): Locale {
  current = locale;
  return current;
}

// The server renders the public pages too, and it cannot read localStorage: the language also travels in a cookie, so product names and
// categories come back in the visitor's language from the very first byte.
function rememberForServer(locale: Locale) {
  try { document.cookie = `${STORAGE_KEY}=${locale}; path=/; max-age=31536000; SameSite=Lax`; } catch { /* cookies unavailable */ }
}

function readLocale(): Locale {
  const locale = window.localStorage.getItem(STORAGE_KEY) === "ar" ? "ar" : "en";
  if (!document.cookie.includes(`${STORAGE_KEY}=${locale}`)) rememberForServer(locale);
  return applyLocale(locale);
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
  rememberForServer(locale);
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
  // Category names (in both languages) come from the API once; screens that print a category draw again when they arrive.
  // The platform's own name (set by its owners) replaces the built-in one in every sentence once it is known.
  // Both are waited for together, so the page is redrawn once for them, not once for each.
  const [loaded, setLoaded] = useState(() => (categoryNamesReady() ? "names" : ""));
  useEffect(() => {
    let live = true;
    Promise.all([categoryNamesReady() ? true : loadCategoryNames(), loadPlatformBrand()]).then(([names, brand]) => {
      if (live) setLoaded(`${names ? "names" : ""}${brand ? "+brand" : ""}`);
    });
    return () => { live = false; };
  }, []);

  useEffect(() => {
    document.documentElement.setAttribute("lang", locale);
    document.documentElement.setAttribute("dir", locale === "ar" ? "rtl" : "ltr");
  }, [locale]);

  // Page titles are set by the server in English ("Your cart", "Products — <store>"): the tab says the same in the reader's language.
  useEffect(() => {
    const translateTitle = () => {
      const now = document.title;
      const next = now.split(" — ").map((part) => tr(part)).join(" — ");
      if (next !== now) document.title = next;
    };
    translateTitle();
    const el = document.querySelector("title");
    if (!el) return;
    const watch = new MutationObserver(translateTitle);
    watch.observe(el, { childList: true, characterData: true, subtree: true });
    return () => watch.disconnect();
  }, [locale]);

  return (
    <LocaleContext.Provider value={locale}>
      {(hydrated || !gate) && (
        <div key={`${locale}-${loaded}`} className="contents">
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
