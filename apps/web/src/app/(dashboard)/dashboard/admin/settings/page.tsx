"use client";

import { useEffect, useMemo, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getSettings, resetSetting, saveSettings } from "@/services/admin.service";
import type { SettingRow } from "@/types/admin";

const GROUPS: Record<string, string> = { platform: "Platform", security: "Security" };

/**
 * Edits every runtime setting from the schema the API describes: adding a setting in the backend
 * adds it here automatically. Payments have their own page.
 */
export default function AdminSettingsPage() {
  const [rows, setRows] = useState<SettingRow[] | null>(null);
  const [draft, setDraft] = useState<Record<string, string | number | boolean>>({});
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getSettings().then(setRows).catch(() => setForbidden(true));
  }, []);

  const groups = useMemo(() => {
    const out: Record<string, SettingRow[]> = {};
    for (const r of rows ?? []) {
      if (r.group in GROUPS) (out[r.group] ??= []).push(r);
    }
    return out;
  }, [rows]);

  const current = (r: SettingRow) => (r.key in draft ? draft[r.key] : (r.value ?? ""));
  const set = (key: string, value: string | number | boolean) => {
    setMessage(null);
    setDraft((d) => ({ ...d, [key]: value }));
  };

  async function save(group: string) {
    const keys = (groups[group] ?? []).map((r) => r.key).filter((k) => k in draft);
    if (keys.length === 0) return;
    try {
      const next = await saveSettings(Object.fromEntries(keys.map((k) => [k, draft[k]])));
      setRows(next);
      setDraft((d) => Object.fromEntries(Object.entries(d).filter(([k]) => !keys.includes(k))));
      setMessage({ ok: true, text: tr("Saved") });
    } catch (e) {
      setMessage({ ok: false, text: errorMessage(e) });
    }
  }

  async function reset(key: string) {
    try {
      setRows(await resetSetting(key));
      setDraft((d) => Object.fromEntries(Object.entries(d).filter(([k]) => k !== key)));
      setMessage({ ok: true, text: tr("Reset to default") });
    } catch (e) {
      setMessage({ ok: false, text: errorMessage(e) });
    }
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!rows) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-3xl">
      <PageHeader title="Settings" />
      <p className="mb-6 text-sm text-muted-foreground">{tr("Changes apply immediately, with no redeploy. Every change is recorded in the audit log.")}</p>

      {message && <p className={`mb-4 text-sm ${message.ok ? "text-green-600" : "text-red-500"}`}>{message.text}</p>}

      {Object.entries(groups).map(([group, list]) => (
        <section key={group} className="mb-10">
          <h2 className="mb-3 text-xl font-semibold">{tr(GROUPS[group])}</h2>
          <div className="space-y-4 rounded-lg border p-4">
            {list.map((r) => (
              <div key={r.key} className="grid gap-1 md:grid-cols-[1fr_16rem] md:items-start md:gap-4">
                <div>
                  <label htmlFor={r.key} className="font-medium">{tr(r.label)}</label>
                  {r.help && <p className="text-xs text-muted-foreground">{tr(r.help)}</p>}
                  {!r.is_default && r.type !== "secret" && (
                    <button type="button" className="mt-1 text-xs underline" onClick={() => reset(r.key)}>{tr("Reset to default")}</button>
                  )}
                </div>

                <div>
                  {r.type === "bool" ? (
                    <label className="flex items-center gap-2">
                      <input id={r.key} type="checkbox" checked={Boolean(current(r))} onChange={(e) => set(r.key, e.target.checked)} />
                      {Boolean(current(r)) ? tr("On") : tr("Off")}
                    </label>
                  ) : r.type === "select" ? (
                    <select id={r.key} className="w-full rounded-lg border px-3 py-2" value={String(current(r))} onChange={(e) => set(r.key, e.target.value)}>
                      {Object.entries(r.options).map(([value, label]) => (
                        <option key={value} value={value}>{tr(label)}</option>
                      ))}
                    </select>
                  ) : r.type === "text" ? (
                    <textarea id={r.key} className="w-full rounded-lg border px-3 py-2" rows={3} value={String(current(r))} onChange={(e) => set(r.key, e.target.value)} />
                  ) : r.type === "secret" ? (
                    <div className="space-y-1">
                      <Input id={r.key} type="password" autoComplete="new-password" placeholder={r.has_value ? "••••••••" : ""} value={String(draft[r.key] ?? "")} onChange={(e) => set(r.key, e.target.value)} />
                      <div className="flex items-center gap-2 text-xs">
                        <Badge variant={r.has_value ? "default" : "outline"}>{r.has_value ? tr("Set") : tr("Not set")}</Badge>
                        {r.has_value && <button type="button" className="underline" onClick={() => reset(r.key)}>{tr("Remove")}</button>}
                      </div>
                    </div>
                  ) : (
                    <Input id={r.key} type={r.type === "int" || r.type === "float" ? "number" : "text"} step={r.type === "float" ? "any" : undefined} value={String(current(r))} onChange={(e) => set(r.key, e.target.value)} />
                  )}
                </div>
              </div>
            ))}
            <Button onClick={() => save(group)} disabled={!list.some((r) => r.key in draft)}>{tr("Save changes")}</Button>
          </div>
        </section>
      ))}
    </div>
  );
}
