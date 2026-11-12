"use client";

import { useEffect, useState, type ReactNode } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

export interface FaqCategory {
  /** Used for the anchor, so it must be unique and URL-safe. */
  id: string;
  title: string;
  items: { question: string; answer: ReactNode }[];
}

/** An inline link for answers. */
export function AnswerLink({ href, children }: { href: string; children: ReactNode }) {
  return <a href={href} className={`rounded font-medium text-neutral-900 underline underline-offset-2 hover:text-neutral-700 ${focus}`}>{children}</a>;
}

/** A help-centre FAQ: a sticky topic rail beside the answers on wide screens, stacked above them on small ones. */
export function HelpCentreFaq({ categories, contactHref = "#contact" }: { categories: FaqCategory[]; contactHref?: string }) {
  const [active, setActive] = useState(categories[0]?.id ?? "");

  // Highlight the topic whose section is nearest the top of the screen as the reader scrolls.
  useEffect(() => {
    const sections = categories.map((c) => document.getElementById(c.id)).filter((el): el is HTMLElement => !!el);
    if (typeof IntersectionObserver === "undefined") return;
    const io = new IntersectionObserver(
      (entries) => {
        const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (visible) setActive(visible.target.id);
      },
      { rootMargin: "-96px 0px -60% 0px" },
    );
    sections.forEach((s) => io.observe(s));

    return () => io.disconnect();
  }, [categories]);

  return (
    <div className="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 lg:grid lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-12">
      <aside className="mb-10 lg:sticky lg:top-24 lg:mb-0 lg:self-start">
        <nav aria-label="Browse by topic">
          <p className="px-3 text-xs font-medium uppercase tracking-wide text-neutral-500">Browse by topic</p>
          <ul className="mt-2 space-y-1">
            {categories.map((c) => (
              <li key={c.id}>
                <a
                  href={`#${c.id}`}
                  onClick={() => setActive(c.id)}
                  aria-current={active === c.id ? "true" : undefined}
                  className={`flex min-h-11 items-center justify-between rounded-lg px-3 text-sm font-medium transition-colors ${active === c.id ? "bg-neutral-100 text-neutral-900" : "text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900"} ${focus}`}
                >
                  {c.title}
                  <span className="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-neutral-500 ring-1 ring-neutral-200" aria-label={`${c.items.length} articles`}>{c.items.length}</span>
                </a>
              </li>
            ))}
          </ul>
        </nav>

        <div className="mt-6 rounded-xl border border-neutral-200 bg-white p-4">
          <p className="text-sm font-semibold text-neutral-900">Still stuck?</p>
          <p className="mt-1 text-sm leading-relaxed text-neutral-500">Our team replies within one working day.</p>
          <a href={contactHref} className={`mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Contact support</a>
        </div>
      </aside>

      <div className="min-w-0 space-y-14">
        {categories.map((c) => (
          <section key={c.id} id={c.id} aria-labelledby={`${c.id}-title`} className="scroll-mt-24">
            <h2 id={`${c.id}-title`} className="border-b border-neutral-200 pb-3 text-xl font-semibold tracking-tight text-neutral-900">
              <a href={`#${c.id}`} className={`rounded ${focus}`}>{c.title}</a>
            </h2>
            <dl className="mt-6 space-y-8">
              {c.items.map((item) => (
                <div key={item.question}>
                  <dt className="font-medium text-neutral-900">{item.question}</dt>
                  <dd className="mt-2 max-w-prose text-sm leading-relaxed text-neutral-600">{item.answer}</dd>
                </div>
              ))}
            </dl>
          </section>
        ))}
      </div>
    </div>
  );
}
