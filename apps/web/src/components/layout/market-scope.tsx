"use client";

import { useSearchParams, usePathname } from "next/navigation";
import { useEffect, useState } from "react";

import { tr } from "@/lib/i18n";
import { getMarkets } from "@/services/platform.service";

/**
 * Says whose settings a page is changing: one market's, or the platform-wide ones. A market manager is always inside their market;
 * the platform owner is inside a market when they opened the page from it.
 */
export function MarketScopeNotice({ own }: { own?: boolean }) {
  const pathname = usePathname();
  const market = useSearchParams().get("market");
  const [name, setName] = useState<string | null>(null);
  const inMarketPages = pathname.startsWith("/dashboard/market/");

  useEffect(() => {
    if (inMarketPages) { import("@/services/api").then(({ api }) => api.get<{ name: string }>("/market").then((r) => setName(r.data.name)).catch(() => undefined)); return; }
    if (market) getMarkets().then((r) => setName(r.markets.find((m) => String(m.id) === market)?.name ?? null)).catch(() => undefined);
  }, [market, inMarketPages]);

  if (!inMarketPages && !market) return <p role="note" className="mb-4 rounded-lg border bg-muted p-3 text-sm">{tr("These settings apply to every market that has not set its own.")}</p>;

  return (
    <p role="note" className="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900" data-testid="market-scope">
      {tr("You are changing the settings of")} <strong>{name ?? "…"}</strong> {tr("only. Anything you leave unset uses the platform's value.")} {own ? "" : ""}
    </p>
  );
}
