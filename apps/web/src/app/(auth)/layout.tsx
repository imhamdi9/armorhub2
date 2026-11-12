import type { Metadata } from "next";

import { I18nProvider } from "@/lib/i18n";

// Sign-in and sign-up screens are for people, not for search results.
export const metadata: Metadata = { robots: { index: false, follow: true } };

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return <I18nProvider><main>{children}</main></I18nProvider>;
}
