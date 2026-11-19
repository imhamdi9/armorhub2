import type { ReactNode } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 2, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const EVENT_GLYPHS = {
  comment: <svg {...svg} className="size-4"><path d="M5 5h14a1.500 1.500 0 0 1 1.500 1.500v8A1.500 1.500 0 0 1 19 16h-7l-4.500 3.500V16H5a1.500 1.500 0 0 1-1.500-1.500v-8A1.500 1.500 0 0 1 5 5Z" /></svg>,
  check: <svg {...svg} className="size-4"><path d="m5 12.500 4.500 4.500L19 7.500" /></svg>,
  upload: <svg {...svg} className="size-4"><path d="M12 16V5M7 9.500 12 5l5 4.500M5 19h14" /></svg>,
} satisfies Record<string, ReactNode>;

export interface ActivityEvent {
  id: string;
  actor: string;
  /** Neutral wording between the actor and the object: "commented on", "completed". */
  action: string;
  object: { label: string; href: string };
  /** "2 hours ago". */
  when: string;
  /** For the <time> element. */
  datetime: string;
  /** A coloured dot, or a glyph inside a tinted circle. */
  marker: { kind: "dot"; tone: "emerald" | "sky" | "amber" | "rose" | "neutral" } | { kind: "glyph"; tone: "emerald" | "sky" | "amber" | "rose" | "neutral"; glyph: ReactNode };
  /** An optional quoted comment shown under the sentence. */
  quote?: string;
}

const TONES = {
  emerald: { dot: "bg-emerald-500", tint: "bg-emerald-50 text-emerald-700 border-emerald-200" },
  sky: { dot: "bg-sky-500", tint: "bg-sky-50 text-sky-700 border-sky-200" },
  amber: { dot: "bg-amber-500", tint: "bg-amber-50 text-amber-700 border-amber-200" },
  rose: { dot: "bg-rose-500", tint: "bg-rose-50 text-rose-700 border-rose-200" },
  neutral: { dot: "bg-neutral-400", tint: "bg-neutral-50 text-neutral-600 border-neutral-200" },
} as const;

function Marker({ marker }: { marker: ActivityEvent["marker"] }) {
  const tone = TONES[marker.tone];

  return marker.kind === "dot" ? (
    <span aria-hidden="true" className="relative z-10 grid size-8 shrink-0 place-items-center rounded-full border border-neutral-200 bg-white">
      <span className={`size-2.5 rounded-full ${tone.dot}`} />
    </span>
  ) : (
    <span aria-hidden="true" className={`relative z-10 grid size-8 shrink-0 place-items-center rounded-full border ${tone.tint}`}>{marker.glyph}</span>
  );
}

/** A card with a vertical timeline of recent events. A single hairline runs behind the markers. */
export function ActivityFeedCard({ title = "Recent activity", events, viewAllHref = "#all-activity", onFilter }: { title?: string; events: ActivityEvent[]; viewAllHref?: string; onFilter?: () => void }) {
  return (
    <section aria-labelledby="activity-title" className="overflow-hidden rounded-2xl border border-neutral-200 bg-white">
      <header className="flex items-center justify-between gap-3 border-b border-neutral-200 px-5 py-3">
        <h2 id="activity-title" className="text-sm font-semibold text-neutral-900">{title}</h2>
        <button type="button" onClick={onFilter} className={`inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>
          <svg {...svg} className="size-4"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
          Filter
        </button>
      </header>

      {/* The hairline is drawn once on the list, from the first marker's centre to the last, and the markers sit on top of it. */}
      <ol className="relative px-5 py-5 before:absolute before:inset-y-9 before:start-9 before:w-px before:bg-neutral-200 before:content-['']">
        {events.map((e, i) => (
          <li key={e.id} className={`relative flex gap-3 ${i < events.length - 1 ? "pb-6" : ""}`}>
            <Marker marker={e.marker} />
            <div className="min-w-0 flex-1 pt-1">
              <p className="text-sm leading-relaxed text-neutral-600">
                <strong className="font-semibold text-neutral-900">{e.actor}</strong> {e.action}{" "}
                <a href={e.object.href} className={`rounded font-medium text-neutral-900 underline decoration-neutral-300 underline-offset-2 hover:decoration-neutral-900 ${focus}`}>{e.object.label}</a>
              </p>
              <time dateTime={e.datetime} className="mt-0.5 block text-xs text-neutral-400">{e.when}</time>
              {e.quote && (
                <blockquote className="mt-3 ms-0 rounded-xl border border-sky-100 bg-sky-50/70 px-4 py-3 text-sm leading-relaxed text-neutral-700">
                  {e.quote}
                </blockquote>
              )}
            </div>
          </li>
        ))}
      </ol>

      <a href={viewAllHref} className={`flex min-h-12 w-full items-center justify-center border-t border-neutral-200 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50 hover:text-neutral-900 ${focus} focus-visible:ring-inset`}>
        View all activity
      </a>
    </section>
  );
}
