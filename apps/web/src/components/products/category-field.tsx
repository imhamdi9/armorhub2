"use client";

import { useEffect, useState } from "react";

import { Input } from "@/components/ui/input";
import { categoryLabel } from "@/lib/category";
import { tr } from "@/lib/i18n";
import { getProductCategories } from "@/services/platform.service";

const NEW = "__new__";

/**
 * Category is picked from the list already in use (so "Skin care" is never typed three different ways) and is never left empty:
 * "New category" opens a text box, and the API files it under one tidy spelling.
 */
export function CategoryField({ value, onChange, id }: { value: string; onChange: (v: string) => void; id?: string }) {
  const [options, setOptions] = useState<string[]>([]);
  const [adding, setAdding] = useState(false);

  useEffect(() => { getProductCategories().then(setOptions).catch(() => setOptions([])); }, []);

  const known = options.includes(value);
  const list = value && !known && !adding ? [value, ...options] : options;
  const showNew = adding || (options.length === 0 && !value);

  return (
    <div className="space-y-2">
      {options.length > 0 && (
        <select
          id={id} aria-label={tr("Category")} required={!showNew}
          value={showNew ? NEW : value}
          onChange={(e) => { if (e.target.value === NEW) { setAdding(true); onChange(""); } else { setAdding(false); onChange(e.target.value); } }}
          className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
        >
          <option value="" disabled>{tr("Choose a category")}</option>
          {list.map((c) => <option key={c} value={c}>{categoryLabel(c)}</option>)}
          <option value={NEW}>{tr("New category…")}</option>
        </select>
      )}
      {showNew && (
        <Input aria-label={tr("New category")} required autoFocus={options.length > 0} placeholder={tr("e.g. Skin care")} value={value} onChange={(e) => onChange(e.target.value)} />
      )}
    </div>
  );
}
