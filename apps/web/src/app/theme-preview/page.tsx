import { I18nProvider } from "@/lib/i18n";
import { getShowcase, getStores } from "@/lib/public-api";
import { PreviewFrame } from "./preview-frame";

export const dynamic = "force-dynamic";
export const metadata = { robots: { index: false, follow: false } };

/** The home page as the theme editor's live preview: it shows whatever draft the editor sends it, never anything stored. */
export default async function ThemePreviewPage() {
  const [list, showcase] = await Promise.all([getStores(), getShowcase()]);

  return (
    <I18nProvider gate={false}>
      <PreviewFrame stores={list?.stores ?? []} showcase={showcase ?? { industries: [] }} />
    </I18nProvider>
  );
}
