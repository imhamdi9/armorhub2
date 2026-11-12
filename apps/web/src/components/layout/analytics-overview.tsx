"use client";

import { useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

type RangeKey = "24h" | "7d" | "30d" | "90d";
interface Metric { label: string; value: string; change: number; bars: number[] }
interface RangeData { name: string; compared: string; metrics: [Metric, Metric, Metric, Metric]; visitors: number[]; signups: number[] }

/** Sample data per range. Swap this object for real numbers; the panel redraws from whatever it holds. */
export const DATA: Record<RangeKey, RangeData> = {
  "24h": { name: "Last 24 hours", compared: "the previous 24 hours", visitors: [18, 14, 11, 9, 12, 22, 34, 48, 55, 52, 47, 41, 44, 50, 58, 62, 57, 49, 43, 38, 31, 27, 22, 19], signups: [2, 1, 1, 1, 2, 3, 5, 7, 8, 8, 6, 6, 6, 7, 9, 9, 8, 7, 6, 5, 4, 3, 3, 2],
    metrics: [
      { label: "Visitors", value: "4,820", change: 6.2, bars: [30, 22, 18, 26, 44, 60, 72, 64, 58, 52, 40, 34] },
      { label: "Sign-ups", value: "126", change: -2.4, bars: [20, 24, 18, 22, 30, 42, 50, 46, 40, 36, 28, 24] },
      { label: "Conversion", value: "2.6%", change: -0.3, bars: [44, 40, 46, 42, 38, 40, 36, 38, 34, 36, 32, 34] },
      { label: "Revenue", value: "$3,410", change: 9.8, bars: [14, 18, 22, 20, 30, 38, 44, 52, 60, 56, 64, 70] },
    ] },
  "7d": { name: "Last 7 days", compared: "the previous 7 days", visitors: [310, 340, 365, 352, 398, 421, 447], signups: [18, 22, 24, 21, 27, 30, 34],
    metrics: [
      { label: "Visitors", value: "28,940", change: 12.4, bars: [38, 42, 40, 48, 52, 50, 58, 62, 60, 68, 72, 78] },
      { label: "Sign-ups", value: "824", change: 8.1, bars: [30, 34, 32, 38, 40, 44, 42, 50, 52, 56, 58, 64] },
      { label: "Conversion", value: "2.8%", change: -1.2, bars: [60, 58, 62, 56, 58, 54, 56, 52, 54, 50, 52, 50] },
      { label: "Revenue", value: "$21,300", change: 15.7, bars: [26, 30, 36, 34, 42, 46, 50, 58, 62, 70, 76, 84] },
    ] },
  "30d": { name: "Last 30 days", compared: "the previous 30 days", visitors: [240, 262, 255, 280, 301, 296, 318, 330, 342, 351, 360, 372], signups: [14, 15, 15, 17, 18, 18, 20, 21, 21, 23, 24, 25],
    metrics: [
      { label: "Visitors", value: "118,300", change: 4.9, bars: [50, 54, 52, 58, 56, 60, 62, 58, 64, 66, 68, 70] },
      { label: "Sign-ups", value: "3,342", change: 2.2, bars: [44, 46, 48, 46, 50, 52, 50, 54, 52, 56, 54, 58] },
      { label: "Conversion", value: "2.8%", change: -0.8, bars: [58, 56, 58, 54, 56, 52, 54, 50, 52, 48, 50, 48] },
      { label: "Revenue", value: "$84,900", change: -3.6, bars: [70, 66, 72, 64, 68, 60, 64, 58, 62, 54, 58, 52] },
    ] },
  "90d": { name: "Last 90 days", compared: "the previous 90 days", visitors: [180, 205, 230, 222, 260, 288, 301, 322, 340, 352, 380, 402], signups: [9, 11, 12, 12, 14, 16, 17, 18, 19, 21, 23, 25],
    metrics: [
      { label: "Visitors", value: "341,200", change: 21.5, bars: [24, 30, 34, 40, 44, 48, 56, 60, 66, 72, 80, 88] },
      { label: "Sign-ups", value: "9,610", change: 18.9, bars: [20, 26, 30, 34, 40, 44, 50, 56, 60, 66, 72, 80] },
      { label: "Conversion", value: "2.8%", change: -2.1, bars: [66, 62, 64, 58, 60, 54, 56, 50, 52, 48, 46, 44] },
      { label: "Revenue", value: "$247,000", change: 24.3, bars: [18, 24, 30, 36, 42, 50, 56, 64, 70, 78, 84, 92] },
    ] },
};

const RANGES = Object.keys(DATA) as RangeKey[];

function MiniBars({ bars, positive }: { bars: number[]; positive: boolean }) {
  return (
    <div className="flex h-10 items-end gap-1" aria-hidden="true" data-mini-bars>
      {bars.map((h, i) => <span key={i} style={{ height: `${h}%` }} className={`flex-1 rounded-sm ${positive ? "bg-emerald-200" : "bg-rose-200"} ${i === bars.length - 1 ? (positive ? "!bg-emerald-500" : "!bg-rose-500") : ""}`} />)}
    </div>
  );
}

function points(series: number[], max: number, w: number, h: number, pad: number) {
  const step = (w - pad * 2) / Math.max(series.length - 1, 1);

  return series.map((v, i) => [pad + i * step, h - pad - (v / max) * (h - pad * 2)] as const);
}

function AreaChart({ visitors, signups }: { visitors: number[]; signups: number[] }) {
  const W = 640, H = 220, PAD = 16;
  const max = Math.max(...visitors) * 1.1;
  const a = points(visitors, max, W, H, PAD);
  // Sign-ups are a different scale, so they are drawn against their own maximum.
  const b = points(signups, Math.max(...signups) * 1.1, W, H, PAD);
  const line = (pts: (readonly [number, number])[]) => pts.map(([x, y]) => `${x.toFixed(1)},${y.toFixed(1)}`).join(" ");
  const area = `${PAD},${H - PAD} ${line(a)} ${W - PAD},${H - PAD}`;

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="h-56 w-full" role="img" aria-label="Visitors and sign-ups over the selected range" preserveAspectRatio="none">
      {[0, 1, 2, 3, 4].map((i) => <line key={i} x1={PAD} x2={W - PAD} y1={PAD + (i * (H - PAD * 2)) / 4} y2={PAD + (i * (H - PAD * 2)) / 4} stroke="#e5e5e5" strokeDasharray="3 4" />)}
      <polygon points={area} fill="#171717" opacity="0.06" />
      <polyline data-series="visitors" points={line(a)} fill="none" stroke="#171717" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
      <polyline data-series="signups" points={line(b)} fill="none" stroke="#38bdf8" strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

/** An analytics overview: a range switcher, four metric cards with mini bars, and a wider trend chart. All of it redraws from the dataset of the chosen range. */
export function AnalyticsOverview({ onExport }: { onExport?: (range: RangeKey) => void }) {
  const [range, setRange] = useState<RangeKey>("7d");
  const d = DATA[range];

  return (
    <div className="mx-auto w-full max-w-6xl space-y-6 px-4 py-8 sm:px-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-semibold tracking-tight text-neutral-900">Analytics</h1>
          <p data-subline className="mt-1 text-sm text-neutral-500" aria-live="polite">{d.name} · compared with {d.compared}</p>
        </div>
        <div className="flex items-center gap-2">
          <div role="group" aria-label="Date range" className="inline-flex rounded-xl border border-neutral-200 bg-white p-1">
            {RANGES.map((r) => (
              <button key={r} type="button" aria-pressed={range === r} onClick={() => setRange(r)}
                className={`min-h-11 min-w-11 rounded-lg px-3 text-sm font-medium transition-colors ${range === r ? "bg-neutral-900 text-white" : "text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900"} ${focus}`}>{r}</button>
            ))}
          </div>
          <button type="button" onClick={() => onExport?.(range)} className={`inline-flex min-h-11 items-center gap-2 rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 transition-colors hover:bg-neutral-50 ${focus}`}>
            <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 4v11M7 11l5 5 5-5M5 20h14" /></svg>
            Export
          </button>
        </div>
      </header>

      <section aria-label="Key metrics" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {d.metrics.map((m) => {
          const positive = m.change >= 0;

          return (
            <article key={m.label} data-metric className="rounded-xl border border-neutral-200 bg-white p-5">
              <p className="text-sm text-neutral-500">{m.label}</p>
              <div className="mt-2 flex items-center justify-between gap-2">
                <p data-figure className="text-3xl font-semibold tracking-tight text-neutral-900 tabular-nums">{m.value}</p>
                <span data-change className={`rounded-full px-2 py-0.5 text-xs font-medium ${positive ? "bg-emerald-50 text-emerald-700" : "bg-rose-50 text-rose-700"}`}>
                  {positive ? "+" : "−"}{Math.abs(m.change).toFixed(1)}%<span className="sr-only"> {positive ? "up" : "down"} from {d.compared}</span>
                </span>
              </div>
              <div className="mt-4"><MiniBars bars={m.bars} positive={positive} /></div>
            </article>
          );
        })}
      </section>

      <section aria-labelledby="trend-title" className="rounded-xl border border-neutral-200 bg-white p-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 id="trend-title" className="text-sm font-semibold text-neutral-900">Visitors and sign-ups</h2>
          <ul className="flex items-center gap-4 text-xs text-neutral-500" aria-label="Legend">
            <li className="flex items-center gap-1.5"><span aria-hidden="true" className="size-2.5 rounded-full bg-neutral-900" />Visitors</li>
            <li className="flex items-center gap-1.5"><span aria-hidden="true" className="size-2.5 rounded-full bg-sky-400" />Sign-ups</li>
          </ul>
        </div>
        <div className="mt-4"><AreaChart visitors={d.visitors} signups={d.signups} /></div>
      </section>
    </div>
  );
}
