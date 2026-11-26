"use client";

const focus =
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

const AVATAR_TONES = ["bg-amber-200 text-amber-900", "bg-sky-200 text-sky-900", "bg-rose-200 text-rose-900", "bg-emerald-200 text-emerald-900"];

function CheckIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" className="size-3" fill="none" stroke="currentColor" strokeWidth="3.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="m5 12.5 4.5 4.5L19 7.5" />
    </svg>
  );
}

export interface InvitationCardProps {
  inviter: string;
  workspace: string;
  domain: string;
  role: string;
  /** Names of teammates to draw as stacked avatars (initials only). */
  teammates: string[];
  /** How many more people are in the workspace beyond the avatars shown. */
  moreTeammates?: number;
  invitedEmail: string;
  expiresInDays?: number;
  onAccept?: () => void;
  onDecline?: () => void;
  busy?: boolean;
}

/** A team-invite acceptance card. It only draws the screen: accepting and declining are wired through the callbacks. */
export function InvitationCard({
  inviter,
  workspace,
  domain,
  role,
  teammates,
  moreTeammates = 0,
  invitedEmail,
  expiresInDays = 7,
  onAccept,
  onDecline,
  busy = false,
}: InvitationCardProps) {
  const initial = (s: string) => (s.trim()[0] ?? "?").toUpperCase();
  const total = teammates.length + moreTeammates;

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="invite-title" className="w-full max-w-md rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="flex flex-col items-center text-center">
          <div className="relative flex items-center" aria-hidden="true">
            <span className="relative z-0 grid size-14 place-items-center rounded-full bg-amber-200 text-lg font-semibold text-amber-900 ring-4 ring-white">{initial(inviter)}</span>
            <span className="relative z-0 -ms-2 grid size-14 place-items-center rounded-2xl bg-neutral-900 text-lg font-semibold text-white ring-4 ring-white">{initial(workspace)}</span>
            <span className="absolute start-1/2 top-1/2 z-10 grid size-6 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full bg-neutral-900 text-white ring-2 ring-white rtl:translate-x-1/2">
              <CheckIcon />
            </span>
          </div>

          <h1 id="invite-title" className="mt-5 text-xl font-semibold tracking-tight text-neutral-900">
            {inviter} invited you to {workspace}
          </h1>
          <p className="mt-1.5 text-sm text-neutral-500" dir="ltr">{domain}</p>
        </div>

        <dl className="mt-6 divide-y divide-neutral-200 rounded-xl border border-neutral-200 text-sm">
          <div className="flex min-h-11 items-center justify-between gap-4 px-4 py-2.5">
            <dt className="text-neutral-500">Your role</dt>
            <dd>
              <span className="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-800">{role}</span>
            </dd>
          </div>
          <div className="flex min-h-11 items-center justify-between gap-4 px-4 py-2.5">
            <dt className="text-neutral-500">Teammates</dt>
            <dd className="flex items-center gap-2">
              <span className="flex -space-x-1.5 rtl:space-x-reverse" aria-hidden="true">
                {teammates.slice(0, 4).map((name, i) => (
                  <span key={name} className={`grid size-7 place-items-center rounded-full text-xs font-semibold ring-2 ring-white ${AVATAR_TONES[i % AVATAR_TONES.length]}`}>{initial(name)}</span>
                ))}
                {moreTeammates > 0 && (
                  <span className="grid size-7 place-items-center rounded-full bg-neutral-100 text-xs font-semibold text-neutral-600 ring-2 ring-white">+{moreTeammates}</span>
                )}
              </span>
              <span className="sr-only">{total} teammates already in {workspace}</span>
            </dd>
          </div>
          <div className="flex min-h-11 items-center justify-between gap-4 px-4 py-2.5">
            <dt className="shrink-0 text-neutral-500">Invited email</dt>
            <dd className="min-w-0 truncate font-mono text-xs text-neutral-900" dir="ltr">{invitedEmail}</dd>
          </div>
        </dl>

        <div className="mt-6 space-y-2">
          <button
            type="button"
            onClick={onAccept}
            disabled={busy}
            className={`inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60 ${focus}`}
          >
            Accept invitation
          </button>
          <button
            type="button"
            onClick={onDecline}
            disabled={busy}
            className={`inline-flex min-h-11 w-full items-center justify-center rounded-xl px-4 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-100 hover:text-neutral-900 disabled:opacity-60 ${focus}`}
          >
            Decline
          </button>
        </div>

        <p className="mt-5 text-center text-xs leading-relaxed text-neutral-500">
          This invitation expires in {expiresInDays} days. The workspace admin can see when you accept it.
        </p>
      </section>
    </div>
  );
}
