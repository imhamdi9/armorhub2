"use client";

import { createContext, useContext } from "react";

import { tr, useLocale } from "@/lib/i18n";
import { imageSrc, pickFlag, pickList, pickText, type SiteContent } from "@/lib/surfaces";

const EMPTY: SiteContent = { home: {}, brand: {}, nav: {} };
const SiteContext = createContext<SiteContent>(EMPTY);
const EditingContext = createContext(false);

/** Gives the platform pages the published, admin-edited content. Without it (store pages) everything is built-in. */
export function SiteProvider({ content, editing = false, children }: { content: SiteContent; editing?: boolean; children: React.ReactNode }) {
  return (
    <SiteContext.Provider value={content}>
      <EditingContext.Provider value={editing}>{children}</EditingContext.Provider>
    </SiteContext.Provider>
  );
}

/** In the theme editor's preview, marks an element so a click on it jumps to its setting. Does nothing on the real site. */
export function useEditable(): (field: string) => { "data-edit"?: string } {
  const editing = useContext(EditingContext);

  return (field) => (editing ? { "data-edit": field } : {});
}

export const useSite = () => useContext(SiteContext);

/** The admin's wording for this field in the visitor's language, else the built-in text (translated). */
export function useSiteText(surface: keyof SiteContent, field: string, fallback: string): string {
  const site = useSite();
  const locale = useLocale();

  return pickText(site[surface], locale, field) ?? tr(fallback);
}

/** Like useSiteText but null when nothing was set (for values that have no built-in text, such as a logo address). */
export function useSiteValue(surface: keyof SiteContent, field: string): string | null {
  const site = useSite();
  const locale = useLocale();

  return imageSrc(pickText(site[surface], locale, field) ?? pickText(site[surface], "en", field));
}

export function useSiteFlag(field: string): boolean {
  return pickFlag(useSite().home, field);
}

export function useSiteList<T extends Record<string, string>>(surface: keyof SiteContent, field: string): T[] {
  return pickList<T>(useSite()[surface], useLocale(), field);
}
