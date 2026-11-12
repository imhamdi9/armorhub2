"use client";

import { tr } from "@/lib/i18n";

export type InstallationMode = "none" | "optional" | "required";

const CHOICES: [InstallationMode, string, string][] = [
  ["none", "A finished product, sold on its own", "The customer buys it and uses it. No installation offers are shown."],
  ["optional", "Sold on its own; installation offers if the customer wants them", "The customer can buy it alone. If they like, nearby centres send them installation offers, and the choice is theirs."],
  ["required", "Sold only with an installation offer: the platform finds the installer", "The product is not sold on its own: the customer first gets installation offers from nearby centres, and can buy the product once they choose one."],
];

/** Whether (and how) a product comes with installation. The product is always sold on its own; this only decides what the customer is offered. */
export function InstallationField({ value, onChange }: { value: InstallationMode; onChange: (v: InstallationMode) => void }) {
  return (
    <fieldset className="space-y-2 rounded-lg border p-3">
      <legend className="px-1 text-sm font-medium">{tr("Installation")}</legend>
      {CHOICES.map(([mode, title, help]) => (
        <label key={mode} className="flex cursor-pointer items-start gap-2 rounded-md p-2 text-sm hover:bg-muted">
          <input type="radio" name="installation" className="mt-1" value={mode} checked={value === mode} onChange={() => onChange(mode)} />
          <span><span className="font-medium">{tr(title)}</span><span className="block text-muted-foreground">{tr(help)}</span></span>
        </label>
      ))}
    </fieldset>
  );
}
