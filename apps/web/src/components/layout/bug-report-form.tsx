"use client";

import { useRef, useState, useSyncExternalStore } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const field = "block w-full rounded-xl border border-neutral-300 bg-white px-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20";
const label = "mb-1.5 block text-sm font-medium text-neutral-800";

const ACCEPT = ["image/png", "image/jpeg", "image/gif", "video/mp4", "application/pdf", "text/plain"];
const ACCEPT_EXT = /\.(png|jpe?g|gif|mp4|pdf|txt|log)$/i;
const MAX_BYTES = 10 * 1024 * 1024;

// Full class names are written out so Tailwind can see them.
const SEVERITIES = [
  { id: "low", name: "Low", tint: "peer-checked:border-sky-300 peer-checked:bg-sky-50 peer-checked:text-sky-800" },
  { id: "medium", name: "Medium", tint: "peer-checked:border-amber-300 peer-checked:bg-amber-50 peer-checked:text-amber-800" },
  { id: "high", name: "High", tint: "peer-checked:border-orange-300 peer-checked:bg-orange-50 peer-checked:text-orange-800" },
  { id: "critical", name: "Critical", tint: "peer-checked:border-rose-300 peer-checked:bg-rose-50 peer-checked:text-rose-800" },
] as const;

/** The browser and operating system, read from the user agent. Good enough to pre-fill; the person can correct it. */
export function detectEnvironment(ua: string): { browser: string; os: string } {
  const v = (re: RegExp) => ua.match(re)?.[1];
  const browser = v(/Edg\/(\d+)/) ? `Edge ${v(/Edg\/(\d+)/)}` : v(/Firefox\/(\d+)/) ? `Firefox ${v(/Firefox\/(\d+)/)}` : v(/Chrome\/(\d+)/) ? `Chrome ${v(/Chrome\/(\d+)/)}` : v(/Version\/(\d+).*Safari/) ? `Safari ${v(/Version\/(\d+)/)}` : "Unknown browser";
  const os = /Windows NT 10/.test(ua) ? "Windows 10/11" : /Android (\d+)/.test(ua) ? `Android ${v(/Android (\d+)/)}` : /iPhone|iPad/.test(ua) ? "iOS" : /Mac OS X/.test(ua) ? "macOS" : /CrOS/.test(ua) ? "ChromeOS" : /Linux/.test(ua) ? "Linux" : "Unknown OS";

  return { browser, os };
}
const noop = () => () => undefined;

export interface BugReport { summary: string; severity: string; steps: string; expected: string; actual: string; environment: { browser: string; os: string; version: string }; files: string[] }

/** A bug report form with a severity control, a dropzone and pre-filled environment details. Drafts are kept in this browser. */
export function BugReportForm({ appVersion = "2.4.0", statusHref = "#status", draftKey = "bug-report.draft", onSubmit }: { appVersion?: string; statusHref?: string; draftKey?: string; onSubmit?: (r: BugReport) => void }) {
  // The server does not know the browser, so it renders nothing for these and the client fills them in without a hydration mismatch.
  const ua = useSyncExternalStore(noop, () => navigator.userAgent, () => "");
  const detected = detectEnvironment(ua);
  const [form, setForm] = useState({ summary: "", severity: "medium", steps: "", expected: "", actual: "" });
  const [env, setEnv] = useState<{ browser?: string; os?: string; version?: string }>({});
  const [files, setFiles] = useState<File[]>([]);
  const [fileError, setFileError] = useState<string | null>(null);
  const [dragging, setDragging] = useState(false);
  const [status, setStatus] = useState<{ tone: "ok" | "error"; text: string } | null>(null);
  const picker = useRef<HTMLInputElement>(null);
  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => { setForm((f) => ({ ...f, [k]: e.target.value })); setStatus(null); };
  const environment = { browser: env.browser ?? detected.browser, os: env.os ?? detected.os, version: env.version ?? appVersion };

  function addFiles(list: FileList | File[]) {
    const accepted: File[] = [];
    const problems: string[] = [];
    for (const f of Array.from(list)) {
      if (!ACCEPT.includes(f.type) && !ACCEPT_EXT.test(f.name)) problems.push(`${f.name}: this type isn’t accepted`);
      else if (f.size > MAX_BYTES) problems.push(`${f.name}: over 10 MB`);
      else accepted.push(f);
    }
    setFiles((cur) => [...cur, ...accepted].slice(0, 6));
    setFileError(problems.length ? problems.join(". ") : null);
  }

  function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!form.summary.trim()) { setStatus({ tone: "error", text: "Add a short summary so the team can find this report." }); return; }
    onSubmit?.({ ...form, environment, files: files.map((f) => f.name) });
    try { window.localStorage.removeItem(draftKey); } catch { /* nothing to clear */ }
    setStatus({ tone: "ok", text: "Thanks. Your report has been sent to the workspace admins." });
  }

  function saveDraft() {
    try { window.localStorage.setItem(draftKey, JSON.stringify({ ...form, environment })); setStatus({ tone: "ok", text: "Draft saved on this device." }); }
    catch { setStatus({ tone: "error", text: "Couldn’t save the draft in this browser." }); }
  }

  return (
    <div className="grid min-h-screen grid-cols-[minmax(0,1fr)] place-items-start bg-neutral-50 px-4 py-10">
      <section aria-labelledby="bug-title" className="mx-auto w-full max-w-2xl rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 id="bug-title" className="text-2xl font-semibold tracking-tight text-neutral-900">Report a bug</h1>
        <p className="mt-2 text-sm leading-relaxed text-neutral-500">
          Please send one issue per report. If something looks broken for everyone, check the{" "}
          <a href={statusHref} className={`rounded font-medium text-neutral-900 underline underline-offset-2 hover:text-neutral-700 ${focus}`}>status page</a> first: it may already be a known incident.
        </p>

        <form className="mt-7 space-y-6" onSubmit={submit} noValidate>
          <div>
            <label htmlFor="bug-summary" className={label}>Summary</label>
            <input id="bug-summary" type="text" value={form.summary} onChange={set("summary")} maxLength={120} placeholder="Exporting a report to CSV downloads an empty file" aria-invalid={status?.tone === "error" && !form.summary.trim() ? true : undefined} className={`${field} min-h-11`} />
          </div>

          <fieldset>
            <legend className={label}>Severity</legend>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
              {SEVERITIES.map((s) => (
                <label key={s.id} className="relative block cursor-pointer">
                  <input type="radio" name="severity" value={s.id} checked={form.severity === s.id} onChange={() => setForm((f) => ({ ...f, severity: s.id }))} className="peer sr-only" />
                  <span className={`flex min-h-11 items-center justify-center rounded-xl border border-neutral-300 bg-white px-3 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-50 peer-focus-visible:ring-2 peer-focus-visible:ring-neutral-900 peer-focus-visible:ring-offset-2 ${s.tint}`}>{s.name}</span>
                </label>
              ))}
            </div>
          </fieldset>

          <div>
            <label htmlFor="bug-steps" className={label}>Steps to reproduce</label>
            <textarea id="bug-steps" rows={5} value={form.steps} onChange={set("steps")} placeholder={"1. Open the Reports page\n2. Click “Export”\n3. Choose CSV and press Download"} className={`${field} py-2.5 font-mono text-[13px] leading-relaxed placeholder:text-neutral-400`} />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label htmlFor="bug-expected" className={label}>Expected behaviour</label>
              <textarea id="bug-expected" rows={3} value={form.expected} onChange={set("expected")} placeholder="A CSV with all visible rows" className={`${field} py-2.5`} />
            </div>
            <div>
              <label htmlFor="bug-actual" className={label}>Actual behaviour</label>
              <textarea id="bug-actual" rows={3} value={form.actual} onChange={set("actual")} placeholder="A file with only the header row" className={`${field} py-2.5`} />
            </div>
          </div>

          <div>
            <p className={label} id="bug-files-label">Attachments</p>
            <div role="group" aria-labelledby="bug-files-label" data-dropzone
              onDragOver={(e) => { e.preventDefault(); setDragging(true); }} onDragLeave={() => setDragging(false)}
              onDrop={(e) => { e.preventDefault(); setDragging(false); addFiles(e.dataTransfer.files); }}
              className={`rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors ${dragging ? "border-neutral-900 bg-neutral-50" : "border-neutral-300"}`}>
              <svg aria-hidden="true" viewBox="0 0 24 24" className="mx-auto size-6 text-neutral-400" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="m20.500 11-8.200 8.200a5 5 0 0 1-7.100-7.100l8.700-8.700a3.300 3.300 0 0 1 4.700 4.700l-8.700 8.700a1.700 1.700 0 0 1-2.400-2.400l7.900-7.900" /></svg>
              <p className="mt-2 text-sm text-neutral-600">Drag files here, or{" "}
                <button type="button" onClick={() => picker.current?.click()} className={`inline-flex min-h-11 items-center rounded font-medium text-neutral-900 underline underline-offset-2 ${focus}`}>browse</button>
              </p>
              <p className="mt-0.5 text-xs text-neutral-500">PNG, JPG, GIF, MP4, PDF, TXT or LOG, up to 10 MB each.</p>
              <input ref={picker} type="file" multiple accept=".png,.jpg,.jpeg,.gif,.mp4,.pdf,.txt,.log" className="sr-only" tabIndex={-1} aria-label="Choose files" onChange={(e) => { if (e.target.files) addFiles(e.target.files); e.target.value = ""; }} />
            </div>
            {fileError && <p role="alert" className="mt-2 text-xs text-rose-600">{fileError}</p>}
            {files.length > 0 && (
              <ul className="mt-3 space-y-1.5" aria-label="Attached files">
                {files.map((f, i) => (
                  <li key={f.name + i} className="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 text-sm text-neutral-700">
                    <span className="min-w-0 flex-1 truncate py-2">{f.name}</span>
                    <button type="button" aria-label={`Remove ${f.name}`} onClick={() => setFiles((cur) => cur.filter((_, j) => j !== i))} className={`grid size-11 place-items-center rounded-lg text-neutral-400 hover:text-neutral-900 ${focus}`}>
                      <svg viewBox="0 0 24 24" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          <details className="group rounded-xl border border-neutral-200">
            <summary className={`flex min-h-11 cursor-pointer list-none items-center justify-between rounded-xl px-4 text-sm font-medium text-neutral-800 ${focus}`}>
              Environment details
              <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 text-neutral-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="m6 9 6 6 6-6" /></svg>
            </summary>
            <div className="grid gap-4 border-t border-neutral-200 p-4 sm:grid-cols-3">
              {([["browser", "Browser"], ["os", "Operating system"], ["version", "App version"]] as const).map(([k, l]) => (
                <div key={k}>
                  <label htmlFor={`env-${k}`} className={label}>{l}</label>
                  <input id={`env-${k}`} type="text" value={environment[k]} onChange={(e) => setEnv((v) => ({ ...v, [k]: e.target.value }))} className={`${field} min-h-11`} />
                </div>
              ))}
            </div>
          </details>

          <div className="flex flex-wrap items-center gap-3 border-t border-neutral-100 pt-6">
            <button type="submit" className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-6 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Submit report</button>
            <button type="button" onClick={saveDraft} className={`inline-flex min-h-11 items-center rounded-xl px-4 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>Save draft</button>
            <p className="w-full text-xs text-neutral-500 sm:ms-auto sm:w-auto">Visible to your workspace admins.</p>
          </div>
          {status && <p role={status.tone === "error" ? "alert" : "status"} data-status className={`text-sm ${status.tone === "error" ? "text-rose-600" : "text-emerald-700"}`}>{status.text}</p>}
        </form>
      </section>
    </div>
  );
}
