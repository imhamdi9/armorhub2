"use client";

import { Suspense } from "react";

import { AuthBrand } from "@/components/auth/auth-brand";
import { tr } from "@/lib/i18n";

const STEPS: [string, string][] = [
  ["M4 6h16M4 12h10M4 18h6", "Describe what you need once"],
  ["M12 3 5 6v6c0 4.5 3 7.5 7 9 4-1.5 7-4.5 7-9V6l-7-3Z", "Compare quotes from checked partners"],
  ["M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V8ZM3 10h18", "Pay in the app, get your warranty"],
];

/** The dark side of the sign-in and sign-up screens (large screens only): the market's logo, one promise, three steps. */
export function AuthAside() {
  return (
    <aside className="bg-dark bg-grid-dark relative hidden flex-col justify-between overflow-hidden p-12 lg:flex xl:p-16">
      <Suspense fallback={null}><AuthBrand variant="panel" /></Suspense>
      <h2 className="display mt-16 max-w-md text-5xl !text-white">
        {tr("Quotes from verified installers,")} <span className="text-on-dark-accent">{tr("one sign-in away.")}</span>
      </h2>
      <ul className="mt-16 grid max-w-sm gap-4">
        {STEPS.map(([d, text]) => (
          <li key={text} className="flex items-center gap-3.5 text-[15px] text-white/85">
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-[11px] bg-white/10 text-lime">
              <svg viewBox="0 0 24 24" className="h-[18px] w-[18px]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={d} /></svg>
            </span>
            {tr(text)}
          </li>
        ))}
      </ul>
    </aside>
  );
}
