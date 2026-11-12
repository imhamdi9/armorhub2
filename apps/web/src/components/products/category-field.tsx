"use client";

import { useEffect, useState } from "react";

import { categoryLabel } from "@/lib/category";
import { tr, useLocale } from "@/lib/i18n";
import { getProductCategories, type CategoryGroup } from "@/services/platform.service";

/** A category is picked from the platform's own list (groups with their categories); nobody types a new one. Admins extend the list. */
export function CategoryField({ value, onChange, id }: { value: string; onChange: (v: string) => void; id?: string }) {
  const [groups, setGroups] = useState<CategoryGroup[]>([]);
  const [loaded, setLoaded] = useState(false);
  const locale = useLocale();

  useEffect(() => { getProductCategories().then(setGroups).catch(() => setGroups([])).finally(() => setLoaded(true)); }, []);

  if (!loaded) return <div className="h-10 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  const label = (c: { name: string; name_ar?: string | null }) => (locale === "ar" && c.name_ar ? c.name_ar : c.name);
  const known = groups.some((g) => g.children.some((c) => c.key === value));

  return (
    <select
      id={id} aria-label={tr("Category")} required value={value}
      onChange={(e) => onChange(e.target.value)}
      className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
    >
      <option value="" disabled>{tr("Choose a category")}</option>
      {value && !known && <option value={value}>{categoryLabel(value)}</option>}
      {groups.map((g) => (
        <optgroup key={g.key} label={label(g)}>
          {g.children.map((c) => <option key={c.key} value={c.key}>{label(c)}</option>)}
        </optgroup>
      ))}
    </select>
  );
}
