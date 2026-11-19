"use client";

import { DEFAULT_TEMPLATE, PAGE_TEMPLATES, TEMPLATE_INFO, isTemplate, type PageTemplate } from "@/lib/product-templates";
import { tr } from "@/lib/i18n";

/** Reads whatever the API sent and falls back to the default, so an unknown value never breaks the form. */
export const asTemplate = (value: unknown): PageTemplate => (isTemplate(value) ? value : DEFAULT_TEMPLATE);

/**
 * Which layout the customer's product page uses. Only platform administrators see this.
 * Each choice shows a small picture of the page.
 */
export function PageTemplateField({ value, onChange }: { value: PageTemplate; onChange: (v: PageTemplate) => void }) {
  return (
    <fieldset className="space-y-3 rounded-lg border p-3" data-testid="page-template-field">
      <legend className="px-1 text-sm font-medium">{tr("Product page layout")}</legend>
      <p className="text-sm text-muted-foreground">{tr("Choose how this product is shown to customers. You can change it at any time.")}</p>
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {PAGE_TEMPLATES.map((key) => {
          const info = TEMPLATE_INFO[key];
          const on = value === key;

          return (
            <label key={key} className={`group relative flex cursor-pointer flex-col overflow-hidden rounded-xl border-2 bg-white transition ${on ? "border-[#E0B400] shadow-card" : "border-transparent ring-1 ring-border hover:ring-[#E0B400]/50"}`}>
              <input type="radio" name="page_template" className="sr-only" value={key} checked={on} onChange={() => onChange(key)} />
              {/* eslint-disable-next-line @next/next/no-img-element -- a small static preview from /public */}
              <img src={`/templates/${key}.png`} alt="" loading="lazy" className="aspect-[1440/1000] w-full border-b object-cover object-top" />
              <span className="flex flex-1 flex-col gap-1 p-3 text-sm">
                <span className="flex items-center justify-between gap-2">
                  <span className="font-semibold">{tr(info.name)}</span>
                  {on && <span className="rounded-full bg-[#FFD21F] px-2 py-0.5 text-xs font-semibold text-[#1a1a1a]">{tr("Selected")}</span>}
                </span>
                <span className="text-muted-foreground">{tr(info.help)}</span>
                <span className="mt-auto pt-1 text-xs font-medium text-[#8a6a00]">{tr(info.offers)}</span>
              </span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
