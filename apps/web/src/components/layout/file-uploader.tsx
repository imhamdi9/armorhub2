"use client";

import { useEffect, useRef, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const ACCEPT_EXT = ["png", "jpg", "jpeg", "pdf", "zip"] as const;
const BADGE: Record<string, string> = { png: "bg-sky-50 text-sky-700", jpg: "bg-sky-50 text-sky-700", jpeg: "bg-sky-50 text-sky-700", pdf: "bg-rose-50 text-rose-700", zip: "bg-amber-50 text-amber-700" };

/** 1536 -> "1.5 KB". */
export const humanSize = (bytes: number) => (bytes < 1024 ? `${bytes} B` : bytes < 1024 ** 2 ? `${(bytes / 1024).toFixed(1)} KB` : `${(bytes / 1024 ** 2).toFixed(1)} MB`);
const extOf = (name: string) => name.split(".").pop()?.toLowerCase() ?? "";

export type UploadFn = (file: File, onProgress: (percent: number) => void, signal: AbortSignal) => Promise<void>;

/**
 * ===== STUB: REPLACE THIS FUNCTION WITH A REAL UPLOAD =====
 * It only simulates a transfer so the component can be seen working. Swap the body for an XHR or fetch upload that calls
 * `onProgress(0..100)` as bytes are sent, resolves when the server accepts the file, rejects when it does not, and honours `signal` to cancel.
 * (A file whose name contains "fail" is rejected here, just to show the error row and its Retry link.)
 */
export const uploadFile: UploadFn = (file, onProgress, signal) =>
  new Promise<void>((resolve, reject) => {
    let sent = 0;
    const timer = setInterval(() => {
      sent += 8 + Math.random() * 14;
      if (/fail/i.test(file.name) && sent > 55) { clearInterval(timer); reject(new Error("The server rejected this file.")); return; }
      onProgress(Math.min(100, Math.round(sent)));
      if (sent >= 100) { clearInterval(timer); resolve(); }
    }, 180);
    signal.addEventListener("abort", () => { clearInterval(timer); reject(new DOMException("Cancelled", "AbortError")); });
  });

interface Row { id: number; file: File; status: "uploading" | "done" | "error"; progress: number; error?: string }
let counter = 0;

/** A drag-and-drop file uploader with a row per file: type badge, name, size and a progress bar. Pass `upload` to use a real transfer. */
export function FileUploader({ maxBytes = 10 * 1024 * 1024, upload = uploadFile }: { maxBytes?: number; upload?: UploadFn }) {
  const [rows, setRows] = useState<Row[]>([]);
  const [over, setOver] = useState(false);
  const picker = useRef<HTMLInputElement>(null);
  const depth = useRef(0); // dragenter/leave also fire for children, so count them to avoid the highlight flickering
  const controllers = useRef(new Map<number, AbortController>());
  const patch = (id: number, p: Partial<Row>) => setRows((r) => r.map((x) => (x.id === id ? { ...x, ...p } : x)));

  useEffect(() => { const c = controllers.current; return () => c.forEach((a) => a.abort()); }, []);

  function start(id: number, file: File) {
    const ctl = new AbortController();
    controllers.current.set(id, ctl);
    patch(id, { status: "uploading", progress: 0, error: undefined });
    upload(file, (progress) => patch(id, { progress }), ctl.signal)
      .then(() => patch(id, { status: "done", progress: 100 }))
      .catch((e: Error) => { if (e.name !== "AbortError") patch(id, { status: "error", error: e.message || "The upload failed." }); })
      .finally(() => controllers.current.delete(id));
  }

  function accept(list: FileList | File[]) {
    for (const file of Array.from(list)) {
      const id = ++counter;
      const ext = extOf(file.name);
      const problem = !ACCEPT_EXT.includes(ext as (typeof ACCEPT_EXT)[number]) ? "This file type isn’t supported." : file.size > maxBytes ? `Too large. The limit is ${humanSize(maxBytes)}.` : null;
      setRows((r) => [...r, { id, file, status: problem ? "error" : "uploading", progress: 0, error: problem ?? undefined }]);
      if (!problem) start(id, file);
    }
  }

  function retry(row: Row) {
    // A file that was refused before sending can't succeed unchanged, so Retry asks for another; a failed transfer is simply tried again.
    const refusedLocally = !ACCEPT_EXT.includes(extOf(row.file.name) as (typeof ACCEPT_EXT)[number]) || row.file.size > maxBytes;
    if (refusedLocally) { setRows((r) => r.filter((x) => x.id !== row.id)); picker.current?.click(); } else start(row.id, row.file);
  }

  const remove = (id: number) => { controllers.current.get(id)?.abort(); setRows((r) => r.filter((x) => x.id !== id)); };

  return (
    <section aria-label="Upload files" className="mx-auto w-full max-w-xl">
      <div data-dropzone data-over={over}
        onDragEnter={(e) => { e.preventDefault(); depth.current++; setOver(true); }}
        onDragOver={(e) => e.preventDefault()}
        onDragLeave={() => { depth.current = Math.max(0, depth.current - 1); if (depth.current === 0) setOver(false); }}
        onDrop={(e) => { e.preventDefault(); depth.current = 0; setOver(false); accept(e.dataTransfer.files); }}
        className={`flex flex-col items-center rounded-2xl border-2 border-dashed px-6 py-12 text-center transition-colors ${over ? "border-neutral-900 bg-neutral-100" : "border-neutral-300 bg-white"}`}>
        <span aria-hidden="true" className="grid size-12 place-items-center rounded-xl bg-neutral-100 text-neutral-600">
          <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"><path d="M12 16V5M7 9.500 12 5l5 4.500M5 19h14" /></svg>
        </span>
        <p className="mt-4 text-base font-semibold text-neutral-900">Drag files here</p>
        <p className="mt-1 text-sm text-neutral-500">or{" "}
          <button type="button" onClick={() => picker.current?.click()} className={`inline-flex min-h-11 items-center rounded font-medium text-neutral-900 underline underline-offset-2 ${focus}`}>browse</button>
        </p>
        <p className="mt-1 text-xs text-neutral-400">PNG, JPG, PDF or ZIP, up to {humanSize(maxBytes)} each.</p>
        <input ref={picker} type="file" multiple accept=".png,.jpg,.jpeg,.pdf,.zip" aria-label="Choose files" className="sr-only" tabIndex={-1} onChange={(e) => { if (e.target.files) accept(e.target.files); e.target.value = ""; }} />
      </div>

      {/* One row per file; the row's layout is the template, filled in from each file. */}
      <ul aria-label="Files" aria-live="polite" className="mt-4 space-y-2">
        {rows.map((r) => {
          const ext = extOf(r.file.name);

          return (
            <li key={r.id} data-row data-status={r.status} className={`rounded-xl border p-3 ${r.status === "error" ? "border-rose-200 bg-rose-50/60" : "border-neutral-200 bg-white"}`}>
              <div className="flex items-center gap-3">
                <span aria-hidden="true" className={`grid h-9 min-w-9 shrink-0 place-items-center rounded-lg px-1.5 text-[11px] font-semibold uppercase ${BADGE[ext] ?? "bg-neutral-100 text-neutral-600"}`}>{ext.slice(0, 4) || "file"}</span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-neutral-900">{r.file.name}</p>
                  <p className={`text-xs ${r.status === "error" ? "text-rose-600" : r.status === "done" ? "text-emerald-700" : "text-neutral-500"}`}>
                    {r.status === "error" ? r.error : r.status === "done" ? `${humanSize(r.file.size)} · Uploaded` : `${humanSize(r.file.size)} · ${r.progress}%`}
                  </p>
                </div>
                {r.status === "error" && <button type="button" onClick={() => retry(r)} className={`inline-flex min-h-11 items-center rounded px-2 text-sm font-medium text-rose-700 underline underline-offset-2 ${focus}`}>Retry</button>}
                <button type="button" aria-label={`Remove ${r.file.name}`} onClick={() => remove(r.id)} className={`grid size-11 shrink-0 place-items-center rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>
                  <svg viewBox="0 0 24 24" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round"><path d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
              </div>
              {r.status !== "error" && (
                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-neutral-100" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={r.progress} aria-label={`Upload progress for ${r.file.name}`}>
                  <div data-bar style={{ width: `${r.progress}%` }} className={`h-full rounded-full transition-[width] duration-150 motion-reduce:transition-none ${r.status === "done" ? "bg-emerald-500" : "bg-neutral-900"}`} />
                </div>
              )}
            </li>
          );
        })}
      </ul>
    </section>
  );
}
