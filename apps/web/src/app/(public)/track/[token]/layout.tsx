import type { Metadata } from "next";

// The address is a private link to one person's order: never indexed, never passed on in a referrer.
export const metadata: Metadata = { title: "Your order", robots: { index: false, follow: false }, referrer: "no-referrer" };

export default function TrackLayout({ children }: { children: React.ReactNode }) {
  return children;
}
