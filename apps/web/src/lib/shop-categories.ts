"use client";

import { useEffect, useState } from "react";

/** A department (top-level category) of a market with the categories that have products, in the language being browsed. */
export interface Department { key: string; name: string; count: number; children: { key: string; name: string; count: number }[] }

const cache = new Map<string, Promise<Department[]>>();
const lang = () => (typeof window !== "undefined" && window.localStorage.getItem("locale") === "ar" ? "ar" : "en");

/** Loads the departments of one market once per language (the menu and the search box on every page share it). */
export function loadDepartments(slug: string): Promise<Department[]> {
  const key = `${slug}|${lang()}`;
  if (!cache.has(key)) {
    cache.set(key, fetch(`${process.env.NEXT_PUBLIC_API_URL ?? ""}/public/storefronts/${encodeURIComponent(slug)}/categories`, { headers: { "Accept-Language": lang() } })
      .then((r) => (r.ok ? r.json() : { tree: [] }))
      .then((d: { tree: Department[] }) => d.tree)
      .catch(() => { cache.delete(key); return [] as Department[]; }));
  }

  return cache.get(key)!;
}

export function useDepartments(slug: string): Department[] {
  const [list, setList] = useState<Department[]>([]);
  useEffect(() => { let live = true; loadDepartments(slug).then((d) => live && setList(d)); return () => { live = false; }; }, [slug]);

  return list;
}
