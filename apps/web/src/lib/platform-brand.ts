/** The platform's own name and logo, as the owners set them in the theme editor. Loaded once from the public API. */
import { imageSrc } from "@/lib/surfaces";

const DEFAULT_NAME = "ArmorHub";
const DEFAULT_NAME_AR = "أرمور هب";

let names = { en: DEFAULT_NAME, ar: DEFAULT_NAME_AR };
let logo: string | null = null;
let loading: Promise<boolean> | null = null;

/** Loads the brand once; resolves true when it differs from the built-in one (so screens know to draw again). */
export function loadPlatformBrand(): Promise<boolean> {
  loading ??= fetch(`${process.env.NEXT_PUBLIC_API_URL ?? ""}/public/surfaces/site.brand`)
    .then((r) => (r.ok ? r.json() : null))
    .then((d: { values?: Record<string, Record<string, unknown>> } | null) => {
      const text = (l: string) => { const v = d?.values?.[l]?.name; return typeof v === "string" && v.trim() && v.trim() !== DEFAULT_NAME ? v.trim() : null; };
      // (the API fills an unset name with the built-in one, so "ArmorHub" means nothing was chosen)
      const en = text("en") ?? DEFAULT_NAME;
      names = { en, ar: text("ar") ?? (text("en") ? en : DEFAULT_NAME_AR) };
      const l = d?.values?.en?.logo_url;
      logo = typeof l === "string" && l.trim() ? imageSrc(l.trim()) : null;
      return names.en !== DEFAULT_NAME || logo !== null;
    })
    .catch(() => false);

  return loading;
}

export const platformName = (locale: "en" | "ar") => names[locale];
export const platformLogo = () => logo;

/** Puts the platform's own name in place of the built-in one inside a sentence. */
export function withPlatformName(text: string, locale: "en" | "ar"): string {
  const to = names[locale];
  if (to === DEFAULT_NAME && locale === "en") return text;
  if (locale === "ar" && to === DEFAULT_NAME_AR) return text;

  return text.replaceAll(DEFAULT_NAME, to).replaceAll(DEFAULT_NAME_AR, to);
}
