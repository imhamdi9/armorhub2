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
