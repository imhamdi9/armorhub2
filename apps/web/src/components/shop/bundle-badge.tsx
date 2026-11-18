import { tr } from "@/lib/i18n";

/** The mark every bundle carries, wherever it appears: an icon and "Bundle" (a set of products the seller put together at one price). */
export function BundleBadge({ className = "", seller }: { className?: string; seller?: string | null }) {
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full bg-black px-2.5 py-1 text-xs font-semibold text-white ${className}`} data-testid="bundle-badge" title={seller ? tr("A bundle made by {seller}", { seller }) : tr("A bundle made by the seller")}>
      <svg aria-hidden="true" viewBox="0 0 24 24" className="size-3.5 shrink-0" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z" /><path d="m3 8 9 5 9-5M12 13v8" /></svg>
      {tr("Bundle")}
    </span>
  );
}
