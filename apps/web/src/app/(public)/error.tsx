"use client";

import { useEffect } from "react";

import { tr } from "@/lib/i18n";

/** Shown when a public page could not reach the service (never a 404, which would claim the store or product is gone). Tries again by itself. */
export default function PublicError({ reset }: { error: Error; reset: () => void }) {
  useEffect(() => {
    const id = setTimeout(reset, 6000);

    return () => clearTimeout(id);
  }, [reset]);

  return (
    <div className="mx-auto my-16 max-w-xl rounded-2xl border border-dashed border-border p-8 text-center text-slate" role="status">
      <p className="font-semibold text-obsidian">{tr("The service is waking up.")}</p>
      <p className="mt-1 text-sm">{tr("This can take up to a minute the first time. This page will refresh by itself.")}</p>
      <button type="button" onClick={reset} className="btn-brand mt-4 px-5 py-2.5 text-sm">{tr("Try again")}</button>
    </div>
  );
}
