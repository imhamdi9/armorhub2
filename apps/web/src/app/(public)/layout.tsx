import { I18nProvider } from "@/lib/i18n";

/** The public site: drawn on the server so search engines and first paint get real content. */
export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return <I18nProvider gate={false}>{children}</I18nProvider>;
}
