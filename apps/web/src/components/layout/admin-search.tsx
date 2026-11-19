"use client";

import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import { tr } from "@/lib/i18n";
import { hasRole } from "@/lib/navigation";
import { searchAdmin, type SearchGroup } from "@/services/admin.service";

/** One box for an order number, a person, a company or a product. Press "/" anywhere to start typing. Platform administrators only. */
export function AdminSearch() {
  const user = useCurrentUser();
  const router = useRouter();
  const input = useRef<HTMLInputElement>(null);
  const [q, setQ] = useState("");
  const [result, setResult] = useState<{ q: string; groups: SearchGroup[] } | null>(null);
  const text = q.trim();
  // Results only count while they answer what is typed now: shorter or newer text shows "Searching…" until its own answer arrives.
  const groups = result && result.q === text ? result.groups : null;
  const [open, setOpen] = useState(false);
  const isAdmin = hasRole(user, "platform-admin") && !user?.impersonated_by;

  useEffect(() => {
    if (!isAdmin) return;
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      if (e.key === "/" && !/^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName) && !target.isContentEditable) { e.preventDefault(); input.current?.focus(); }
    };
    window.addEventListener("keydown", onKey);

    return () => window.removeEventListener("keydown", onKey);
  }, [isAdmin]);

  useEffect(() => {
    if (!isAdmin || text.length < 2) return;
    let live = true;
    const id = setTimeout(() => { searchAdmin(text).then((r) => { if (live) setResult({ q: text, groups: r.groups }); }).catch(() => { if (live) setResult({ q: text, groups: [] }); }); }, 250);

    return () => { live = false; clearTimeout(id); };
  }, [text, isAdmin]);

  if (!isAdmin) return null;

  const go = (path: string) => { setOpen(false); setQ(""); setResult(null); router.push(path); };

  return (
    <div className="relative hidden w-64 md:block lg:w-80" role="search">
      <input
        ref={input}
        value={q}
        onChange={(e) => { setQ(e.target.value); setOpen(true); }}
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        onKeyDown={(e) => { if (e.key === "Escape") { setOpen(false); input.current?.blur(); } }}
        placeholder={tr("Search orders, people, products…  ( / )")}
        aria-label={tr("Search the platform")}
        className="h-9 w-full rounded-full border border-border bg-white px-4 text-sm outline-none focus:border-obsidian"
      />
      {open && q.trim().length >= 2 && (
        <div className="absolute start-0 top-11 z-40 max-h-[70vh] w-full overflow-auto rounded-2xl border border-border bg-white p-2 shadow-card" data-testid="admin-search-results">
          {groups === null ? (
            <p className="p-3 text-sm text-muted-foreground">{tr("Searching…")}</p>
          ) : groups.length === 0 ? (
            <p className="p-3 text-sm text-muted-foreground">{tr("Nothing found.")}</p>
          ) : (
            groups.map((g) => (
              <div key={g.key} className="mb-1">
                <p className="px-3 pt-2 text-xs font-semibold text-muted-foreground">{tr(g.label)}</p>
                {g.items.map((i) => (
                  <button key={`${g.key}-${i.id}`} type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => go(i.path)} className="block w-full rounded-lg px-3 py-2 text-start hover:bg-fog">
                    <span className="block text-sm font-medium">{i.title}</span>
                    <span className="block truncate text-xs text-muted-foreground">{i.detail}</span>
                  </button>
                ))}
              </div>
            ))
          )}
        </div>
      )}
    </div>
  );
}
