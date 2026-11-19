"use client";

import { useEffect } from "react";

import { startMarketing, track, type TrackData, type TrackName } from "@/lib/marketing";

/** Loads the market's ad pixels once the visitor has accepted marketing cookies, and keeps the campaign they came from. Renders nothing. */
export function MarketingScripts({ market }: { market?: string | null }) {
  useEffect(() => startMarketing(market), [market]);

  return null;
}

/** Reports one event when this appears (a product was viewed, checkout began...). Renders nothing. */
export function TrackEvent({ name, data, eventId }: { name: TrackName; data?: TrackData; eventId?: string }) {
  const key = JSON.stringify([name, data, eventId]);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { track(name, data, eventId); }, [key]);

  return null;
}
