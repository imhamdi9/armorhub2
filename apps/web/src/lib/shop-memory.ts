"use client";

import { useEffect, useState } from "react";

/**
 * What a shopper looked at and what they want to compare. It lives only in their own browser (nothing about it is sent to us),
 * per store, newest first. Components listen for changes so a toggle on one card updates the bar everywhere.
 */
export type MemoryKind = "viewed" | "compare";

export const LIMITS: Record<MemoryKind, number> = { viewed: 12, compare: 4 };
const EVENT = "shop-memory";
const storageKey = (slug: string, kind: MemoryKind) => `shop:${slug}:${kind}`;

export function readIds(slug: string, kind: MemoryKind): number[] {
  try {
    const raw = JSON.parse(localStorage.getItem(storageKey(slug, kind)) ?? "[]");
    return Array.isArray(raw) ? raw.filter((n): n is number => Number.isInteger(n)).slice(0, LIMITS[kind]) : [];
  } catch {
    return [];
  }
}

function writeIds(slug: string, kind: MemoryKind, ids: number[]) {
  try {
    localStorage.setItem(storageKey(slug, kind), JSON.stringify(ids.slice(0, LIMITS[kind])));
  } catch {
    /* storage unavailable: the shopper simply loses the shortcut */
  }
  window.dispatchEvent(new Event(EVENT));
}

/** Put a product first in the recently viewed list. */
export function rememberView(slug: string, id: number) {
  writeIds(slug, "viewed", [id, ...readIds(slug, "viewed").filter((n) => n !== id)]);
}

/** Add to or remove from the comparison. Returns false when the comparison is full and the product was not added. */
export function toggleCompare(slug: string, id: number): boolean {
  const ids = readIds(slug, "compare");
  if (ids.includes(id)) {
    writeIds(slug, "compare", ids.filter((n) => n !== id));
    return true;
  }
  if (ids.length >= LIMITS.compare) return false;
  writeIds(slug, "compare", [...ids, id]);
  return true;
}

export const clearCompare = (slug: string) => writeIds(slug, "compare", []);

/** The ids now, kept up to date when they change here or in another tab. */
export function useIds(slug: string, kind: MemoryKind): number[] {
  const [ids, setIds] = useState<number[]>([]);
  useEffect(() => {
    const sync = () => setIds(readIds(slug, kind));
    sync();
    window.addEventListener(EVENT, sync);
    window.addEventListener("storage", sync);
    return () => {
      window.removeEventListener(EVENT, sync);
      window.removeEventListener("storage", sync);
    };
  }, [slug, kind]);

  return ids;
}
