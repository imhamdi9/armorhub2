"use client";

import { useEffect, useState } from "react";

import { tr } from "@/lib/i18n";
import { getProductReadiness } from "@/services/platform.service";
import type { Product, ProductReadiness } from "@/types/platform";

/** What a listing still needs before shoppers trust it: the checklist, scored. Refreshes whenever the product is saved. */
export function ReadinessCard({ product }: { product: Product }) {
  const [r, setR] = useState<ProductReadiness | null>(null);
  useEffect(() => {
    getProductReadiness(product.id).then(setR).catch(() => setR(null));
  }, [product]);
  if (!r) return null;

  return (
    <section className="mb-6 rounded-lg border p-4" aria-label={tr("Listing quality")} data-testid="readiness">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold">{tr("Listing quality")}</h2>
        <span className={`rounded-full px-3 py-1 text-sm font-semibold ${r.ready ? "bg-green-100 text-green-800" : "bg-amber-100 text-amber-900"}`}>{r.score}%</span>
      </div>
      <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-label={tr("Listing quality")} aria-valuenow={r.score} aria-valuemin={0} aria-valuemax={100}>
        <div className={`h-full ${r.ready ? "bg-green-600" : "bg-primary"}`} style={{ width: `${r.score}%` }} />
      </div>
      <p className="mt-2 text-sm text-muted-foreground">
        {r.ready ? tr("This listing is complete.") : tr("{n} of {total} done. Finish the rest to win more orders.", { n: r.done, total: r.total })}
      </p>
      <ul className="mt-3 space-y-2 text-sm">
        {r.items.map((i) => (
          <li key={i.key} className="flex items-start gap-2">
            <span aria-hidden="true" className={`mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-xs ${i.ok ? "bg-green-600 text-white" : "border border-amber-500 text-amber-700"}`}>{i.ok ? "✓" : "!"}</span>
            <span>
              <span className={i.ok ? "text-muted-foreground line-through" : "font-medium"}>{tr(i.label)}</span>
              {!i.ok && <span className="block text-xs text-muted-foreground">{tr(i.hint)}</span>}
            </span>
          </li>
        ))}
      </ul>
    </section>
  );
}
