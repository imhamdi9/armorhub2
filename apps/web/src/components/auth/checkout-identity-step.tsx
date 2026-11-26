"use client";

import { useId, type FormEvent, type ReactNode } from "react";

const focus =
  "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const field =
  "block min-h-11 w-full rounded-lg border border-neutral-300 bg-white px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:outline-none focus-visible:border-neutral-900 focus-visible:ring-2 focus-visible:ring-neutral-900/20";

const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

const LockIcon = () => <svg {...svg} className="size-5"><rect x="5" y="10.5" width="14" height="9.5" rx="2" /><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5" /></svg>;
const PackageIcon = () => <svg {...svg} className="size-5"><path d="m12 3 8 4.2v9.6L12 21l-8-4.2V7.2L12 3Z" /><path d="m4.5 7.5 7.5 4 7.5-4M12 11.5V21" /></svg>;
const MailOffIcon = () => <svg {...svg} className="size-5"><rect x="3.5" y="5.5" width="17" height="13" rx="2" /><path d="m4 7 8 6 8-6M4 19 20 5" /></svg>;
const GoogleIcon = () => <svg aria-hidden="true" viewBox="0 0 24 24" className="size-5"><path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.3Z" /><path fill="#34A853" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22Z" /><path fill="#FBBC05" d="M6.4 14c-.2-.6-.3-1.3-.3-2s.1-1.4.3-2V7.4H3.1A10 10 0 0 0 2 12c0 1.6.4 3.1 1.1 4.6L6.4 14Z" /><path fill="#EA4335" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.8-2.8A10 10 0 0 0 3.1 7.4L6.4 10C7.2 7.700 9.400 5.900 12 5.900Z" /></svg>;
const AppleIcon = () => <svg aria-hidden="true" viewBox="0 0 24 24" className="size-5" fill="currentColor"><path d="M16.4 12.6c0-2.300 1.900-3.400 2-3.500-1.100-1.600-2.800-1.800-3.400-1.800-1.400-.1-2.800.9-3.500.9s-1.800-.8-3-.8c-1.500 0-3 .9-3.800 2.300-1.600 2.800-.4 7 1.200 9.300.8 1.100 1.700 2.400 2.900 2.300 1.200 0 1.600-.7 3-.7s1.800.7 3 .7c1.300 0 2.100-1.100 2.800-2.200.9-1.300 1.300-2.500 1.300-2.600-.1 0-2.500-1-2.500-3.900ZM14.100 5.700c.6-.8 1.100-1.900.9-3-.9 0-2.100.6-2.700 1.400-.6.700-1.100 1.800-.9 2.900 1 .1 2.100-.5 2.700-1.300Z" /></svg>;

export interface CheckoutIdentityStepProps {
  onGuest?: (email: string) => void;
  onSignIn?: (email: string, password: string) => void;
  onSocial?: (provider: "google" | "apple") => void;
  termsHref?: string;
  privacyHref?: string;
}

function Field({ label, children }: { label: string; children: (id: string) => ReactNode }) {
  const id = useId();

  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium text-neutral-800">{label}</label>
      {children(id)}
    </div>
  );
}

/** The first step of checkout: continue as a guest (recommended), sign in, or use a social account. It only draws the screen. */
export function CheckoutIdentityStep({ onGuest, onSignIn, onSocial, termsHref = "#terms", privacyHref = "#privacy" }: CheckoutIdentityStepProps) {
  const read = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    return new FormData(e.currentTarget);
  };

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-center bg-neutral-50 px-4 py-10">
      <section aria-labelledby="identity-title" className="w-full max-w-lg rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <p className="text-xs font-medium uppercase tracking-wide text-neutral-500">Step 1 of 3</p>
        <h1 id="identity-title" className="mt-1 text-xl font-semibold tracking-tight text-neutral-900">How would you like to check out?</h1>
        <p className="mt-1.5 text-sm text-neutral-500">An account is optional. You can order without one.</p>

        <form
          className="mt-6 rounded-xl border border-neutral-200 p-5 ring-2 ring-neutral-900"
          aria-labelledby="guest-title"
          onSubmit={(e) => onGuest?.(String(read(e).get("email") ?? ""))}
        >
          <div className="flex items-center justify-between gap-3">
            <h2 id="guest-title" className="text-sm font-semibold text-neutral-900">Checkout as a guest</h2>
            <span className="rounded-full bg-neutral-900 px-2 py-0.5 text-[11px] font-medium text-white">Recommended</span>
          </div>
          <div className="mt-4">
            <Field label="Email address">
              {(id) => <input id={id} name="email" type="email" required autoComplete="email" placeholder="you@example.com" className={field} />}
            </Field>
          </div>
          <button type="submit" className={`mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>
            Continue as guest
          </button>
          <p className="mt-3 text-xs leading-relaxed text-neutral-500">You can create an account afterwards from your receipt.</p>
        </form>

        <div className="my-6 flex items-center gap-3" role="separator" aria-label="or">
          <span className="h-px flex-1 bg-neutral-200" />
          <span className="text-xs font-medium uppercase tracking-wide text-neutral-400">or</span>
          <span className="h-px flex-1 bg-neutral-200" />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <form className="rounded-xl border border-neutral-200 p-4" aria-labelledby="signin-title" onSubmit={(e) => { const d = read(e); onSignIn?.(String(d.get("email") ?? ""), String(d.get("password") ?? "")); }}>
            <h2 id="signin-title" className="text-sm font-semibold text-neutral-900">Sign in</h2>
            <div className="mt-3 space-y-3">
              <Field label="Email">{(id) => <input id={id} name="email" type="email" required autoComplete="username" className={field} />}</Field>
              <Field label="Password">{(id) => <input id={id} name="password" type="password" required autoComplete="current-password" className={field} />}</Field>
            </div>
            <button type="submit" className={`mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-50 ${focus}`}>
              Sign in
            </button>
          </form>

          <div className="rounded-xl border border-neutral-200 p-4" role="group" aria-labelledby="social-title">
            <h2 id="social-title" className="text-sm font-semibold text-neutral-900">Use a social account</h2>
            <div className="mt-3 space-y-3">
              {([["google", "Google", <GoogleIcon key="g" />], ["apple", "Apple", <AppleIcon key="a" />]] as const).map(([provider, label, glyph]) => (
                <button key={provider} type="button" aria-label={`Continue with ${label}`} onClick={() => onSocial?.(provider)} className={`inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-neutral-300 bg-white px-3 text-sm font-medium text-neutral-900 transition-colors hover:bg-neutral-50 ${focus}`}>
                  {glyph}
                  {label}
                </button>
              ))}
            </div>
          </div>
        </div>

        <ul className="mt-8 grid gap-3 border-t border-neutral-200 pt-6 text-xs leading-snug text-neutral-600 sm:grid-cols-3">
          {([[<LockIcon key="l" />, "Payment is encrypted"], [<PackageIcon key="p" />, "Track your order by email"], [<MailOffIcon key="m" />, "No marketing without your consent"]] as const).map(([glyph, text]) => (
            <li key={text} className="flex items-start gap-2 sm:flex-col sm:items-center sm:text-center">
              <span className="text-neutral-700">{glyph}</span>
              <span>{text}</span>
            </li>
          ))}
        </ul>

        <p className="mt-6 text-center text-xs text-neutral-500">
          By continuing you agree to our <a href={termsHref} className={`rounded font-medium text-neutral-700 underline underline-offset-2 hover:text-neutral-900 ${focus}`}>Terms</a> and{" "}
          <a href={privacyHref} className={`rounded font-medium text-neutral-700 underline underline-offset-2 hover:text-neutral-900 ${focus}`}>Privacy Policy</a>.
        </p>
      </section>
    </div>
  );
}
