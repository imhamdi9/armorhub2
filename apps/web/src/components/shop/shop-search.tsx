"use client";

import { useEffect, useId, useRef, useState } from "react";

import { api } from "@/services/api";
import { tr } from "@/lib/i18n";

type Suggestion = { text: string; kind: "product" | "brand" };

/**
 * A pill search. It works without JavaScript (a plain GET to the catalogue); with it, names and brands are suggested as you type,
 * and a misspelt word still finds the closest one. The store's accent colours the submit button.
 */
export function ShopSearch({ action, q = "", category = "", slug, compact = false, departments = [] }: { action: string; q?: string; category?: string; slug?: string; compact?: boolean; departments?: { key: string; name: string }[] }) {
  const [dept, setDept] = useState(category);
  const [value, setValue] = useState(q);
  const [found, setItems] = useState<Suggestion[]>([]);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(-1);
  const form = useRef<HTMLFormElement>(null);
  const listId = useId();

  const typed = value.trim();
  const searching = !!slug && typed.length >= 2 && typed !== q;
  // Suggestions are only shown while someone is typing a new search.
  const items = searching ? found : [];
  useEffect(() => {
    if (!searching) return;
    const timer = setTimeout(() => {
      api.get<{ suggestions: Suggestion[] }>(`/public/storefronts/${encodeURIComponent(slug)}/suggest`, { params: { q: typed } })
        .then((r) => { setItems(r.data.suggestions); setActive(-1); })
        .catch(() => setItems([]));
    }, 180);
    return () => clearTimeout(timer);
  }, [typed, slug, searching]);

  function pick(text: string) {
    setValue(text);
    setOpen(false);
    setItems([]);
    // Wait for the field to hold the chosen text before sending the form.
    setTimeout(() => form.current?.requestSubmit(), 0);
  }

  function onKey(e: React.KeyboardEvent) {
    if (!open || items.length === 0) return;
    if (e.key === "ArrowDown") { e.preventDefault(); setActive((a) => (a + 1) % items.length); }
    else if (e.key === "ArrowUp") { e.preventDefault(); setActive((a) => (a <= 0 ? items.length - 1 : a - 1)); }
    else if (e.key === "Enter" && active >= 0) { e.preventDefault(); pick(items[active].text); }
    else if (e.key === "Escape") setOpen(false);
  }

  return (
    <form ref={form} role="search" action={action} method="get" className={compact ? "relative flex h-11 w-full items-stretch rounded-full border-2 border-obsidian bg-white" : "relative mx-auto flex max-w-xl items-center rounded-full border border-black/10 bg-white py-1 ps-5 pe-1"}>
      {compact && (
        <select name="category" value={dept} onChange={(e) => setDept(e.target.value)} aria-label={tr("Search in")} data-testid="search-department"
          className="max-w-[9rem] shrink-0 cursor-pointer rounded-s-full border-0 border-e border-border bg-fog ps-4 pe-2 text-sm font-semibold text-obsidian outline-none">
          <option value="">{tr("All")}</option>
          {departments.map((d) => <option key={d.key} value={d.key}>{d.name}</option>)}
        </select>
      )}
      <input name="q" value={value} onChange={(e) => { setValue(e.target.value); setOpen(true); }} onKeyDown={onKey} onFocus={() => setOpen(true)} onBlur={() => setTimeout(() => setOpen(false), 120)}
        type="search" autoComplete="off" aria-label={tr("Search products")} placeholder={tr("What are you looking for today?")}
        role={slug ? "combobox" : undefined} aria-expanded={slug ? open && items.length > 0 : undefined} aria-controls={slug ? listId : undefined} aria-autocomplete={slug ? "list" : undefined}
        className={compact ? "min-w-0 flex-1 bg-transparent px-4 text-sm outline-none placeholder:text-[#5f5c5b]" : "h-11 min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-[#5f5c5b]"} />
      {!compact && category && <input type="hidden" name="category" value={category} />}
      <button type="submit" aria-label={tr("Search")} className={compact ? "btn-brand grid w-12 shrink-0 place-items-center rounded-e-full" : "btn-brand grid size-12 shrink-0 place-items-center rounded-full"} style={{ boxShadow: "0 4px 24px color-mix(in srgb, var(--brand) 34%, transparent)" }}>
        <svg viewBox="0 0 24 24" className="size-5 rtl:-scale-x-100" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </button>

      {open && items.length > 0 && (
        <ul id={listId} role="listbox" data-testid="suggestions" className="absolute inset-x-0 top-full z-30 mt-2 overflow-hidden rounded-2xl border border-black/10 bg-white py-1 text-start shadow-lg">
          {items.map((s, i) => (
            <li key={`${s.kind}-${s.text}`} role="option" aria-selected={i === active}>
              <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => pick(s.text)} className={`flex w-full items-center justify-between gap-3 px-5 py-2.5 text-start text-sm ${i === active ? "bg-brand-wash" : "hover:bg-fog"}`}>
                <span className="truncate">{s.text}</span>
                <span className="shrink-0 text-xs text-slate">{s.kind === "brand" ? tr("Brand") : tr("Product")}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </form>
  );
}
