"use client";

import { useMemo, useRef, useState, useEffect } from "react";

const focus = "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900 focus-visible:ring-offset-2";
const ROLES = ["Owner", "Admin", "Editor", "Viewer"] as const;
type Role = (typeof ROLES)[number];
type Status = "Active" | "Invited" | "Suspended";
export interface Member { id: string; name: string; email: string; role: Role; lastActive: string; status: Status }

const STATUS_STYLE: Record<Status, string> = { Active: "bg-emerald-50 text-emerald-700 ring-emerald-200", Invited: "bg-amber-50 text-amber-700 ring-amber-200", Suspended: "bg-rose-50 text-rose-700 ring-rose-200" };
const TONES = ["bg-amber-200 text-amber-900", "bg-sky-200 text-sky-900", "bg-rose-200 text-rose-900", "bg-emerald-200 text-emerald-900", "bg-violet-200 text-violet-900", "bg-orange-200 text-orange-900"];
const initials = (n: string) => n.split(/\s+/).map((w) => w[0]).slice(0, 2).join("").toUpperCase();

/** A team administration table with row selection, a bulk-action bar and a guard that keeps the workspace from losing its last Owner. */
export function TeamAdminPanel({ initial, licensedSeats }: { initial: Member[]; licensedSeats: number }) {
  const [members, setMembers] = useState<Member[]>(initial);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [query, setQuery] = useState("");
  const [note, setNote] = useState<string | null>(null);
  const header = useRef<HTMLInputElement>(null);

  const visible = useMemo(() => {
    const q = query.trim().toLowerCase();

    return members.filter((m) => !q || `${m.name} ${m.email}`.toLowerCase().includes(q));
  }, [members, query]);

  const seatsUsed = members.filter((m) => m.status !== "Suspended").length;
  const allSelected = visible.length > 0 && visible.every((m) => selected.has(m.id));
  const someSelected = visible.some((m) => selected.has(m.id));

  // A header checkbox that is neither checked nor unchecked while only some rows are picked.
  useEffect(() => { if (header.current) header.current.indeterminate = someSelected && !allSelected; }, [someSelected, allSelected]);

  const toggleAll = (on: boolean) => setSelected((s) => { const n = new Set(s); visible.forEach((m) => (on ? n.add(m.id) : n.delete(m.id))); return n; });
  const toggleOne = (id: string) => setSelected((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n; });

  function changeRole(member: Member, role: Role) {
    const owners = members.filter((m) => m.role === "Owner").length;
    if (member.role === "Owner" && role !== "Owner" && owners === 1) {
      // Refuse the change rather than leave the workspace with nobody who can manage it.
      setNote(`${member.name} is the last Owner. Make someone else an Owner first, then change this role.`);

      return;
    }
    setNote(null);
    setMembers((all) => all.map((m) => (m.id === member.id ? { ...m, role } : m)));
  }

  const selectedCount = [...selected].filter((id) => members.some((m) => m.id === id)).length;

  return (
    <section aria-labelledby="team-title" className="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6">
      <header className="flex flex-wrap items-center gap-x-6 gap-y-3">
        <div className="me-auto">
          <h1 id="team-title" className="text-xl font-semibold tracking-tight text-neutral-900">Team</h1>
          <p data-seat-counter className="mt-0.5 text-sm text-neutral-500" aria-live="polite">{seatsUsed} of {licensedSeats} seats in use</p>
        </div>
        <label className="relative block w-full sm:w-64">
          <span className="sr-only">Search members</span>
          <svg aria-hidden="true" viewBox="0 0 24 24" className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round"><circle cx="11" cy="11" r="6.5" /><path d="m20 20-4-4" /></svg>
          <input type="search" value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search people" className="min-h-11 w-full rounded-xl border border-neutral-300 bg-white ps-9 pe-3 text-sm placeholder:text-neutral-400 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20" />
        </label>
        <button type="button" className={`inline-flex min-h-11 items-center rounded-xl bg-neutral-900 px-4 text-sm font-semibold text-white transition-colors hover:bg-neutral-800 ${focus}`}>Invite people</button>
      </header>

      {note && <p role="alert" data-owner-note className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{note}</p>}

      {selectedCount > 0 && (
        <div data-bulk-bar role="region" aria-label="Bulk actions" className="mt-4 flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-2">
          <p data-bulk-count className="me-auto text-sm font-medium text-neutral-900" aria-live="polite">{selectedCount} selected</p>
          <button type="button" className={`inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-100 ${focus}`}>Resend invite</button>
          <button type="button" className={`inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-rose-700 hover:bg-rose-50 ${focus}`}>Remove</button>
        </div>
      )}

      <div className="relative mt-4 overflow-x-auto rounded-2xl border border-neutral-200 bg-white">
        <table className="w-full min-w-[40rem] text-start text-sm">
          <thead className="border-b border-neutral-200 bg-neutral-50 text-xs uppercase tracking-wide text-neutral-500">
            <tr>
              <th scope="col" className="w-12 px-4 py-3"><input ref={header} type="checkbox" aria-label="Select all members" checked={allSelected} onChange={(e) => toggleAll(e.target.checked)} className="size-4 rounded border-neutral-300 accent-neutral-900" /></th>
              <th scope="col" className="px-2 py-3 text-start font-medium">Member</th>
              <th scope="col" className="px-2 py-3 text-start font-medium">Role</th>
              <th scope="col" className="px-2 py-3 text-start font-medium">Last active</th>
              <th scope="col" className="px-2 py-3 text-start font-medium">Status</th>
              <th scope="col" className="w-12 px-2 py-3"><span className="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-neutral-100">
            {visible.length === 0 && <tr><td colSpan={6} className="p-8 text-center text-neutral-500">No one matches that search.</td></tr>}
            {visible.map((m, i) => (
              <tr key={m.id} data-member={m.id} className={selected.has(m.id) ? "bg-neutral-50" : ""}>
                <td className="px-4 py-3"><input type="checkbox" aria-label={`Select ${m.name}`} checked={selected.has(m.id)} onChange={() => toggleOne(m.id)} className="size-4 rounded border-neutral-300 accent-neutral-900" /></td>
                <td className="px-2 py-3">
                  <div className="flex items-center gap-3">
                    <span aria-hidden="true" className={`grid size-9 shrink-0 place-items-center rounded-full text-xs font-semibold ${TONES[i % TONES.length]}`}>{initials(m.name)}</span>
                    <div className="min-w-0"><p className="truncate font-medium text-neutral-900">{m.name}</p><p className="truncate text-xs text-neutral-500" dir="ltr">{m.email}</p></div>
                  </div>
                </td>
                <td className="px-2 py-3">
                  <select aria-label={`Role for ${m.name}`} value={m.role} onChange={(e) => changeRole(m, e.target.value as Role)} className="min-h-11 rounded-lg border border-neutral-300 bg-white px-2 text-sm text-neutral-800 sm:min-h-9 focus-visible:border-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-900/20">
                    {ROLES.map((r) => <option key={r}>{r}</option>)}
                  </select>
                </td>
                <td className="px-2 py-3 text-neutral-500">{m.lastActive}</td>
                <td className="px-2 py-3"><span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${STATUS_STYLE[m.status]}`}>{m.status}</span></td>
                <td className="px-2 py-3">
                  <button type="button" aria-label={`Actions for ${m.name}`} className={`grid size-11 place-items-center rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 ${focus}`}>
                    <svg viewBox="0 0 24 24" aria-hidden="true" className="size-5" fill="currentColor"><circle cx="5.5" cy="12" r="1.6" /><circle cx="12" cy="12" r="1.6" /><circle cx="18.5" cy="12" r="1.6" /></svg>
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
