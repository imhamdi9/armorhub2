import type { PublicProductPage, PublicProducts, PublicShowcase, PublicStore, PublicStoreList } from "@/types/public";

/**
 * Server-side reads of the public API (no login). The Next.js server talks to the API directly:
 * API_INTERNAL_URL / API_PROXY_TARGET, or the origin of an absolute NEXT_PUBLIC_API_URL.
 */
function apiBase(): string {
  const direct = process.env.API_INTERNAL_URL || process.env.API_PROXY_TARGET;
  if (direct) return direct.replace(/\/$/, "");

  try {
    return new URL(process.env.NEXT_PUBLIC_API_URL ?? "").origin;
  } catch {
    return "";
  }
}

const TTL_MS = 10_000;
const MAX_ENTRIES = 500;
const cache = new Map<string, { expires: number; value: unknown }>();

/**
 * A short in-memory cache that also remembers "not found". Next.js's own data cache is not used on purpose:
 * when a refresh fails (a store was closed, so the API answers 404) it keeps serving the old successful
 * response, which would leave a closed or suspended store publicly visible. Here a 404 is cached as a 404.
 */
/** How long a visitor waits for the API before the page shows its "waking up" message instead (a sleeping free instance takes ~30-60s). */
const API_PATIENCE_MS = 8000;

/** One request to the public API. `down` is true when the API could not be reached or failed (not when it simply says "not found"). */
async function fetchPublic<T>(path: string): Promise<{ value: T | null; down: boolean }> {
  const base = apiBase();
  if (!base) return { value: null, down: true };

  try {
    const res = await fetch(`${base}/api/public${path}`, { headers: { Accept: "application/json" }, cache: "no-store", signal: AbortSignal.timeout(API_PATIENCE_MS) });
    if (res.status >= 500) return { value: null, down: true };

    return { value: res.ok ? ((await res.json()) as T) : null, down: false };
  } catch {
    return { value: null, down: true };
  }
}

async function get<T>(path: string): Promise<T | null> {
  const hit = cache.get(path);
  if (hit && hit.expires > Date.now()) return hit.value as T | null;

  const { value, down } = await fetchPublic<T>(path);
  // An unreachable API is retried next time rather than remembered, so a store never looks closed just because the server was asleep.
  if (down) return null;

  if (cache.size >= MAX_ENTRIES) cache.clear();
  cache.set(path, { expires: Date.now() + TTL_MS, value });

  return value;
}

/** The open stores, and whether the list is missing because the API could not be reached (so the page can say so instead of "no stores"). */
export async function getStoresChecked() {
  const { value, down } = await fetchPublic<PublicStoreList>("/storefronts");

  return { stores: value?.stores ?? [], down };
}

export const getStores = () => get<PublicStoreList>("/storefronts");
export const getSurface = async (key: string) => (await get<{ values: Record<string, Record<string, unknown>> }>(`/surfaces/${key}`))?.values ?? {};
export const getShowcase = () => get<PublicShowcase>("/showcase");
export const getStore = (slug: string) => get<PublicStore>(`/storefronts/${encodeURIComponent(slug)}`);
export const getStoreProducts = (slug: string, params: { q?: string; category?: string; page?: string; industry?: string }) => {
  const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v) as [string, string][]).toString();
  return get<PublicProducts>(`/storefronts/${encodeURIComponent(slug)}/products${query ? `?${query}` : ""}`);
};
export const getStoreProduct = (slug: string, id: string) =>
  get<PublicProductPage>(`/storefronts/${encodeURIComponent(slug)}/products/${encodeURIComponent(id)}`);

/** Black or white, whichever reads better on this background colour. */
export function textOn(hex: string): "#ffffff" | "#111827" {
  const m = /^#([0-9a-f]{6})$/i.exec(hex);
  if (!m) return "#ffffff";
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(m[1].slice(i, i + 2), 16) / 255);
  const lin = (c: number) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);

  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b) > 0.45 ? "#111827" : "#ffffff";
}
