import type { Metadata } from "next";

// Design previews of standalone screens: not part of the product's navigation, so search engines should skip them.
export const metadata: Metadata = { robots: { index: false, follow: false } };

export default function UiPreviewLayout({ children }: { children: React.ReactNode }) {
  return children;
}
