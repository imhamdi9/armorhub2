"use client";

import Link from "next/link";

import { CONSENT_KEY, readConsent } from "@/components/layout/cookie-consent-banner";
import { POLICIES, POLICIES_UPDATED, POLICY_KINDS, type PolicyKind } from "@/lib/policies";
import { formatDate, tr, useLocale } from "@/lib/i18n";

/** A policy page, in the visitor's language, with links to the others. */
export function PolicyView({ kind }: { kind: PolicyKind }) {
  const locale = useLocale();
  const p = POLICIES[kind];

  return (
    <div className="mx-auto max-w-3xl px-4 py-12" data-testid="policy">
      <nav aria-label={tr("Policies")} className="mb-8 flex flex-wrap gap-2 text-sm">
        {POLICY_KINDS.map((k) => (
          <Link key={k} href={`/policies/${k}`} aria-current={k === kind ? "page" : undefined} className={`rounded-full px-4 py-1.5 ${k === kind ? "bg-black text-white" : "bg-neutral-100 hover:bg-neutral-200"}`}>{POLICIES[k].title[locale]}</Link>
        ))}
      </nav>
      <h1 className="text-3xl font-bold tracking-tight">{p.title[locale]}</h1>
      <p className="mt-2 text-neutral-600">{p.summary[locale]}</p>

      {p.sections.map((s) => (
        <section key={s.heading.en} className="mt-8">
          <h2 className="text-xl font-semibold">{s.heading[locale]}</h2>
          {s.body.map((b) => <p key={b.en} className="mt-2 leading-relaxed text-neutral-800">{b[locale]}</p>)}
        </section>
      ))}

      {kind === "cookies" && (
        <button type="button" className="mt-8 rounded-full border border-neutral-300 px-5 py-2 text-sm font-medium hover:bg-neutral-50" data-testid="reset-consent"
          onClick={() => { try { window.localStorage.removeItem(CONSENT_KEY); } catch { /* storage unavailable */ } window.location.reload(); }}>
          {readConsent() ? tr("Change my cookie choice") : tr("Choose cookies")}
        </button>
      )}

      <p className="mt-12 border-t pt-4 text-sm text-neutral-500">{tr("Last updated")}: {formatDate(POLICIES_UPDATED)} · {tr("The operator of your market may add local terms.")}</p>
    </div>
  );
}
