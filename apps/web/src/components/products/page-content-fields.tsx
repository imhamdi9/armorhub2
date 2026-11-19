"use client";

import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { tr } from "@/lib/i18n";
import { TEMPLATE_FIELDS, type ContentField, type PageContent } from "@/lib/product-content";
import { TEMPLATE_INFO, type PageTemplate } from "@/lib/product-templates";
import { getProducts } from "@/services/platform.service";

const MAX = { steps: 4, faqs: 8, specs: 10, tiers: 3, addons: 4 };

/** A list of small rows the person can add to and remove from. */
function Rows<T extends object>({ label, help, rows, max, blank, onChange, render }: { label: string; help?: string; rows: T[]; max: number; blank: T; onChange: (rows: T[]) => void; render: (row: T, set: (patch: Partial<T>) => void) => React.ReactNode }) {
  return (
    <div className="space-y-2">
      <p className="text-sm font-medium">{label}</p>
      {help && <p className="text-xs text-muted-foreground">{help}</p>}
      {rows.map((row, i) => (
        <div key={i} className="flex items-start gap-2">
          <div className="grid flex-1 gap-2 sm:grid-cols-2">{render(row, (patch) => onChange(rows.map((r, j) => (j === i ? { ...r, ...patch } : r))))}</div>
          <button type="button" aria-label={tr("Remove")} className="mt-1 rounded-full border px-2 text-sm" onClick={() => onChange(rows.filter((_, j) => j !== i))}>×</button>
        </div>
      ))}
      <Button type="button" variant="outline" size="sm" disabled={rows.length >= max} onClick={() => onChange([...rows, blank])}>{tr("Add a row")}</Button>
    </div>
  );
}

/** Pick up to four other products to offer next to this one. */
function AddonPicker({ ids, selfId, onChange }: { ids: number[]; selfId?: number; onChange: (ids: number[]) => void }) {
  const [products, setProducts] = useState<{ id: number; name: string }[]>([]);
  useEffect(() => {
    getProducts({}).then((l) => setProducts(l.filter((p) => p.id !== selfId).map((p) => ({ id: p.id, name: p.name })))).catch(() => setProducts([]));
  }, [selfId]);

  return (
    <fieldset className="space-y-2" data-testid="field-addon_ids">
      <legend className="text-sm font-medium">{tr("Products to offer with this one")}</legend>
      <p className="text-xs text-muted-foreground">{tr("Up to {n}. If you pick none, the page suggests products from the same store.", { n: MAX.addons })}</p>
      <div className="grid max-h-48 gap-1 overflow-auto rounded-lg border p-2 sm:grid-cols-2">
        {products.length === 0 && <p className="text-sm text-muted-foreground">{tr("No other products yet.")}</p>}
        {products.map((p) => {
          const on = ids.includes(p.id);
          return (
            <label key={p.id} className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={on} disabled={!on && ids.length >= MAX.addons} onChange={() => onChange(on ? ids.filter((x) => x !== p.id) : [...ids, p.id])} />
              <span className="truncate">{p.name}</span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}

/**
 * The extra information the chosen page layout needs. Picking a layout opens exactly these fields, so a page is not left half-empty:
 * each field says what it is for, and every one is optional (the layout falls back to sensible defaults).
 */
export function PageContentFields({ template, value, onChange, selfId }: { template: PageTemplate; value: PageContent; onChange: (v: PageContent) => void; selfId?: number }) {
  const fields = TEMPLATE_FIELDS[template];
  const set = (patch: PageContent) => onChange({ ...value, ...patch });
  const has = (f: ContentField) => fields.includes(f);

  return (
    <fieldset className="space-y-4 rounded-lg border border-[#E0B400]/40 bg-[#FFD21F]/[0.08] p-3" data-testid="page-content-fields">
      <legend className="px-1 text-sm font-medium">{tr("Content for the “{name}” layout", { name: tr(TEMPLATE_INFO[template].name) })}</legend>
      <p className="text-sm text-muted-foreground">{tr("These fields appear on the product page. Leave any of them empty and the page uses its defaults.")}</p>

      {has("headline") && (
        <div data-testid="field-headline">
          <label className="text-sm font-medium" htmlFor="pc-headline">{tr("Headline under the name")}</label>
          <Input id="pc-headline" maxLength={160} value={value.headline ?? ""} onChange={(e) => set({ headline: e.target.value })} />
        </div>
      )}

      {has("tiers") && (
        <div data-testid="field-tiers">
          <Rows label={tr("Names for the cards (lowest to highest)")} help={tr("The card prices come from the prices of this product; here you name each card and describe it.")} rows={value.tiers ?? []} max={MAX.tiers} blank={{ name: "", text: "" }} onChange={(tiers) => set({ tiers })}
            render={(r, s) => (<><Input aria-label={tr("Name")} placeholder={tr("Name")} maxLength={40} value={r.name} onChange={(e) => s({ name: e.target.value })} /><Input aria-label={tr("Short description")} placeholder={tr("Short description")} maxLength={160} value={r.text ?? ""} onChange={(e) => s({ text: e.target.value })} /></>)} />
        </div>
      )}

      {has("steps") && (
        <div data-testid="field-steps">
          <Rows label={tr("Steps the customer follows")} help={tr("Up to {n}, in order.", { n: MAX.steps })} rows={value.steps ?? []} max={MAX.steps} blank={{ title: "" }} onChange={(steps) => set({ steps })}
            render={(r, s) => <Input aria-label={tr("Step")} placeholder={tr("Step")} maxLength={80} className="sm:col-span-2" value={r.title} onChange={(e) => s({ title: e.target.value })} />} />
        </div>
      )}

      {has("specs") && (
        <div data-testid="field-specs">
          <Rows label={tr("Specifications")} help={tr("For example: Material, Size, Colour.")} rows={value.specs ?? []} max={MAX.specs} blank={{ label: "", value: "" }} onChange={(specs) => set({ specs })}
            render={(r, s) => (<><Input aria-label={tr("Label")} placeholder={tr("Label")} maxLength={60} value={r.label} onChange={(e) => s({ label: e.target.value })} /><Input aria-label={tr("Value")} placeholder={tr("Value")} maxLength={120} value={r.value} onChange={(e) => s({ value: e.target.value })} /></>)} />
        </div>
      )}

      {has("addon_ids") && <AddonPicker ids={value.addon_ids ?? []} selfId={selfId} onChange={(addon_ids) => set({ addon_ids })} />}

      {has("free_delivery_from") && (
        <div data-testid="field-free_delivery_from">
          <label className="text-sm font-medium" htmlFor="pc-free">{tr("Free delivery on orders from")}</label>
          <Input id="pc-free" type="number" min="0" step="0.01" max="9999999999" value={value.free_delivery_from ?? ""} onChange={(e) => set({ free_delivery_from: e.target.value })} />
          <p className="text-xs text-muted-foreground">{tr("Shown as a line on the page. Leave empty if you do not offer it.")}</p>
        </div>
      )}

      {has("warranty_terms") && (
        <div data-testid="field-warranty_terms">
          <label className="text-sm font-medium" htmlFor="pc-warranty">{tr("What the warranty covers")}</label>
          <textarea id="pc-warranty" rows={3} maxLength={600} className="w-full rounded-lg border px-3 py-2" value={value.warranty_terms ?? ""} onChange={(e) => set({ warranty_terms: e.target.value })} />
        </div>
      )}

      {has("lead_time") && (
        <div data-testid="field-lead_time">
          <label className="text-sm font-medium" htmlFor="pc-lead">{tr("How long it takes")}</label>
          <Input id="pc-lead" maxLength={80} placeholder={tr("For example: ready in 2 working days")} value={value.lead_time ?? ""} onChange={(e) => set({ lead_time: e.target.value })} />
        </div>
      )}

      {has("quote_note") && (
        <div data-testid="field-quote_note">
          <label className="text-sm font-medium" htmlFor="pc-note">{tr("A note next to the estimate")}</label>
          <textarea id="pc-note" rows={2} maxLength={300} className="w-full rounded-lg border px-3 py-2" value={value.quote_note ?? ""} onChange={(e) => set({ quote_note: e.target.value })} />
        </div>
      )}

      {has("faqs") && (
        <div data-testid="field-faqs">
          <Rows label={tr("Questions customers ask")} help={tr("Up to {n}.", { n: MAX.faqs })} rows={value.faqs ?? []} max={MAX.faqs} blank={{ q: "", a: "" }} onChange={(faqs) => set({ faqs })}
            render={(r, s) => (<><Input aria-label={tr("Question")} placeholder={tr("Question")} maxLength={160} value={r.q} onChange={(e) => s({ q: e.target.value })} /><textarea aria-label={tr("Answer")} placeholder={tr("Answer")} rows={2} maxLength={600} className="rounded-lg border px-3 py-2" value={r.a} onChange={(e) => s({ a: e.target.value })} /></>)} />
        </div>
      )}
    </fieldset>
  );
}
