"use client";

import { useEffect, useState } from "react";

import { getMarketplaces, getOpenStores } from "@/services/auth.service";

/** What the platform lets people sign up for right now, across its markets: no market sells -> no "sell" option; no market has centres -> no "partner" option. */
export interface MarketOffers { ready: boolean; selling: boolean; installation: boolean; installationStores: string[] }

export function useMarketOffers(): MarketOffers {
  const [offers, setOffers] = useState<MarketOffers>({ ready: false, selling: true, installation: true, installationStores: [] });

  useEffect(() => {
    Promise.all([getMarketplaces().catch(() => null), getOpenStores().catch(() => null)]).then(([markets, stores]) => {
      if (!markets || !stores) return;
      const withCentres = stores.filter((s) => s.installation_enabled !== false);
      setOffers({ ready: true, selling: markets.length > 0, installation: withCentres.length > 0, installationStores: withCentres.map((s) => s.slug) });
    });
  }, []);

  return offers;
}
