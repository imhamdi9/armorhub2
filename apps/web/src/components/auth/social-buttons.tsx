"use client";

import { useEffect, useRef, useState } from "react";

import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getSocialProviders, socialLogin, type SocialProvider } from "@/services/auth.service";

/* eslint-disable @typescript-eslint/no-explicit-any -- the three provider SDKs are loaded from their own scripts and have no types here */
type Win = Window & { google?: any; FB?: any; AppleID?: any; fbAsyncInit?: () => void };
const w = () => window as Win;

const loaded: Record<string, Promise<void>> = {};
/** Loads a provider's script once. */
function loadScript(src: string): Promise<void> {
  loaded[src] ??= new Promise((resolve, reject) => {
    const el = document.createElement("script");
    el.src = src;
    el.async = true;
    el.onload = () => resolve();
    el.onerror = () => { delete loaded[src]; reject(new Error("script")); };
    document.head.appendChild(el);
  });
  return loaded[src];
}

const BUTTON = "flex h-[52px] w-full items-center justify-center gap-3 rounded-full border-2 border-[#e6e8ee] bg-white px-5 text-[15px] font-semibold text-[#3a4560] transition hover:bg-[#f6f8fc] disabled:opacity-60";

function FacebookIcon() {
  return <svg viewBox="0 0 24 24" className="h-6 w-6" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#1877f2" /><text x="12.6" y="19" textAnchor="middle" fontSize="19" fontWeight="700" fill="#fff" fontFamily="Arial, sans-serif">f</text></svg>;
}
function AppleIcon() {
  return <svg viewBox="0 0 24 24" className="h-6 w-6" aria-hidden="true"><path fill="#000" d="M16.4 12.700c0-2.400 2-3.500 2.100-3.600-1.200-1.700-3-1.900-3.600-2-1.500-.2-3 .9-3.800.9-.8 0-2-.9-3.300-.9-1.700 0-3.300 1-4.200 2.500-1.800 3.100-.5 7.700 1.300 10.200.9 1.200 1.900 2.600 3.200 2.500 1.300-.1 1.800-.8 3.300-.8s2 .8 3.300.8c1.400 0 2.300-1.200 3.100-2.500 1-1.400 1.400-2.800 1.400-2.900-.1 0-2.800-1.100-2.800-4.200ZM13.900 5.400c.7-.9 1.200-2 1-3.200-1 0-2.300.7-3 1.600-.7.800-1.200 2-1.100 3.100 1.100.1 2.300-.6 3.100-1.500Z" /></svg>;
}

/**
 * "Continue with Google / Facebook / Apple". Shown only for the providers the platform has set up. With a store the person is signing up
 * (a new customer is created); without one they are signing in, and the server says so if there is no account yet.
 */
export function SocialButtons({ store, onDone }: { store?: string | null; onDone: () => void }) {
  const [providers, setProviders] = useState<Partial<Record<SocialProvider, { client_id: string }>>>({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const googleBox = useRef<HTMLDivElement>(null);
  const storeRef = useRef(store);
  useEffect(() => { storeRef.current = store; }, [store]);

  useEffect(() => {
    getSocialProviders().then(setProviders).catch(() => setProviders({}));
  }, []);

  async function send(provider: SocialProvider, credential: string, name?: string) {
    setBusy(true);
    setError(null);
    try {
      await socialLogin({ provider, credential, ...(storeRef.current ? { store: storeRef.current } : {}), ...(name ? { name } : {}) });
      onDone();
    } catch (err) {
      setError(errorMessage(err, "Could not sign in. Please try again."));
    } finally {
      setBusy(false);
    }
  }

  const googleId = providers.google?.client_id;
  useEffect(() => {
    if (!googleId || !googleBox.current) return;
    const box = googleBox.current;
    loadScript("https://accounts.google.com/gsi/client").then(() => {
      const g = w().google?.accounts?.id;
      if (!g) return;
      g.initialize({ client_id: googleId, callback: (r: { credential: string }) => void send("google", r.credential) });
      g.renderButton(box, { type: "standard", theme: "outline", size: "large", shape: "pill", text: "continue_with", width: Math.min(box.clientWidth || 360, 400) });
    }).catch(() => setError(tr("Could not load Google sign-in.")));
    // eslint-disable-next-line react-hooks/exhaustive-deps -- send only reads refs and setters
  }, [googleId]);

  async function facebook() {
    const appId = providers.facebook?.client_id;
    if (!appId) return;
    try {
      if (!w().FB) {
        w().fbAsyncInit = () => w().FB.init({ appId, version: "v19.0", cookie: false, xfbml: false });
        await loadScript("https://connect.facebook.net/en_US/sdk.js");
        await new Promise((r) => setTimeout(r, 0));
      }
      w().FB.login((res: any) => {
        const token = res?.authResponse?.accessToken;
        if (token) void send("facebook", token);
      }, { scope: "public_profile,email" });
    } catch {
      setError(tr("Could not load Facebook sign-in."));
    }
  }

  async function apple() {
    const clientId = providers.apple?.client_id;
    if (!clientId) return;
    try {
      await loadScript("https://appleid.cdn-apple.com/appleauth/static/jsapi/appleid/1/en_US/appleid.auth.js");
      w().AppleID.auth.init({ clientId, scope: "name email", redirectURI: window.location.origin + window.location.pathname, usePopup: true });
      const res = await w().AppleID.auth.signIn();
      const name = [res?.user?.name?.firstName, res?.user?.name?.lastName].filter(Boolean).join(" ");
      if (res?.authorization?.id_token) void send("apple", res.authorization.id_token, name || undefined);
    } catch (err) {
      // The person closing the Apple window is not an error worth showing.
      if ((err as { error?: string })?.error !== "popup_closed_by_user") setError(tr("Could not load Apple sign-in."));
    }
  }

  if (Object.keys(providers).length === 0) return null;

  return (
    <div className="space-y-3" data-testid="social-buttons">
      <div className="flex items-center gap-3 text-sm text-muted-foreground" role="separator"><span className="h-px flex-1 bg-border" />{tr("Or")}<span className="h-px flex-1 bg-border" /></div>
      {providers.google && <div ref={googleBox} className="flex min-h-[44px] w-full justify-center" data-testid="social-google" />}
      {providers.facebook && <button type="button" className={BUTTON} disabled={busy} onClick={facebook} data-testid="social-facebook"><FacebookIcon />{tr("Continue with Facebook")}</button>}
      {providers.apple && <button type="button" className={BUTTON} disabled={busy} onClick={apple} data-testid="social-apple"><AppleIcon />{tr("Continue with Apple")}</button>}
      {error && <p className="text-center text-sm text-red-500" role="alert">{error}</p>}
    </div>
  );
}
