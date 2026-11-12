"use client";

import { useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export interface ChecklistTask { id: string; label: string; action: string; href?: string; done?: boolean }

/** A getting-started card. Clicking a task or its action toggles it; the bar and the count follow. */
export function GettingStartedChecklist({ title = "Get started", tasks }: { title?: string; tasks: ChecklistTask[] }) {
  const [done, setDone] = useState<Set<string>>(() => new Set(tasks.filter((t) => t.done).map((t) => t.id)));
  const total = tasks.length;
  const count = done.size;
  const percent = total ? Math.round((count / total) * 100) : 0;
  const finished = total > 0 && count === total;

  const toggle = (id: string) => setDone((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n; });

  return (
    <section aria-labelledby="checklist-title" className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-5 sm:p-6">
      <div className="flex items-baseline justify-between gap-3">
        <h2 id="checklist-title" className="text-base font-semibold text-neutral-900">{title}</h2>
        <p data-progress-label className={`text-sm ${finished ? "font-medium text-emerald-700" : "text-neutral-500"}`} aria-live="polite">
          {finished ? "All done. Nicely played! 🎉" : `${count} of ${total} done`}
        </p>
      </div>

      <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-100" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={percent} aria-label="Setup progress">
        <div data-progress-bar style={{ width: `${percent}%` }} className="h-full rounded-full bg-neutral-900 transition-[width] duration-500 ease-out motion-reduce:transition-none" />
      </div>

      <ul className="mt-4 divide-y divide-neutral-100">
        {tasks.map((t) => {
          const isDone = done.has(t.id);

          return (
            <li key={t.id} data-task={t.id} className="flex items-center gap-3">
              <button type="button" role="checkbox" aria-checked={isDone} onClick={() => toggle(t.id)} className={`flex min-h-12 flex-1 items-center gap-3 rounded-lg text-start ${focus}`}>
                <span aria-hidden="true" className={`grid size-5 shrink-0 place-items-center rounded-full border transition-colors ${isDone ? "border-neutral-900 bg-neutral-900 text-white" : "border-neutral-300 bg-white"}`}>
                  {isDone && <svg viewBox="0 0 24 24" className="size-3" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>}
                </span>
                <span className={`text-sm ${isDone ? "text-neutral-400 line-through" : "font-medium text-neutral-800"}`}>{t.label}</span>
              </button>
              {!isDone && (
                <a href={t.href ?? "#"} onClick={(e) => { if (!t.href) e.preventDefault(); toggle(t.id); }} className={`inline-flex min-h-11 shrink-0 items-center rounded-lg px-2 text-sm font-medium text-neutral-900 underline decoration-neutral-300 underline-offset-4 hover:decoration-neutral-900 ${focus}`}>{t.action}</a>
              )}
            </li>
          );
        })}
      </ul>
    </section>
  );
}
