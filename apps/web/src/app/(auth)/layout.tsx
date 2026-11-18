import type { Metadata } from "next";
import { Suspense } from "react";

import { AuthAside } from "@/components/auth/auth-aside";
import { AuthBrand } from "@/components/auth/auth-brand";
import { CookieConsentBanner } from "@/components/layout/cookie-consent-banner";
import { I18nProvider } from "@/lib/i18n";

// Sign-in and sign-up screens are for people, not for search results.
export const metadata: Metadata = { robots: { index: false, follow: true } };

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <I18nProvider>
      <div className="grid min-h-screen lg:grid-cols-2">
        <AuthAside />
        <div className="relative flex min-h-screen flex-col bg-bone">
          <Suspense fallback={null}><AuthBrand variant="bar" /></Suspense>
          <main className="flex flex-1">{children}</main>
        </div>
      </div>
      <CookieConsentBanner />
    </I18nProvider>
  );
}
