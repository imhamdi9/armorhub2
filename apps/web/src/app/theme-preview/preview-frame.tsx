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

  // Clicking something in the preview tells the editor what was clicked (links never navigate here).
  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      const target = e.target as Element | null;
      e.preventDefault();
      e.stopPropagation();
      const field = target?.closest("[data-edit]")?.getAttribute("data-edit");
      const block = target?.closest("[data-block]")?.getAttribute("data-block");
      window.parent.postMessage({ type: "theme-click", field, block }, window.location.origin);
    };
    document.addEventListener("click", onClick, true);

    return () => document.removeEventListener("click", onClick, true);
  }, []);

  return (
    <>
      <style>{`[data-edit]{cursor:pointer;outline:2px dashed transparent;outline-offset:3px}[data-edit]:hover{outline-color:#2563eb}[data-block]>*:hover{box-shadow:inset 0 0 0 2px rgba(37,99,235,.35)}`}</style>
      <PlatformHome stores={stores} showcase={showcase} site={site} editing />
    </>
  );
}
