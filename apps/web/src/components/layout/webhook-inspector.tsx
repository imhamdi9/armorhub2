"use client";

import { useEffect, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export interface Attempt { n: number; code: number }
export interface Delivery {
  id: string; event: string; endpoint: string; /** "2 min ago" */ when: string; durationMs: number; signature: string;
  status: "succeeded" | "failed"; code: number; payload: unknown; response: string; attempts: Attempt[];
  /** Present while a retry is under way: which round (1 to 3) and how many seconds are left before that attempt is sent. */
  retry?: { round: number; remaining: number };
}

/** Wait before each retry: two, four, then eight seconds. */
export const BACKOFF = [2, 4, 8] as const;
const FAILED_BODY = '{"error":"service_unavailable","retry_after":30}';
const OK_BODY = '{"received":true}';

const DOT = { succeeded: "bg-emerald-500", failed: "bg-rose-500" } as const;
const PILL = { succeeded: "bg-emerald-50 text-emerald-700 ring-emerald-200", failed: "bg-rose-50 text-rose-700 ring-rose-200" } as const;
const LABEL = { succeeded: "Succeeded", failed: "Failed" } as const;

/**
 * One tick of the retry clock (one second). For every delivery being retried the countdown drops by a second; at zero an attempt is made.
 * ===== STUB: the attempt itself is simulated here. Replace `attemptDelivery` with a call to your API. =====
 * This demo fails the first two retries and succeeds on the third.
 */
const attemptDelivery = (round: number) => (round >= BACKOFF.length ? { code: 200, body: OK_BODY, ms: 180 } : { code: 503, body: FAILED_BODY, ms: 3000 });

export function tick(all: Delivery[]): Delivery[] {
  return all.map((d) => {
    if (!d.retry) return d;
    const remaining = d.retry.remaining - 1;
    if (remaining > 0) return { ...d, retry: { ...d.retry, remaining } };

    const r = attemptDelivery(d.retry.round);
    const attempts = [...d.attempts, { n: d.attempts.length + 1, code: r.code }];
    if (r.code === 200) return { ...d, attempts, status: "succeeded", code: 200, response: r.body, durationMs: r.ms, retry: undefined };

    return { ...d, attempts, code: r.code, response: r.body, durationMs: r.ms, retry: { round: d.retry.round + 1, remaining: BACKOFF[d.retry.round] } };
  });
}

function Block({ title, children }: { title: string; children: string }) {
  return (
    <div className="min-w-0">
      <p className="mb-1.5 text-xs font-medium text-neutral-500">{title}</p>
      <pre tabIndex={0} className={`max-h-56 overflow-auto rounded-xl bg-neutral-900 p-3 font-mono text-xs leading-relaxed text-neutral-100 ${focus}`}>{children}</pre>
    </div>
  );
}

/** A webhook delivery inspector: recent deliveries on the left, the selected one on the right, and a retry that backs off 2, 4, then 8 seconds. */
export function WebhookInspector({ initial }: { initial: Delivery[] }) {
  const [deliveries, setDeliveries] = useState<Delivery[]>(initial);
  const [selectedId, setSelectedId] = useState(initial[0]?.id ?? "");
  const retrying = deliveries.some((d) => d.retry);

  // One clock for every delivery being retried. Retries keep running if you look at another delivery.
  useEffect(() => {
    if (!retrying) return;
    const id = setInterval(() => setDeliveries(tick), 1000);

    return () => clearInterval(id);
  }, [retrying]);

  const d = deliveries.find((x) => x.id === selectedId) ?? deliveries[0];
  if (!d) return null;

  const startRetry = () => setDeliveries((all) => all.map((x) => (x.id === d.id && x.status === "failed" && !x.retry ? { ...x, retry: { round: 1, remaining: BACKOFF[0] } } : x)));

  return (
    <section aria-label="Webhook deliveries" className="mx-auto flex w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white md:min-h-[34rem] md:flex-row">
      <div className="max-h-64 shrink-0 overflow-y-auto border-b border-neutral-200 md:max-h-none md:w-72 md:border-b-0 md:border-e">
        <h2 className="border-b border-neutral-100 px-4 py-3 text-sm font-semibold text-neutral-900">Recent deliveries</h2>
        <ul>
          {deliveries.map((x) => (
            <li key={x.id}>
              <button type="button" data-delivery={x.id} aria-current={x.id === d.id ? "true" : undefined} onClick={() => setSelectedId(x.id)}
                className={`flex min-h-14 w-full items-center gap-3 px-4 py-2.5 text-start transition-colors hover:bg-neutral-50 ${x.id === d.id ? "bg-sky-50 hover:bg-sky-50" : ""} ${focus} focus-visible:ring-inset`}>
                <span data-dot aria-hidden="true" className={`size-2.5 shrink-0 rounded-full ${DOT[x.status]}`} />
                <span className="min-w-0 flex-1">
                  <span className="block truncate font-mono text-[13px] text-neutral-900">{x.event}</span>
                  <span className="block text-xs text-neutral-500"><span data-code className={x.status === "failed" ? "font-medium text-rose-600" : ""}>{x.code}</span> · {x.when}<span className="sr-only"> {LABEL[x.status]}</span></span>
                </span>
              </button>
            </li>
          ))}
        </ul>
      </div>

      <div className="min-w-0 flex-1 p-5 sm:p-6">
        <header className="flex flex-wrap items-center gap-3">
          <h2 data-event className="me-auto min-w-0 truncate font-mono text-base font-semibold text-neutral-900">{d.event}</h2>
          <span data-pill className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${PILL[d.status]}`}>{LABEL[d.status]}</span>
          <button type="button" data-retry onClick={startRetry} disabled={d.status === "succeeded" || !!d.retry}
            className={`inline-flex min-h-11 min-w-36 items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 tabular-nums transition-colors hover:bg-neutral-50 disabled:cursor-not-allowed disabled:opacity-60 ${focus}`}>
            {d.retry ? `Retrying in ${d.retry.remaining}s` : "Retry"}
          </button>
        </header>
        <p role="status" className="sr-only">{d.retry ? `Retry ${d.retry.round} of ${BACKOFF.length} in ${d.retry.remaining} seconds` : `Delivery ${LABEL[d.status].toLowerCase()}`}</p>

        <dl className="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 rounded-xl border border-neutral-200 p-4 text-sm sm:grid-cols-4">
          <div className="col-span-2"><dt className="text-xs text-neutral-500">Endpoint</dt><dd className="truncate font-mono text-xs text-neutral-900" title={d.endpoint}>{d.endpoint}</dd></div>
          <div><dt className="text-xs text-neutral-500">Attempts</dt><dd data-attempts className="font-semibold tabular-nums text-neutral-900">{d.attempts.length}</dd></div>
          <div><dt className="text-xs text-neutral-500">Duration</dt><dd className="font-semibold tabular-nums text-neutral-900">{d.durationMs.toLocaleString("en-US")} ms</dd></div>
          <div className="col-span-2 sm:col-span-4"><dt className="text-xs text-neutral-500">Signature</dt><dd className="truncate font-mono text-xs text-neutral-700" title={d.signature}>{d.signature}</dd></div>
        </dl>

        <div className="mt-5 grid gap-4 lg:grid-cols-2">
          <Block title="Request payload">{JSON.stringify(d.payload, null, 2)}</Block>
          <Block title={`Response body · ${d.code}`}>{d.response}</Block>
        </div>

        <div className="mt-5">
          <p className="mb-2 text-xs font-medium text-neutral-500">Attempts</p>
          <ul data-chips className="flex flex-wrap gap-2" aria-label="Delivery attempts">
            {d.attempts.map((a) => (
              <li key={a.n} className={`inline-flex min-h-8 items-center gap-1.5 rounded-full px-3 text-xs font-medium ring-1 ring-inset ${a.code === 200 ? "bg-emerald-50 text-emerald-700 ring-emerald-200" : "bg-rose-50 text-rose-700 ring-rose-200"}`}>
                <span className={`size-1.5 rounded-full ${a.code === 200 ? "bg-emerald-500" : "bg-rose-500"}`} aria-hidden="true" />#{a.n} · {a.code}
              </li>
            ))}
          </ul>
        </div>
      </div>
    </section>
  );
}
