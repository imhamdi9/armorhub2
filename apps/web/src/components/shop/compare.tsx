"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";

import { LIMITS, clearCompare, toggleCompare, useIds } from "@/lib/shop-memory";
import { tr } from "@/lib/i18n";

/** "Add to comparison" on a card or a product page. */
export function CompareToggle({ slug, id, className = "" }: { slug: string; id: number; className?: string }) {
  const ids = useIds(slug, "compare");
  const on = ids.includes(id);
  const [full, setFull] = useState(false);

  return (
    <button type="button" aria-pressed={on} data-testid="compare-toggle"
      onClick={() => setFull(!toggleCompare(slug, id))}
      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold backdrop-blur transition ${on ? "bg-black text-white" : "bg-white/90 text-black hover:bg-white"} ${className}`}>
      <span aria-hidden="true">{on ? "✓" : "+"}</span>
      {full && !on ? tr("Compare is full ({n})", { n: LIMITS.compare }) : tr("Compare")}
    </button>
  );
}

/** Appears once something is picked: how many, a link to the comparison and a way to start over. */
export function CompareBar({ slug }: { slug: string }) {
  const ids = useIds(slug, "compare");
  const onComparison = usePathname()?.endsWith("/compare");
  if (ids.length === 0 || onComparison) return null;
  return (
    <div role="region" aria-label={tr("Comparison")} data-testid="compare-bar"
      className="fixed inset-x-3 bottom-20 z-40 mx-auto flex max-w-md items-center justify-between gap-3 rounded-full bg-black px-5 py-3 text-sm text-white shadow-xl md:bottom-6">
      <span>{tr("{n} to compare", { n: ids.length })}</span>
      <span className="flex items-center gap-3">
        <button type="button" className="text-white/70 underline" onClick={() => clearCompare(slug)}>{tr("Clear")}</button>
        <Link href={`/s/${slug}/compare`} className="rounded-full bg-white px-4 py-1.5 font-semibold text-black">{tr("Compare now")}</Link>
      </span>
    </div>
  );
}
