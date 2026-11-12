"use client";

import { useEffect, useState } from "react";

import { PlatformHome } from "@/components/public/platform-home";
import type { SiteContent } from "@/lib/surfaces";
import type { PublicShowcase, PublicStoreCard } from "@/types/public";

/** Receives the editor's unsaved draft from its parent window (same site only) and renders the real home page with it. */
export function PreviewFrame({ stores, showcase }: { stores: PublicStoreCard[]; showcase: PublicShowcase }) {
  const [site, setSite] = useState<SiteContent>({ home: {}, brand: {}, nav: {} });

  useEffect(() => {
    const onMessage = (e: MessageEvent) => {
      if (e.origin !== window.location.origin || e.source !== window.parent) return;
      const data = e.data as { type?: string; site?: SiteContent } | null;
      if (data?.type === "theme-draft" && data.site) setSite(data.site);
    };
    window.addEventListener("message", onMessage);
    window.parent.postMessage({ type: "theme-preview-ready" }, window.location.origin);

    return () => window.removeEventListener("message", onMessage);
  }, []);

  return <PlatformHome stores={stores} showcase={showcase} site={site} />;
}
