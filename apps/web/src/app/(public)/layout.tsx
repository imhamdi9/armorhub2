import { CookieConsentBanner } from "@/components/layout/cookie-consent-banner";
import { I18nProvider } from "@/lib/i18n";

/** The public site: drawn on the server so search engines and first paint get real content. */
export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return <I18nProvider gate={false}>
      {children}
      <CookieConsentBanner />
    </I18nProvider>;
}
