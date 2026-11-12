"use client";

import type { ReactNode } from "react";

const focus =
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";

function ShieldIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" className="size-5 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 3 5 6v5.5c0 4.4 2.9 8.2 7 9.5 4.1-1.3 7-5.1 7-9.5V6l-7-3Z" />
      <path d="m9 12 2 2 4-4.5" />
    </svg>
  );
}

function LogoMark() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <path d="M6 18V6l12 12V6" />
    </svg>
  );
}

export interface PasskeySignInCardProps {
  /** The remembered account. */
  email: string;
  brand?: string;
  onContinueWithPasskey?: () => void;
  onNotYou?: () => void;
  onUseCode?: () => void;
  onUsePassword?: () => void;
  /** Disables the main button while the device prompt is open. */
  busy?: boolean;
  /** Replaces the footer line (for example with a link). */
  footer?: ReactNode;
}

/** A passkey-first sign-in card. It only draws the screen: the sign-in itself is wired through the callbacks. */
export function PasskeySignInCard({
  email,
  brand = "Northwind",
  onContinueWithPasskey,
  onNotYou,
  onUseCode,
  onUsePassword,
  busy = false,
  footer,
}: PasskeySignInCardProps) {
  const initial = (email.trim()[0] ?? "?").toUpperCase();

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="passkey-title" className="w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <div className="flex flex-col items-center text-center">
          <div className="grid size-11 place-items-center rounded-xl bg-neutral-900 text-white">
            <LogoMark />
          </div>
          <h1 id="passkey-title" className="mt-5 text-xl font-semibold tracking-tight text-neutral-900">
            Sign in to {brand}
          </h1>
          <p className="mt-1.5 text-sm text-neutral-500">This account uses a passkey.</p>
        </div>

        <div className="mt-6 flex min-h-11 items-center gap-3 rounded-xl border border-neutral-200 px-3 py-1.5">
          <span aria-hidden="true" className="grid size-8 shrink-0 place-items-center rounded-full bg-neutral-100 text-sm font-semibold text-neutral-700">
            {initial}
          </span>
          <span className="min-w-0 flex-1 truncate text-sm font-medium text-neutral-900" dir="ltr" title={email}>
            {email}
          </span>
          <button
            type="button"
            onClick={onNotYou}
            className={`-me-1 inline-flex min-h-11 shrink-0 items-center rounded-lg px-2 text-sm font-medium text-neutral-600 underline-offset-4 hover:text-neutral-900 hover:underline ${focus}`}
          >
            Not you?
          </button>
        </div>

        <button
          type="button"
          onClick={onContinueWithPasskey}
          disabled={busy}
          className={`mt-4 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-60 ${focus}`}
        >
          <ShieldIcon />
          Continue with passkey
        </button>
        <p className="mt-3 text-center text-xs leading-relaxed text-neutral-500">
          Your device will ask for Face ID, a fingerprint or the device PIN.
        </p>

        <div className="my-6 flex items-center gap-3" role="separator" aria-label="or">
          <span className="h-px flex-1 bg-neutral-200" />
          <span className="text-xs font-medium uppercase tracking-wide text-neutral-400">or</span>
          <span className="h-px flex-1 bg-neutral-200" />
        </div>

        <div className="space-y-3">
          {([["Use a one-time code", onUseCode], ["Sign in with password", onUsePassword]] as const).map(([label, action]) => (
            <button
              key={label}
              type="button"
              onClick={action}
              className={`inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-50 ${focus}`}
            >
              {label}
            </button>
          ))}
        </div>

        <p className="mt-6 text-center text-xs leading-relaxed text-neutral-500">
          {footer ?? "Security keys and other passkey-compatible devices work too."}
        </p>
      </section>
    </div>
  );
}
