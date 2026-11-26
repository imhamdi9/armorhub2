"use client";

import { useEffect, useId, useRef, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const MAX_LOGO_BYTES = 2 * 1024 * 1024;

export const SWATCHES = [
  { name: "Charcoal", value: "#171717" }, { name: "Blue", value: "#2563eb" }, { name: "Violet", value: "#7c3aed" }, { name: "Pink", value: "#db2777" },
  { name: "Red", value: "#dc2626" }, { name: "Orange", value: "#ea580c" }, { name: "Green", value: "#059669" }, { name: "Teal", value: "#0d9488" },
] as const;

/** Lower-case letters, numbers and single hyphens only; never starts with a hyphen. A trailing hyphen is allowed while typing and trimmed on blur. */
export const slugify = (raw: string) => raw.toLowerCase().normalize("NFKD").replace(/[̀-ͯ]/g, "").replace(/[^a-z0-9]+/g, "-").replace(/^-+/, "").slice(0, 30);
const trimSlug = (s: string) => s.replace(/-+$/, "");

/** Black or white text, whichever reads better on this colour. */
const textOn = (hex: string) => {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

  return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.45 ? "#171717" : "#ffffff";
};

export interface BrandingValue { name: string; color: string; subdomain: string; logo: File | null }

/** A workspace branding step: the form on the left, a live preview of the workspace on the right. */
export function WorkspaceBrandingStep({ suffix = ".workspace.app", step = 3, of = 4, onChange }: { suffix?: string; step?: number; of?: number; onChange?: (v: BrandingValue) => void }) {
  const [name, setName] = useState("");
  const [color, setColor] = useState<string>(SWATCHES[1].value);
  const [subdomain, setSubdomain] = useState("");
  const [touched, setTouched] = useState(false); // once the person edits the address themselves, stop following the name
  const [logo, setLogo] = useState<{ file: File; url: string } | null>(null);
  const [logoError, setLogoError] = useState<string | null>(null);
  const file = useRef<HTMLInputElement>(null);
  const ids = { name: useId(), sub: useId(), hint: useId(), subHint: useId() };

  const initial = (name.trim()[0] ?? "?").toUpperCase();
  const slugOk = subdomain.length === 0 || /^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/.test(trimSlug(subdomain));
  const shown = trimSlug(subdomain) || "your-team";
  const ink = textOn(color);

  useEffect(() => () => { if (logo) URL.revokeObjectURL(logo.url); }, [logo]);
  useEffect(() => { onChange?.({ name, color, subdomain: trimSlug(subdomain), logo: logo?.file ?? null }); }, [name, color, subdomain, logo, onChange]);

  function pickLogo(f: File | undefined) {
    if (!f) return;
    if (!/^image\/(png|jpeg|webp)$/.test(f.type)) { setLogoError("Use a PNG, JPG or WebP image."); return; }
    if (f.size > MAX_LOGO_BYTES) { setLogoError("That file is over 2 MB."); return; }
    setLogoError(null);
    setLogo({ file: f, url: URL.createObjectURL(f) });
  }

  const tile = (size: string, text: string, style?: React.CSSProperties) => (
    <span className={`grid shrink-0 place-items-center overflow-hidden rounded-xl font-semibold ${size} ${text}`} style={style} aria-hidden="true">
      {logo ? (
        // eslint-disable-next-line @next/next/no-img-element -- a local preview of the chosen file
        <img src={logo.url} alt="" className="size-full object-cover" />
      ) : initial}
    </span>
  );

  return (
    <div className="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
      <div className="grid gap-10 lg:grid-cols-2 lg:gap-14">
        <form className="min-w-0" onSubmit={(e) => e.preventDefault()} noValidate>
          <p className="text-xs font-medium text-neutral-500">Step {step} of {of}</p>
          <h1 className="mt-2 text-2xl font-semibold tracking-tight text-neutral-900">Brand your workspace</h1>
          <p className="mt-2 text-sm leading-relaxed text-neutral-500">Give your team a name, a logo and a colour. You can change all of this later.</p>

          <div className="mt-8 space-y-7">
            <div>
              <label htmlFor={ids.name} className="mb-1.5 block text-sm font-medium text-neutral-800">Workspace name</label>
              <input id={ids.name} type="text" value={name} maxLength={40} autoComplete="organization" placeholder="Acme Studio"
                onChange={(e) => { setName(e.target.value); if (!touched) setSubdomain(slugify(e.target.value)); }}
                className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20" />
            </div>

            <div>
              <p className="mb-1.5 text-sm font-medium text-neutral-800" id={`${ids.hint}-label`}>Logo</p>
              <div className="flex items-center gap-4">
                {tile("size-16 text-2xl", "bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200")}
                <div>
                  <div className="flex items-center gap-1">
                    <input ref={file} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" tabIndex={-1} aria-labelledby={`${ids.hint}-label`} onChange={(e) => { pickLogo(e.target.files?.[0]); e.target.value = ""; }} />
                    <button type="button" onClick={() => file.current?.click()} className={`inline-flex min-h-11 items-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-medium text-neutral-800 transition-colors hover:bg-neutral-50 ${focus}`}>Upload</button>
                    <button type="button" onClick={() => { setLogo(null); setLogoError(null); }} disabled={!logo} className={`inline-flex min-h-11 items-center rounded-lg px-3 text-sm text-neutral-500 hover:text-neutral-900 disabled:cursor-default disabled:opacity-50 disabled:hover:text-neutral-500 ${focus}`}>Remove</button>
                  </div>
                  <p className={`mt-1 text-xs ${logoError ? "text-rose-600" : "text-neutral-500"}`} role={logoError ? "alert" : undefined} id={ids.hint}>{logoError ?? "Square PNG, JPG or WebP, up to 2 MB."}</p>
                </div>
              </div>
            </div>

            <fieldset>
              <legend className="mb-1.5 text-sm font-medium text-neutral-800">Brand colour</legend>
              <div role="radiogroup" aria-label="Brand colour" className="flex flex-wrap gap-1">
                {SWATCHES.map((s) => (
                  <button key={s.value} type="button" role="radio" aria-checked={color === s.value} aria-label={s.name} onClick={() => setColor(s.value)}
                    className={`grid size-11 place-items-center rounded-full ${focus}`}>
                    <span style={{ backgroundColor: s.value, ...(color === s.value ? { boxShadow: `0 0 0 2px #fff, 0 0 0 4px ${s.value}` } : {}) }} className="size-8 rounded-full transition-shadow motion-reduce:transition-none" />
                  </button>
                ))}
              </div>
            </fieldset>

            <div>
              <label htmlFor={ids.sub} className="mb-1.5 block text-sm font-medium text-neutral-800">Workspace address</label>
              <div className={`flex min-h-11 items-center overflow-hidden rounded-xl border bg-white focus-within:ring-2 ${slugOk ? "border-neutral-300 focus-within:border-neutral-900 focus-within:ring-neutral-900/20" : "border-rose-400 focus-within:ring-rose-200"}`}>
                <input id={ids.sub} type="text" value={subdomain} inputMode="url" autoCapitalize="none" autoCorrect="off" spellCheck={false} dir="ltr" placeholder="acme-studio" aria-describedby={ids.subHint} aria-invalid={slugOk ? undefined : true}
                  onChange={(e) => { setTouched(true); setSubdomain(slugify(e.target.value)); }} onBlur={() => setSubdomain((s) => trimSlug(s))}
                  className="min-h-11 min-w-0 flex-1 bg-transparent px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus:outline-none" />
                <span className="select-none border-s border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-500 self-stretch grid place-items-center" dir="ltr">{suffix}</span>
              </div>
              <p id={ids.subHint} className={`mt-1.5 text-xs ${slugOk ? "text-neutral-500" : "text-rose-600"}`}>{slugOk ? "Letters, numbers and hyphens, 3 to 30 characters." : "Use 3 to 30 letters, numbers or hyphens."}</p>
            </div>
          </div>
        </form>

        <section aria-label="Live preview" className="min-w-0 lg:pt-10">
          <div className="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm">
            <div className="flex items-center gap-3 border-b border-neutral-200 bg-neutral-50 px-4 py-2.5">
              <span className="flex gap-1.5" aria-hidden="true">{[0, 1, 2].map((i) => <span key={i} className="size-2.5 rounded-full bg-neutral-300" />)}</span>
              <p data-preview-address className="min-w-0 flex-1 truncate rounded-md bg-white px-3 py-1 text-xs text-neutral-500 ring-1 ring-neutral-200" dir="ltr">
                <svg aria-hidden="true" viewBox="0 0 24 24" className="me-1.5 inline size-3 align-[-1px]" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="5" y="10.5" width="14" height="9.5" rx="2" /><path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.500" /></svg>
                https://<span className="text-neutral-900">{shown}</span>{suffix}
              </p>
            </div>

            <div data-preview-header className="flex items-center gap-3 px-4 py-3" style={{ backgroundColor: color, color: ink }}>
              {tile("size-8 text-sm", "", { backgroundColor: ink === "#ffffff" ? "rgba(255,255,255,0.2)" : "rgba(0,0,0,0.12)", color: ink })}
              <span data-preview-name className="truncate text-sm font-semibold">{name.trim() || "Your workspace"}</span>
              <span className="ms-auto hidden gap-2 sm:flex" aria-hidden="true">{[0, 1, 2].map((i) => <span key={i} className="h-2 w-10 rounded-full" style={{ backgroundColor: ink, opacity: 0.35 }} />)}</span>
            </div>

            <div className="space-y-3 p-4" aria-hidden="true">
              {[["w-2/3", "w-1/2"], ["w-3/4", "w-2/5"]].map(([a, b], i) => (
                <div key={i} className="flex items-center gap-3 rounded-xl border border-neutral-100 p-3">
                  <span className="size-9 shrink-0 rounded-lg bg-neutral-100" />
                  <div className="min-w-0 flex-1 space-y-2"><span className={`block h-2.5 rounded-full bg-neutral-200 ${a}`} /><span className={`block h-2 rounded-full bg-neutral-100 ${b}`} /></div>
                </div>
              ))}
            </div>
          </div>
          <p className="mt-3 text-center text-xs text-neutral-400">A preview of how your workspace will look.</p>
        </section>
      </div>
    </div>
  );
}
