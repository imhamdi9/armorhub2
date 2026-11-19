import type { Metadata } from "next";
import { notFound } from "next/navigation";

// Design previews of standalone screens: not part of the product's navigation, so search engines should skip them.
export const metadata: Metadata = { robots: { index: false, follow: false } };

/** The previews are for designers: they exist in development, and in production only when SHOW_UI_PREVIEWS=1 (they must not break the build or be public by accident). */
export default function UiPreviewLayout({ children }: { children: React.ReactNode }) {
  if (process.env.NODE_ENV === "production" && process.env.SHOW_UI_PREVIEWS !== "1") notFound();

  return children;
}
