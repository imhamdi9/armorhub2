"use client";

import { useEffect, useState } from "react";

import { CONSENT_EVENT, CookieConsentBanner, type ConsentDetail } from "@/components/layout/cookie-consent-banner";

/** The page listens for the event the way analytics would, and shows what it heard. Clear `cookie-consent` in localStorage to see the banner again. */
export default function CookieConsentPreview() {
  const [heard, setHeard] = useState<ConsentDetail | null>(null);
  useEffect(() => {
    const on = (e: Event) => setHeard((e as CustomEvent<ConsentDetail>).detail);
    window.addEventListener(CONSENT_EVENT, on);

    return () => window.removeEventListener(CONSENT_EVENT, on);
  }, []);

  return (
    <div className="min-h-screen bg-neutral-50 p-10">
      <h1 className="text-2xl font-semibold text-neutral-900">A page with a cookie banner</h1>
      <p className="mt-2 text-sm text-neutral-500">Analytics here would start only after an &ldquo;accepted&rdquo; event.</p>
      <p data-heard className="mt-4 rounded-lg border border-neutral-200 bg-white p-3 font-mono text-xs text-neutral-600">{heard ? JSON.stringify(heard) : "No consent event yet."}</p>
      <CookieConsentBanner />
    </div>
  );
}
