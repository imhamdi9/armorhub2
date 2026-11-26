import type { Metadata } from "next";

// A private way back into one shopper's orders: never indexed, never passed on in a referrer.
export const metadata: Metadata = { title: "Your order", robots: { index: false, follow: false }, referrer: "no-referrer" };

export default function GuestAccessLayout({ children }: { children: React.ReactNode }) {
  return children;
}
