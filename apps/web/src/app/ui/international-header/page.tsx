"use client";

import { InternationalHeader } from "@/components/layout/international-header";

/** A preview of the international header. Choices are stored in this browser (localStorage); nothing else on the page reacts to them. */
export default function InternationalHeaderPreview() {
  return (
    <div className="min-h-screen bg-neutral-50">
      <InternationalHeader />
      <main className="mx-auto max-w-6xl px-4 py-16 text-neutral-600 sm:px-6">
        <h1 className="text-2xl font-semibold tracking-tight text-neutral-900">Page content</h1>
        <p className="mt-2 max-w-prose text-sm">Open the language or currency pill above. Press Escape or click here to close it.</p>
      </main>
    </div>
  );
}
