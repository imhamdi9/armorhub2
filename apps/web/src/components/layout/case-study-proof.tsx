"use client";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export interface CaseMetric {
  /** The big figure, already formatted: "52%", "3.1×". */
  figure: string;
  label: string;
  /** Which way the number moved. */
  direction: "up" | "down";
  /** Green when the move is good news (a falling no-show rate is an improvement). Defaults to true. */
  improved?: boolean;
}

export interface CaseStudy {
  customer: string;
  /** Two sentences; `key` is the clause that gets the highlight. */
  quote: { before: string; key: string; after: string };
  name: string;
  role: string;
  metrics: [CaseMetric, CaseMetric, CaseMetric];
  href: string;
}

function Arrow({ direction, improved }: Pick<CaseMetric, "direction" | "improved">) {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" className={`size-4 shrink-0 ${improved === false ? "text-neutral-400" : "text-emerald-600"}`} fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
      {direction === "up" ? <path d="M12 19V5M5.500 11.500 12 5l6.500 6.500" /> : <path d="M12 5v14M5.500 12.500 12 19l6.500-6.500" />}
    </svg>
  );
}

function Card({ study }: { study: CaseStudy }) {
  const initials = study.name.split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase();

  return (
    <article className="flex flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white">
      <div className="flex-1 p-6 sm:p-8">
        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-neutral-500">{study.customer}</p>

        <blockquote className="mt-5">
          <p className="text-lg leading-relaxed text-neutral-900 sm:text-xl sm:leading-relaxed">
            “{study.quote.before}
            <span className="rounded bg-neutral-100 px-1 font-medium box-decoration-clone">{study.quote.key}</span>
            {study.quote.after}”
          </p>
        </blockquote>

        <div className="mt-6 flex items-center gap-3">
          <span aria-hidden="true" className="grid size-10 shrink-0 place-items-center rounded-full bg-neutral-200 text-sm font-semibold text-neutral-700">{initials}</span>
          <div className="min-w-0">
            <p className="text-sm font-medium text-neutral-900">{study.name}</p>
            <p className="text-sm text-neutral-500">{study.role}</p>
          </div>
        </div>
      </div>

      <dl className="grid grid-cols-3 divide-x divide-neutral-200 rtl:divide-x-reverse border-t border-neutral-200 bg-neutral-50">
        {study.metrics.map((m) => (
          <div key={m.label} className="flex flex-col gap-1 p-4 sm:px-5 sm:py-5">
            <dd className="flex items-center gap-1.5 text-2xl font-semibold tracking-tight text-neutral-900 tabular-nums sm:text-3xl">
              {m.figure}
              <Arrow direction={m.direction} improved={m.improved} />
              <span className="sr-only">{m.improved === false ? "" : "improvement"}</span>
            </dd>
            <dt className="min-h-[2.25em] text-[10px] font-medium uppercase leading-snug tracking-wider text-neutral-500">{m.label}</dt>
          </div>
        ))}
      </dl>

      <a href={study.href} className={`group inline-flex min-h-12 items-center justify-between gap-2 border-t border-neutral-200 px-6 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-50 sm:px-8 ${focus} focus-visible:ring-inset`}>
        <span>Read the full story<span className="sr-only"> about {study.customer}</span></span>
        <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 transition-transform group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </article>
  );
}

/** Customer proof: a quote with the key clause highlighted, three results and a link to the full story, for each case study. */
export function CaseStudyProof({ studies }: { studies: CaseStudy[] }) {
  return (
    <section aria-label="Customer results" className="mx-auto w-full max-w-5xl px-4 py-12 sm:px-6">
      <div className="grid gap-6 lg:grid-cols-2">
        {studies.map((s) => <Card key={s.customer} study={s} />)}
      </div>
    </section>
  );
}
