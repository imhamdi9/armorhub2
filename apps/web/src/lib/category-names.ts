/** Category names in both languages, loaded once from the public API (slug -> { en, ar }). */
let names: Record<string, { en: string; ar: string | null }> = {};
let loading: Promise<boolean> | null = null;

/** Loads the names once; resolves true when there are some (so a screen knows to draw again). */
export function loadCategoryNames(): Promise<boolean> {
  loading ??= fetch(`${process.env.NEXT_PUBLIC_API_URL ?? ""}/public/category-names`)
    .then((r) => (r.ok ? r.json() : {}))
    .then((m: typeof names) => { names = m; return Object.keys(m).length > 0; })
    .catch(() => false);
  return loading;
}

export const categoryNamesReady = () => Object.keys(names).length > 0;
export const knownCategory = (slug: string) => names[slug];
