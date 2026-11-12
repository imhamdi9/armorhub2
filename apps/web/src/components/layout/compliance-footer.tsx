import type { ReactNode } from "react";

const focus = "rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export const MARK_GLYPHS = {
  registration: <svg {...svg} className="size-4"><path d="M12 3 5 6v5.500c0 4.400 2.900 8.200 7 9.500 4.100-1.300 7-5.100 7-9.500V6l-7-3Z" /><path d="m9 12 2 2 4-4.500" /></svg>,
  deposit: <svg {...svg} className="size-4"><path d="M3.500 9.500 12 4l8.500 5.500M5.500 10v7M9.500 10v7M14.500 10v7M18.500 10v7M3.500 20h17" /></svg>,
  accessibility: <svg {...svg} className="size-4"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="7.800" r="1" fill="currentColor" /><path d="M7.500 10.500 12 11l4.500-.5M12 11v3.500M9.500 18l2.500-3.500 2.500 3.500" /></svg>,
} satisfies Record<string, ReactNode>;

export interface FooterColumn { title: string; links: { label: string; href: string }[] }
export interface TrustMark { glyph: ReactNode; label: string; href: string }

export interface ComplianceFooterProps {
  columns: [FooterColumn, FooterColumn, FooterColumn, FooterColumn];
  notice: { heading: string; paragraphs: [ReactNode, ReactNode, ReactNode] };
  marks: [TrustMark, TrustMark, TrustMark];
  company: string;
  /** The registered office, written out in full. */
  registeredOffice: string;
  year?: number;
  modernSlaveryHref?: string;
}

/** Inline link for the small print: underlined, quiet until hovered. */
export function LegalLink({ href, children }: { href: string; children: ReactNode }) {
  return <a href={href} className={`font-medium text-neutral-700 underline underline-offset-2 hover:text-neutral-900 ${focus}`}>{children}</a>;
}

/** A footer for regulated products: link columns, a regulatory notice, trust marks and the legal bar. All text is text-xs by design. */
export function ComplianceFooter({ columns, notice, marks, company, registeredOffice, year = new Date().getFullYear(), modernSlaveryHref = "#modern-slavery" }: ComplianceFooterProps) {
  return (
    <footer aria-label="Site footer" className="border-t border-neutral-200 bg-white text-xs leading-relaxed text-neutral-500">
      <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <nav aria-label="Footer" className="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-4">
          {columns.map((col) => (
            <div key={col.title}>
              <h2 className="font-semibold text-neutral-900">{col.title}</h2>
              <ul className="mt-3 space-y-1">
                {col.links.map((l) => (
                  <li key={l.label}>
                    <a href={l.href} className={`inline-flex min-h-8 items-center text-neutral-500 transition-colors hover:text-neutral-900 ${focus}`}>{l.label}</a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </nav>

        <hr className="my-10 border-neutral-200" />

        <section aria-labelledby="important-info" className="rounded-xl border border-neutral-200 bg-neutral-50 p-5 sm:p-6">
          <h2 id="important-info" className="font-semibold text-neutral-800">{notice.heading}</h2>
          <div className="mt-3 space-y-3 text-neutral-500">
            {notice.paragraphs.map((p, i) => <p key={i}>{p}</p>)}
          </div>
        </section>

        <ul className="mt-6 flex flex-wrap gap-2" aria-label="Trust marks">
          {marks.map((m) => (
            <li key={m.label}>
              <a href={m.href} className="inline-flex min-h-9 items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2">
                <span className="text-neutral-500">{m.glyph}</span>
                {m.label}
              </a>
            </li>
          ))}
        </ul>
      </div>

      <div className="border-t border-neutral-200">
        <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 sm:px-6 lg:flex-row lg:items-start lg:justify-between lg:gap-8">
          <p>© {year} {company}. All rights reserved.</p>
          <address className="not-italic lg:max-w-xl lg:flex-1 lg:text-center">Registered office: {registeredOffice}</address>
          <a href={modernSlaveryHref} className={`inline-flex min-h-8 items-center font-medium text-neutral-700 underline underline-offset-2 hover:text-neutral-900 ${focus}`}>Modern slavery statement</a>
        </div>
      </div>
    </footer>
  );
}
