import { formatNumber, tr } from "@/lib/i18n";

/** Five stars filled to the average (a partly filled star shows a partial rating), with how many people rated. Nothing at all while there are no ratings. */
export function Stars({ average, count, showCount = true, className = "" }: { average: number | null | undefined; count?: number; showCount?: boolean; className?: string }) {
  if (!average || !count) return null;
  const pct = Math.max(0, Math.min(100, (average / 5) * 100));

  return (
    <span className={`inline-flex items-center gap-1.5 text-sm ${className}`} data-testid="stars" role="img" aria-label={tr("{n} out of 5 stars", { n: formatNumber(average, { maximumFractionDigits: 1 }) })}>
      <span className="relative inline-block leading-none" aria-hidden="true" dir="ltr">
        <span className="text-[#d5cfc0]">★★★★★</span>
        <span className="absolute inset-y-0 start-0 overflow-hidden whitespace-nowrap text-[#f5a000]" style={{ width: `${pct}%` }}>★★★★★</span>
      </span>
      {showCount && <span className="text-[var(--brand-ink)] underline-offset-2">{formatNumber(count)}</span>}
    </span>
  );
}
