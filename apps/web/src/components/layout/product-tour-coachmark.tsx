"use client";

import { useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-neutral-900";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export interface TourStep { title: string; body: string }

/** The tour copy lives in this array: add, remove or reword steps and the counter, dots and last-step label follow. */
export const TOUR: TourStep[] = [
  { title: "Welcome to your workspace", body: "Everything you create lives here. This quick tour shows the three tools you will use most." },
  { title: "Create from anywhere", body: "Press New to start a project, a document or a task without leaving the page you are on." },
  { title: "Find anything fast", body: "Search covers projects, people and files. Press the slash key from any screen to jump to it." },
  { title: "Share and collaborate", body: "Invite teammates here. You choose what each person can see and change before they join." },
];

/** A mock toolbar with a coachmark anchored under the middle button. Done or Skip tour removes the coachmark. */
export function ProductTourCoachmark({ steps = TOUR }: { steps?: TourStep[] }) {
  const [index, setIndex] = useState(0);
  const [active, setActive] = useState(true);
  const step = steps[index];
  const last = index === steps.length - 1;
  const btn = "inline-flex min-h-11 items-center gap-2 rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

  return (
    // The popover is absolutely positioned, so it adds no height of its own: the generous bottom padding keeps room for it.
    <div className="relative mx-auto w-full max-w-xl px-4 pb-64 pt-10">
      <div role="toolbar" aria-label="Workspace tools" className="flex flex-wrap items-center justify-center gap-3">
        <button type="button" className={btn}><svg {...svg} className="size-4"><path d="M12 5v14M5 12h14" /></svg>New</button>

        <div className="relative">
          <button type="button" aria-describedby={active ? "coachmark" : undefined} className={`${btn} ${active ? "relative z-20 ring-4 ring-sky-400 ring-offset-2" : ""}`}>
            <svg {...svg} className="size-4"><circle cx="11" cy="11" r="6.500" /><path d="m20 20-4-4" /></svg>Search
          </button>

          {active && (
            <div id="coachmark" role="dialog" aria-label="Product tour" className="absolute start-1/2 top-full z-30 mt-4 w-72 -translate-x-1/2 rounded-2xl bg-neutral-900 p-4 text-white shadow-xl rtl:translate-x-1/2 sm:w-80">
              <span aria-hidden="true" className="absolute -top-1.5 start-1/2 size-3 -translate-x-1/2 rotate-45 bg-neutral-900 rtl:translate-x-1/2" />
              <p data-step-counter className="text-xs font-medium text-neutral-400">Step {index + 1} of {steps.length}</p>
              <h2 data-step-title className="mt-1 text-sm font-semibold">{step.title}</h2>
              <p data-step-body className="mt-1 text-sm leading-relaxed text-neutral-300">{step.body}</p>

              <div className="mt-4 flex items-center justify-between gap-3">
                <div className="flex items-center gap-1.5" aria-hidden="true" data-dots>
                  {steps.map((_, i) => <span key={i} className={`h-1.5 rounded-full transition-all duration-200 motion-reduce:transition-none ${i === index ? "w-5 bg-white" : "w-1.5 bg-neutral-600"}`} />)}
                </div>
                <div className="flex items-center gap-1">
                  <button type="button" onClick={() => setActive(false)} className={`inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-neutral-400 hover:text-white ${focus}`}>Skip tour</button>
                  <button type="button" onClick={() => (last ? setActive(false) : setIndex((i) => i + 1))} className={`inline-flex min-h-11 items-center rounded-lg bg-white px-4 text-sm font-semibold text-neutral-900 hover:bg-neutral-100 ${focus}`}>{last ? "Done" : "Next"}</button>
                </div>
              </div>
            </div>
          )}
        </div>

        <button type="button" className={btn}><svg {...svg} className="size-4"><circle cx="9" cy="8.500" r="3" /><path d="M3.500 19c.6-3 2.900-4.700 5.500-4.700s4.900 1.700 5.500 4.700M17 8v6M14 11h6" /></svg>Share</button>
      </div>
    </div>
  );
}
