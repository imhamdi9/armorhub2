"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useEffect } from "react";

import { MARKET_KEY, REDIRECTED_KEY, marketFromCoords } from "@/lib/market-detect";
import type { PublicStoreCard } from "@/types/public";

/**
 * Sends a visitor to the market of the country they are in: in Saudi Arabia to the Saudi market, in Egypt to the Egyptian one, so they only see
 * that market's products and currency. Location is asked once (the browser shows its own permission prompt); a visitor who declines, or is
 * elsewhere, simply sees the platform page and can pick a market. Once per visit, and `?all=1` always keeps the platform page.
 */
export function MarketRedirect({ stores }: { stores: PublicStoreCard[] }) {
  const router = useRouter();
  const params = useSearchParams();
  const all = params.get("all") === "1";

  useEffect(() => {
    if (all || stores.length === 0) return;
    try {
      if (sessionStorage.getItem(REDIRECTED_KEY)) return;
    } catch {
      return;
    }

    const go = (code: string) => {
      const store = stores.find((s) => s.market === code);
      if (!store) return false;
      try {
        sessionStorage.setItem(REDIRECTED_KEY, "1");
        localStorage.setItem(MARKET_KEY, code);
      } catch {
        // storage is blocked: still send them, just not remembered
      }
      router.replace(`/s/${store.slug}`);
      return true;
    };

    // A market chosen before is respected without asking for location again.
    let saved: string | null = null;
    try { saved = localStorage.getItem(MARKET_KEY); } catch { saved = null; }
    if (saved && go(saved)) return;

    if (!("geolocation" in navigator)) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const code = marketFromCoords(pos.coords.latitude, pos.coords.longitude);
        if (code) go(code);
      },
      () => undefined,
      { enableHighAccuracy: true, timeout: 10_000, maximumAge: 5 * 60_000 },
    );
  }, [all, stores, router]);

  return null;
}
