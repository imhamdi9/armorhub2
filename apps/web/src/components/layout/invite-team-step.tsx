"use client";

import { useRef, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const ROLES = ["Admin", "Member", "Viewer"] as const;
type Role = (typeof ROLES)[number];
interface Invitee { email: string; role: Role }
const TONES = ["bg-amber-200 text-amber-900", "bg-sky-200 text-sky-900", "bg-rose-200 text-rose-900", "bg-emerald-200 text-emerald-900", "bg-violet-200 text-violet-900"];

const EMAIL = /^[^\s@,;]+@[^\s@,;]+\.[^\s@,;]{2,}$/;
const split = (raw: string) => raw.split(/[,;\s]+/).map((s) => s.trim()).filter(Boolean);
/** "amira.hassan@x.com" gives "AH"; "amira@x.com" gives "AM". */
export const initialsOf = (email: string) => {
  const local = email.split("@")[0] ?? "";
  const parts = local.split(/[._+-]+/).filter(Boolean);

  return (parts.length > 1 ? parts[0][0] + parts[1][0] : local.slice(0, 2)).toUpperCase() || "?";
};

/** An "invite your team" onboarding step. Paste one address or many; each is checked, de-duplicated and added as a row. */
export function InviteTeamStep({ step = 2, of = 4, onContinue, onSkip }: { step?: number; of?: number; onContinue?: (invitees: Invitee[]) => void; onSkip?: () => void }) {
  const [invitees, setInvitees] = useState<Invitee[]>([]);
  const [text, setText] = useState("");
  const [error, setError] = useState<string | null>(null);
  const input = useRef<HTMLInputElement>(null);
  const errId = "invite-error";

  function add() {
    const tokens = split(text);
    if (tokens.length === 0) { setError("Enter an email address."); return; }
    const have = new Set(invitees.map((i) => i.email.toLowerCase()));
    const bad: string[] = [];
    const fresh: Invitee[] = [];
    for (const t of tokens) {
      if (!EMAIL.test(t)) { bad.push(t); continue; }
      const key = t.toLowerCase();
      if (have.has(key)) continue; // already in the list: ignored without fuss
      have.add(key);
      fresh.push({ email: t, role: "Member" });
    }
    if (fresh.length > 0) setInvitees((l) => [...l, ...fresh]);
    // Bad addresses stay in the field so they can be fixed; good ones have moved to the list.
    setText(bad.join(", "));
    setError(bad.length > 0 ? `${bad.length === 1 ? "This doesn’t look like an email address" : "These don’t look like email addresses"}: ${bad.join(", ")}` : null);
    input.current?.focus();
  }

  const n = invitees.length;

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="invite-title" className="w-full max-w-xl">
        <div className="flex items-center gap-3">
          <p className="shrink-0 text-xs font-medium text-neutral-500">Step {step} of {of}</p>
          <div className="flex h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-200" role="progressbar" aria-valuemin={0} aria-valuemax={of} aria-valuenow={step} aria-label="Onboarding progress">
            <span className="h-full rounded-full bg-neutral-900" style={{ width: `${(step / of) * 100}%` }} />
          </div>
        </div>

        <h1 id="invite-title" className="mt-6 text-2xl font-semibold tracking-tight text-neutral-900">Invite your team</h1>
        <p className="mt-2 text-sm leading-relaxed text-neutral-500">Work is better together. Add the people you collaborate with and choose what they can do.</p>

        <form className="mt-6" onSubmit={(e) => { e.preventDefault(); add(); }} noValidate>
          <div className="flex gap-2">
            <label className="min-w-0 flex-1">
              <span className="sr-only">Email addresses</span>
              <input ref={input} type="text" inputMode="email" autoComplete="off" value={text} onChange={(e) => setText(e.target.value)}
                onPaste={(e) => { const pasted = e.clipboardData.getData("text"); if (/[,;\s]/.test(pasted.trim())) { e.preventDefault(); setText((t) => (t ? `${t}, ${pasted}` : pasted)); } }}
                aria-invalid={error ? true : undefined} aria-describedby={`${errId} invite-hint`} placeholder="name@company.com"
                className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20" />
            </label>
            <button type="submit" className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Add</button>
          </div>
          <p id={errId} role="alert" hidden={!error} className="mt-2 text-sm text-rose-600">{error}</p>
          <p id="invite-hint" className="mt-2 text-xs text-neutral-500">Tip: paste several addresses at once, separated by commas, semicolons or spaces.</p>
        </form>

        <ul aria-label="People to invite" className="mt-5 space-y-2">
          {invitees.map((inv, i) => (
            <li key={inv.email} data-invitee className="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white px-3 py-2">
              <span aria-hidden="true" className={`grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold ${TONES[i % TONES.length]}`}>{initialsOf(inv.email)}</span>
              <span className="min-w-0 flex-1 truncate text-sm text-neutral-900" dir="ltr">{inv.email}</span>
              <select aria-label={`Role for ${inv.email}`} value={inv.role} onChange={(e) => setInvitees((l) => l.map((x) => (x.email === inv.email ? { ...x, role: e.target.value as Role } : x)))}
                className="min-h-11 rounded-lg border border-neutral-300 bg-white px-2 text-sm text-neutral-800 sm:min-h-9 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20">
                {ROLES.map((r) => <option key={r}>{r}</option>)}
              </select>
              <button type="button" aria-label={`Remove ${inv.email}`} onClick={() => setInvitees((l) => l.filter((x) => x.email !== inv.email))} className={`grid size-11 place-items-center rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>
                <svg viewBox="0 0 24 24" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
              </button>
            </li>
          ))}
        </ul>

        {n === 0 && (
          <div data-empty className="mt-5 rounded-xl border border-dashed border-neutral-300 px-4 py-8 text-center text-sm text-neutral-500">
            Nobody has been added yet. Add a few email addresses above.
          </div>
        )}

        <div className="mt-8 flex items-center justify-between gap-4">
          <button type="button" onClick={onSkip} className={`inline-flex min-h-11 items-center rounded-lg px-2 text-sm text-neutral-500 underline underline-offset-4 hover:text-neutral-900 ${focus}`}>I&rsquo;ll do this later</button>
          <button type="button" data-continue onClick={() => onContinue?.(invitees)} className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-6 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>
            {n > 0 ? `Send ${n} invite${n === 1 ? "" : "s"}` : "Continue"}
          </button>
        </div>
      </section>
    </div>
  );
}
