"use client";

import { useEffect, useMemo, useRef, useState } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const svg = { fill: "none", stroke: "currentColor", strokeWidth: 1.75, strokeLinecap: "round", strokeLinejoin: "round", viewBox: "0 0 24 24", "aria-hidden": true } as const;

export interface Message { from: string; time: string; text: string; outgoing?: boolean }
export interface Thread { sender: string; time: string; subject: string; participants: string; messages: Message[]; unread?: boolean }
export type Threads = Record<string, Thread>;

const initials = (name: string) => name.split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase();
const TONES = ["bg-amber-200 text-amber-900", "bg-sky-200 text-sky-900", "bg-rose-200 text-rose-900", "bg-emerald-200 text-emerald-900", "bg-violet-200 text-violet-900", "bg-orange-200 text-orange-900"];
const ICON_BTN = `grid size-11 place-items-center rounded-lg text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 ${focus}`;

/** A two-pane inbox: the conversation list on the left, the open thread and a reply box on the right. Give it a height (it fills its parent). */
export function InboxLayout({ initial, initiallyOpen }: { initial: Threads; initiallyOpen?: string }) {
  const [threads, setThreads] = useState<Threads>(initial);
  const [unread, setUnread] = useState<Set<string>>(() => new Set(Object.entries(initial).filter(([id, t]) => t.unread && id !== initiallyOpen).map(([id]) => id)));
  const [selected, setSelected] = useState<string | null>(initiallyOpen ?? null);
  const [showThread, setShowThread] = useState(false); // below md only one pane fits, so the open thread replaces the list
  const [query, setQuery] = useState("");
  const [unreadOnly, setUnreadOnly] = useState(false);
  const [draft, setDraft] = useState("");
  const body = useRef<HTMLDivElement>(null);

  const open = (id: string) => {
    setSelected(id);
    setShowThread(true);
    setUnread((u) => { if (!u.has(id)) return u; const next = new Set(u); next.delete(id); return next; });
  };

  // Opening a thread always starts at its first message.
  useEffect(() => { body.current?.scrollTo({ top: 0 }); }, [selected]);

  const rows = useMemo(() => {
    const q = query.trim().toLowerCase();

    return Object.entries(threads).filter(([id, t]) => (!unreadOnly || unread.has(id)) && (!q || `${t.sender} ${t.subject} ${t.messages.at(-1)?.text ?? ""}`.toLowerCase().includes(q)));
  }, [threads, query, unreadOnly, unread]);

  const current = selected ? threads[selected] : null;

  function send(e: React.FormEvent) {
    e.preventDefault();
    const text = draft.trim();
    if (!text || !selected) return;
    setThreads((all) => ({ ...all, [selected]: { ...all[selected], messages: [...all[selected].messages, { from: "You", time: "Just now", text, outgoing: true }] } }));
    setDraft("");
    requestAnimationFrame(() => body.current?.scrollTo({ top: body.current.scrollHeight, behavior: "smooth" }));
  }

  return (
    <div className="flex h-full min-h-0 overflow-hidden rounded-2xl border border-neutral-200 bg-white">
      <section aria-label="Conversations" className={`${showThread ? "hidden md:flex" : "flex"} w-full shrink-0 flex-col border-neutral-200 md:w-[22rem] md:border-e`}>
        <header className="space-y-3 border-b border-neutral-200 p-4">
          <div className="flex items-center gap-2">
            <h1 className="text-base font-semibold text-neutral-900">Inbox</h1>
            <span className="rounded-full bg-neutral-900 px-2 py-0.5 text-xs font-medium text-white" aria-live="polite" aria-label={`${unread.size} unread`}>{unread.size}</span>
            <button type="button" aria-pressed={unreadOnly} aria-label="Show unread only" onClick={() => setUnreadOnly((v) => !v)} className={`${ICON_BTN} ms-auto ${unreadOnly ? "bg-neutral-100 text-neutral-900" : ""}`}>
              <svg {...svg} className="size-5"><path d="M4 6h16M7 12h10M10 18h4" /></svg>
            </button>
          </div>
          <label className="relative block">
            <span className="sr-only">Search conversations</span>
            <svg {...svg} className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-neutral-400"><circle cx="11" cy="11" r="6.500" /><path d="m20 20-4-4" /></svg>
            <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search" className="min-h-11 w-full rounded-lg border border-neutral-200 bg-neutral-50 ps-9 pe-3 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20" />
          </label>
        </header>

        <ul className="min-h-0 flex-1 divide-y divide-neutral-100 overflow-y-auto">
          {rows.length === 0 && <li className="p-6 text-center text-sm text-neutral-500">No conversations match.</li>}
          {rows.map(([id, t], i) => (
            <li key={id}>
              <button type="button" data-thread={id} aria-current={selected === id ? "true" : undefined} onClick={() => open(id)}
                className={`flex min-h-20 w-full items-start gap-3 px-4 py-3 text-start transition-colors hover:bg-neutral-50 ${selected === id ? "bg-neutral-100 hover:bg-neutral-100" : ""} ${focus} focus-visible:ring-inset`}>
                <span className="mt-3 flex w-2 shrink-0 justify-center" aria-hidden="true">
                  <span data-unread-dot className={`size-2 rounded-full bg-sky-500 ${unread.has(id) ? "" : "invisible"}`} />
                </span>
                <span aria-hidden="true" className={`grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold ${TONES[i % TONES.length]}`}>{initials(t.sender)}</span>
                <span className="min-w-0 flex-1">
                  <span className="flex items-baseline justify-between gap-2">
                    <span className={`truncate text-sm ${unread.has(id) ? "font-semibold" : "font-medium"} text-neutral-900`}>{t.sender}</span>
                    <span className="shrink-0 text-xs text-neutral-400">{t.time}</span>
                  </span>
                  <span className="block truncate text-sm text-neutral-800">{t.subject}</span>
                  <span className="line-clamp-1 text-sm text-neutral-500">{t.messages.at(-1)?.text}</span>
                  {unread.has(id) && <span className="sr-only">Unread</span>}
                </span>
              </button>
            </li>
          ))}
        </ul>
      </section>

      <section aria-label="Conversation" className={`${showThread ? "flex" : "hidden md:flex"} min-w-0 flex-1 flex-col`}>
        {current ? (
          <>
            <header className="flex items-center gap-2 border-b border-neutral-200 px-4 py-2 sm:px-5">
              <button type="button" aria-label="Back to conversations" onClick={() => setShowThread(false)} className={`${ICON_BTN} md:hidden`}>
                <svg {...svg} className="size-5"><path d="M19 12H5M11 6l-6 6 6 6" /></svg>
              </button>
              <div className="min-w-0 flex-1">
                <h2 data-reading-subject className="truncate text-sm font-semibold text-neutral-900">{current.subject}</h2>
                <p data-reading-participants className="truncate text-xs text-neutral-500">{current.participants}</p>
              </div>
              <button type="button" aria-label="Archive" className={ICON_BTN}><svg {...svg} className="size-5"><path d="M4 7h16v3H4zM5.500 10v8a1.500 1.500 0 0 0 1.500 1.500h10a1.500 1.500 0 0 0 1.500-1.500v-8M10 14h4" /></svg></button>
              <button type="button" aria-label="Snooze" className={ICON_BTN}><svg {...svg} className="size-5"><circle cx="12" cy="12" r="8.500" /><path d="M12 7.500V12l3 2" /></svg></button>
              <button type="button" aria-label="More actions" className={ICON_BTN}><svg viewBox="0 0 24 24" aria-hidden="true" className="size-5" fill="currentColor"><circle cx="5.500" cy="12" r="1.600" /><circle cx="12" cy="12" r="1.600" /><circle cx="18.500" cy="12" r="1.600" /></svg></button>
            </header>

            <div ref={body} data-reading-body className="min-h-0 flex-1 space-y-4 overflow-y-auto bg-neutral-50/60 p-4 sm:p-6">
              {current.messages.map((m, i) => (
                <article key={i} className={`max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed sm:max-w-[75%] ${m.outgoing ? "ms-auto rounded-ee-md bg-neutral-900 text-white" : "rounded-es-md border border-neutral-200 bg-neutral-100 text-neutral-800"}`}>
                  <p className={`mb-1 text-xs ${m.outgoing ? "text-neutral-300" : "text-neutral-500"}`}><span className="font-medium">{m.from}</span> · {m.time}</p>
                  <p className="whitespace-pre-line">{m.text}</p>
                </article>
              ))}
            </div>

            <form onSubmit={send} className="sticky bottom-0 flex items-end gap-2 border-t border-neutral-200 bg-white p-3 sm:p-4">
              <label className="min-w-0 flex-1">
                <span className="sr-only">Reply</span>
                <textarea rows={2} value={draft} onChange={(e) => setDraft(e.target.value)} placeholder="Write a reply…" className="block min-h-11 w-full resize-none rounded-xl border border-neutral-300 px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20" />
              </label>
              <button type="submit" className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-5 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Send</button>
            </form>
          </>
        ) : (
          <div className="grid flex-1 place-items-center p-8 text-center text-sm text-neutral-500">Select a conversation to read it.</div>
        )}
      </section>
    </div>
  );
}
