import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

import { api } from "@/api";
import { detectCountry, locationAllowed } from "@/geo";
import { isArabic } from "@/i18n";
import { storeGet, storeSet } from "@/storage";

export type Market = { code: string; name: { en: string; ar: string }; currency: string };

const KEY = "armorhub.market";

type Saved = { code: string; source: "location" | "manual" };

type Ctx = {
  /** All the markets the platform runs. */
  markets: Market[];
  /** The visitor's market: found from their location, or chosen. Null until one is known. */
  market: Market | null;
  /** Looking for the visitor's location right now. */
  locating: boolean;
  /** Why the location could not be used, when it could not. */
  problem: "denied" | "unavailable" | "unsupported" | null;
  ready: boolean;
  choose: (code: string) => Promise<void>;
  locate: () => Promise<void>;
};

const MarketContext = createContext<Ctx | null>(null);

export const marketName = (m: Market) => (isArabic() ? m.name.ar : m.name.en);

/**
 * Which market (country) the app shows. The visitor's location decides: in Saudi Arabia they land in the Saudi market, in Egypt in the Egyptian one,
 * and only that market's stores, products and currency are shown. If the location cannot be used they choose a market, and they can change it any time.
 */
export function MarketProvider({ children }: { children: React.ReactNode }) {
  const [markets, setMarkets] = useState<Market[]>([]);
  const [market, setMarket] = useState<Market | null>(null);
  const [locating, setLocating] = useState(false);
  const [problem, setProblem] = useState<Ctx["problem"]>(null);
  const [ready, setReady] = useState(false);

  const apply = useCallback(async (list: Market[], code: string, source: Saved["source"]) => {
    const found = list.find((m) => m.code === code);
    if (!found) return false;
    setMarket(found);
    await storeSet(KEY, JSON.stringify({ code, source } satisfies Saved));
    return true;
  }, []);

  const locate = useCallback(async () => {
    setLocating(true);
    setProblem(null);
    try {
      const list = markets.length > 0 ? markets : (await api<{ markets: Market[] }>("/public/markets")).markets;
      const found = await detectCountry();
      if (!found.ok) setProblem(found.reason);
      else if (!(await apply(list, found.country, "location"))) setProblem("unsupported");
    } catch {
      setProblem("unavailable");
    } finally {
      setLocating(false);
    }
  }, [markets, apply]);

  useEffect(() => {
    (async () => {
      let list: Market[] = [];
      try {
        list = (await api<{ markets: Market[] }>("/public/markets")).markets;
        setMarkets(list);
      } catch {
        // offline: the saved market (if any) still opens below
      }

      let saved: Saved | null = null;
      try { saved = JSON.parse((await storeGet(KEY)) ?? "null"); } catch { saved = null; }

      if (saved) {
        const found = list.find((m) => m.code === saved.code);
        if (found) setMarket(found);
        setReady(true);
        // Someone who travelled: if location is already allowed, quietly follow them to the market they are now in.
        if (saved.source === "location" && list.length > 0 && (await locationAllowed())) {
          const now = await detectCountry();
          if (now.ok && now.country !== saved.code) await apply(list, now.country, "location");
        }
        return;
      }

      setReady(true);
      if (list.length > 0) await locate();
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps -- runs once, at launch
  }, []);

  const value = useMemo<Ctx>(() => ({ markets, market, locating, problem, ready, choose: async (code) => { await apply(markets, code, "manual"); setProblem(null); }, locate }), [markets, market, locating, problem, ready, apply, locate]);
  return <MarketContext.Provider value={value}>{children}</MarketContext.Provider>;
}

export function useMarket(): Ctx {
  const c = useContext(MarketContext);
  if (!c) throw new Error("useMarket outside MarketProvider");
  return c;
}
