"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { formatDateTime, tr } from "@/lib/i18n";
import { getAuditLogs } from "@/services/admin.service";
import type { AuditEntry } from "@/types/admin";
import type { Paginated } from "@/types/platform";

const QUICK = ["", "auth.", "settings.", "payments.", "user.", "security."];

/** Who did what, and which attempts failed. Entries cannot be edited or deleted. */
export default function AdminAuditPage() {
  const [result, setResult] = useState<Paginated<AuditEntry> | null>(null);
  const [action, setAction] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getAuditLogs({ page, ...(action ? { action } : {}), ...(search ? { search } : {}) })
      .then(setResult)
      .catch(() => setForbidden(true));
  }, [action, search, page]);

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;

  return (
    <div>
      <PageHeader title="Audit log" />

      <div className="mb-4 flex flex-wrap items-center gap-2">
        {QUICK.map((q) => (
          <Button key={q || "all"} size="sm" variant={action === q ? "default" : "outline"} onClick={() => { setAction(q); setPage(1); }}>
            {q === "" ? tr("All") : q.replace(/\.$/, "")}
          </Button>
        ))}
        <Input className="max-w-xs" placeholder={tr("Search details")} value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} />
      </div>

      {!result ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : result.data.length === 0 ? (
        <EmptyState>No matching entries.</EmptyState>
      ) : (
        <>
          <div className="min-w-0 divide-y rounded-lg border text-sm">
            {result.data.map((e) => (
              <div key={e.id} className="min-w-0 p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span><code className="rounded bg-muted px-1">{e.action}</code> {e.subject ?? ""}</span>
                  <span className="text-muted-foreground">{formatDateTime(e.created_at)}</span>
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                  {e.user ? `${e.user.name} (${e.user.email})` : tr("Anonymous or system")}
                  {e.ip ? ` · ${e.ip}` : ""}
                </div>
                {e.meta && <pre className="mt-1 max-w-full overflow-x-auto rounded bg-muted p-2 text-xs">{JSON.stringify(e.meta, null, 1)}</pre>}
              </div>
            ))}
          </div>

          {result.last_page > 1 && (
            <div className="mt-4 flex items-center justify-center gap-3">
              <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>{tr("Previous")}</Button>
              <span className="text-sm">{tr("Page {page} of {total}", { page: result.current_page, total: result.last_page })}</span>
              <Button variant="outline" size="sm" disabled={page >= result.last_page} onClick={() => setPage((p) => p + 1)}>{tr("Next")}</Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
