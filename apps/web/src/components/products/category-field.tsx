"use client";

import { useEffect, useState } from "react";

import { categoryLabel } from "@/lib/category";
import { tr, useLocale } from "@/lib/i18n";
import { getProductCategories, type CategoryGroup } from "@/services/platform.service";

type Option = { key: string; label: string };

/** A category is picked from the platform's own tree (any depth, shown as a path); nobody types a new one. Admins extend the tree. */
export function CategoryField({ value, onChange, id }: { value: string; onChange: (v: string) => void; id?: string }) {
  const [tree, setTree] = useState<CategoryGroup[]>([]);
  const [loaded, setLoaded] = useState(false);
  const locale = useLocale();

  useEffect(() => { getProductCategories().then(setTree).catch(() => setTree([])).finally(() => setLoaded(true)); }, []);

  if (!loaded) return <div className="h-10 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  const name = (c: CategoryGroup) => (locale === "ar" && c.name_ar ? c.name_ar : c.name);
  const options: Option[] = [];
  const walk = (nodes: CategoryGroup[], trail: string[]) => {
    for (const n of nodes) {
      const path = [...trail, name(n)];
      options.push({ key: n.key, label: path.join(" › ") });
      walk(n.children ?? [], path);
    }
  };
  walk(tree, []);

  return (
    <select
      id={id} aria-label={tr("Category")} required value={value}
      onChange={(e) => onChange(e.target.value)}
      className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
    >
      <option value="" disabled>{tr("Choose a category")}</option>
      {value && !options.some((o) => o.key === value) && <option value={value}>{categoryLabel(value)}</option>}
      {options.map((o) => <option key={o.key} value={o.key}>{o.label}</option>)}
    </select>
  );
}
