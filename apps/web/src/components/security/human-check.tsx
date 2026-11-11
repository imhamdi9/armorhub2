"use client";

import { useEffect, useRef, useState } from "react";

import { getChallenge, type SignupChallenge } from "@/services/auth.service";

const SCRIPTS = {
  turnstile: "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit",
  hcaptcha: "https://js.hcaptcha.com/1/api.js?render=explicit",
  recaptcha: "https://www.google.com/recaptcha/api.js?render=explicit",
} as const;

type Widget = { render: (el: HTMLElement, options: Record<string, unknown>) => unknown };
const widgetFor = (provider: keyof typeof SCRIPTS): Widget | undefined =>
  (window as unknown as Record<string, Widget | undefined>)[provider === "recaptcha" ? "grecaptcha" : provider];

/**
 * The bot checks a form needs, in one hook:
 *  - a signed timing token (`token`) and a delay before submit is allowed (`ready`)
 *  - the optional captcha an admin switched on (`captchaToken`, rendered by <CaptchaBox/>)
 * The hidden honeypot field is rendered by <Honeypot/>.
 */
export function useHumanCheck() {
  const [challenge, setChallenge] = useState<SignupChallenge | null>(null);
  const [waited, setWaited] = useState(false);
  const [captchaToken, setCaptchaToken] = useState("");

  useEffect(() => {
    let timer: ReturnType<typeof setTimeout> | undefined;
    getChallenge()
      .then((c) => {
        setChallenge(c);
        timer = setTimeout(() => setWaited(true), (c.min_seconds + 0.5) * 1000);
      })
      .catch(() => undefined);
    return () => clearTimeout(timer);
  }, []);

  const needsCaptcha = !!challenge?.captcha;

  return {
    challenge,
    token: challenge?.token ?? "",
    captchaToken,
    setCaptchaToken,
    /** True once the form may be submitted. */
    ready: !!challenge && waited && (!needsCaptcha || captchaToken !== ""),
    waiting: !!challenge && !waited,
  };
}

/** A field real people never see; bots that fill every input reveal themselves. */
export function Honeypot({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  return (
    // Hidden in place (not pushed off-screen: that creates a huge scroll area in right-to-left pages).
    <div
      aria-hidden="true"
      style={{ position: "absolute", top: 0, left: 0, width: 0, height: 0, opacity: 0, overflow: "hidden", pointerEvents: "none" }}
    >
      <label>
        Website
        <input type="text" name="website" tabIndex={-1} autoComplete="off" value={value} onChange={(e) => onChange(e.target.value)} />
      </label>
    </div>
  );
}

/** Renders Turnstile / hCaptcha / reCAPTCHA when the admin enabled one. */
export function CaptchaBox({
  challenge,
  onToken,
}: {
  challenge: SignupChallenge | null;
  onToken: (token: string) => void;
}) {
  const box = useRef<HTMLDivElement>(null);
  const captcha = challenge?.captcha;

  useEffect(() => {
    if (!captcha || !box.current) return;
    const el = box.current;
    const provider = captcha.provider;

    const render = () => {
      const widget = widgetFor(provider);
      if (!widget) return false;
      widget.render(el, {
        sitekey: captcha.site_key,
        callback: onToken,
        "expired-callback": () => onToken(""),
      });
      return true;
    };

    if (render()) return;

    const script = document.createElement("script");
    script.src = SCRIPTS[provider];
    script.async = true;
    script.onload = () => {
      // reCAPTCHA finishes loading a moment after the script runs.
      const tryRender = (left: number): void => {
        if (!render() && left > 0) setTimeout(() => tryRender(left - 1), 200);
      };
      tryRender(15);
    };
    document.head.appendChild(script);
  }, [captcha, onToken]);

  return captcha ? <div ref={box} className="flex justify-center" /> : null;
}
