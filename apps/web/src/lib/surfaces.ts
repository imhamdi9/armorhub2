import type { Locale } from "@/lib/i18n";

/** Published, admin-editable content for one surface, per language. Missing fields fall back to the built-in wording. */
export type Surface = Partial<Record<Locale, Record<string, unknown>>>;

export interface SiteContent {
  home: Surface;
  brand: Surface;
  nav: Surface;
}

/**
 * The text an admin set for the current language, or null when there is none (the caller then shows the built-in,
 * already-translated wording). English overrides apply to English only: an Arabic visitor never sees an English override.
 */
export function pickText(surface: Surface | undefined, locale: Locale, field: string): string | null {
  const value = surface?.[locale]?.[field];

  return typeof value === "string" && value.trim() !== "" ? value : null;
}

/** On/off switches apply to every language and are stored with the English content. Default: on. */
export function pickFlag(surface: Surface | undefined, field: string): boolean {
  const value = surface?.en?.[field];

  return typeof value === "boolean" ? value : true;
}

export function pickList<T extends Record<string, string>>(surface: Surface | undefined, locale: Locale, field: string): T[] {
  const value = surface?.[locale]?.[field];

  return Array.isArray(value) ? (value as T[]) : [];
}

/** Safe font stacks an admin can pick by name. Nothing typed by an admin is ever used as CSS. */
export const FONT_STACKS: Record<string, string> = {
  system: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif',
  serif: 'Georgia, "Times New Roman", "Noto Naskh Arabic", serif',
  rounded: 'ui-rounded, "SF Pro Rounded", "Nunito", "Segoe UI", system-ui, sans-serif',
  mono: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
};

export const SECTION_KEYS = [
  "partner_strip", "industries", "stores", "quote_feature", "dashboard_feature", "value_props", "partners", "use_cases", "why", "how", "bento",
  "developers", "compat", "markets", "faq",
] as const;
export type SectionKey = (typeof SECTION_KEYS)[number];

/** The admin's order first, then any section they did not mention in the built-in order. */
export function orderSections(chosen: string[]): SectionKey[] {
  const picked = chosen.filter((k, i): k is SectionKey => (SECTION_KEYS as readonly string[]).includes(k) && chosen.indexOf(k) === i);

  return [...picked, ...SECTION_KEYS.filter((k) => !picked.includes(k))];
}

/** An uploaded image is stored as /media/<name>; this turns it into the address the browser loads (https addresses pass through). */
export function imageSrc(value: string | null): string | null {
  if (!value || !value.startsWith("/media/")) return value;

  return `${(process.env.NEXT_PUBLIC_API_URL ?? "/api").replace(/\/$/, "")}/public${value}`;
}
