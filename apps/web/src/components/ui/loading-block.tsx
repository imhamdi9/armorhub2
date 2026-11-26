import { Skeleton } from "@/components/ui/skeleton";
import { tr } from "@/lib/i18n";

/** What a screen shows while its data arrives: the shape of the content to come, not a line of text. Screen readers still hear "Loading...". */
export function LoadingBlock({ rows = 3 }: { rows?: number }) {
  return (
    <div role="status" aria-busy="true" aria-label={tr("Loading...")} className="space-y-3" data-testid="loading">
      <span className="sr-only">{tr("Loading...")}</span>
      {Array.from({ length: rows }, (_, i) => (
        <Skeleton key={i} className={`h-16 w-full rounded-xl ${i === rows - 1 ? "w-2/3" : ""}`} />
      ))}
    </div>
  );
}
