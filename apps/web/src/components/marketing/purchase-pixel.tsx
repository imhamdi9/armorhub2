"use client";

import { useEffect } from "react";

import { MarketingScripts } from "@/components/marketing/marketing-scripts";
import { track } from "@/lib/marketing";

const KEY = "tracked-orders";

/**
 * Tells the ad pixels about a paid purchase from a signed-in customer's own screens (the dashboard has no pixels of its own).
 * Each order is reported once per browser, with the same event id the server uses, so a platform never counts it twice.
 */
export function PurchasePixel({ orders, currency, market }: { orders: { id: number; value: number }[]; currency: string; market?: string | null }) {
  const ids = orders.map((o) => o.id).join(",");
  useEffect(() => {
    let seen: number[] = [];
    try { seen = JSON.parse(window.localStorage.getItem(KEY) ?? "[]") as number[]; } catch { /* storage unavailable */ }
    const fresh = orders.filter((o) => !seen.includes(o.id));
    if (fresh.length === 0) return;
    for (const o of fresh) track("purchase", { value: o.value, currency, id: o.id }, `order-${o.id}`);
    try { window.localStorage.setItem(KEY, JSON.stringify([...seen, ...fresh.map((o) => o.id)].slice(-100))); } catch { /* storage unavailable */ }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ids, currency]);

  return <MarketingScripts market={market} />;
}
