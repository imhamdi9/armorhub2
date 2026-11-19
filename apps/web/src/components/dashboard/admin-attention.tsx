"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { formatNumber, tr } from "@/lib/i18n";
import { getOverview } from "@/services/admin.service";
import type { Overview } from "@/types/admin";

const TONE: Record<string, string> = {
  fail: "border-red-300 bg-red-50",
  warn: "border-amber-300 bg-amber-50",
  info: "border-blue-200 bg-blue-50",
};

/** The first thing an administrator sees: what needs a decision or a fix today, each row one click from the place to do it. */
export function AdminAttention() {
  const [data, setData] = useState<Overview | null>(null);
  useEffect(() => { getOverview().then(setData).catch(() => setData(null)); }, []);
  if (!data) return null;

  // The most urgent first (failures, then warnings, then information).
  const order = { fail: 0, warn: 1, info: 2 } as const;
  const rows = [...data.attention].sort((a, b) => order[a.severity] - order[b.severity]);

  return (
    <section className="rounded-2xl border bg-white p-5 shadow-card" aria-labelledby="attention-title" data-testid="admin-attention">
      <div className="mb-3 flex items-center justify-between">
        <h3 id="attention-title" className="font-display text-xl font-semibold">{tr("Needs attention")}</h3>
        <Link href="/dashboard/admin" className="text-sm font-semibold underline">{tr("Full overview")}</Link>
      </div>
      {rows.length === 0 ? (
        <p className="rounded-lg border border-green-300 bg-green-50 p-3 text-sm">{tr("All clear. Nothing needs your attention right now.")}</p>
      ) : (
        <div className="space-y-2">
          {rows.map((a) => (
            <Link key={a.key} href={a.path} className={`flex items-center justify-between rounded-lg border p-3 text-sm hover:opacity-80 ${TONE[a.severity]}`}>
              <span>{tr(a.label)}</span>
              <strong>{formatNumber(a.count)}</strong>
            </Link>
          ))}
        </div>
      )}
    </section>
  );
}
