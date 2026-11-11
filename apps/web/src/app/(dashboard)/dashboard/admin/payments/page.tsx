"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import {
  activateProvider,
  getAdminPayments,
  getProviders,
  saveProvider,
  testProvider,
} from "@/services/admin.service";
import type { AdminPayment, PaymentProviderInfo, ProviderField } from "@/types/admin";

type Draft = Record<string, string | boolean>;

/**
 * Payment providers are drivers an admin configures and switches here at any time. Credentials are stored
 * encrypted and are never shown again, only whether they are set.
 */
export default function AdminPaymentsPage() {
  const [providers, setProviders] = useState<PaymentProviderInfo[] | null>(null);
  const [payments, setPayments] = useState<AdminPayment[]>([]);
  const [drafts, setDrafts] = useState<Record<string, Draft>>({});
  const [open, setOpen] = useState<string | null>(null);
  const [notes, setNotes] = useState<Record<string, { ok: boolean; text: string }>>({});
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getProviders().then((r) => setProviders(r.providers)).catch(() => setForbidden(true));
    getAdminPayments().then((r) => setPayments(r.data)).catch(() => undefined);
  }, []);

  const note = (key: string, ok: boolean, text: string) => setNotes((n) => ({ ...n, [key]: { ok, text } }));
  const value = (p: PaymentProviderInfo, f: ProviderField) =>
    drafts[p.key]?.[f.key] ?? (f.type === "bool" ? Boolean(f.value) : String(f.value ?? ""));
  const edit = (p: PaymentProviderInfo, f: ProviderField, v: string | boolean) =>
    setDrafts((d) => ({ ...d, [p.key]: { ...d[p.key], [f.key]: v } }));

  async function save(p: PaymentProviderInfo) {
    try {
      const sent = drafts[p.key] ?? {};
      setProviders((await saveProvider(p.key, sent)).providers);
      setDrafts((d) => ({ ...d, [p.key]: {} }));
      note(p.key, true, tr("Saved"));
    } catch (e) {
      note(p.key, false, errorMessage(e));
    }
  }

  async function activate(p: PaymentProviderInfo) {
    try {
      setProviders((await activateProvider(p.key)).providers);
      note(p.key, true, tr("This provider is now active for new payments."));
    } catch (e) {
      note(p.key, false, errorMessage(e));
    }
  }

  async function test(p: PaymentProviderInfo) {
    const r = await testProvider(p.key).catch((e) => ({ ok: false, message: errorMessage(e) }));
    note(p.key, r.ok, tr(r.message));
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!providers) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Payments" />
      <p className="mb-6 text-sm text-muted-foreground">
        {tr("Choose which provider takes payments and enter its keys. You can switch at any time; payments already started with another provider still complete.")}
      </p>

      <div className="space-y-4">
        {providers.map((p) => (
          <section key={p.key} className="rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <h2 className="text-lg font-semibold">{tr(p.label)}</h2>
                {p.active && <Badge>{tr("Active")}</Badge>}
                <Badge variant={p.configured ? "secondary" : "outline"}>{p.configured ? tr("Configured") : tr("Not configured")}</Badge>
              </div>
              <div className="flex gap-2">
                <Button size="sm" variant="outline" onClick={() => setOpen(open === p.key ? null : p.key)}>
                  {open === p.key ? tr("Hide settings") : tr("Settings")}
                </Button>
                <Button size="sm" variant="outline" onClick={() => test(p)}>{tr("Test")}</Button>
                <Button size="sm" disabled={p.active || !p.configured} onClick={() => activate(p)}>{tr("Make active")}</Button>
              </div>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">{tr(p.help)}</p>
            {notes[p.key] && <p className={`mt-2 text-sm ${notes[p.key].ok ? "text-green-600" : "text-red-500"}`}>{notes[p.key].text}</p>}

            {open === p.key && (
              <div className="mt-4 space-y-3 border-t pt-4">
                {p.key !== "simulated" && (
                  <div className="rounded-md bg-muted p-3 text-sm">
                    <p className="font-medium">{tr("Webhook URL")}</p>
                    <code className="break-all">{p.webhook_url}</code>{" "}
                    <button type="button" className="underline" onClick={() => navigator.clipboard?.writeText(p.webhook_url)}>{tr("Copy")}</button>
                  </div>
                )}

                {p.fields.map((f) => (
                  <div key={f.key} className="grid gap-1 md:grid-cols-[1fr_20rem] md:gap-4">
                    <div>
                      <label htmlFor={`${p.key}-${f.key}`} className="text-sm font-medium">
                        {tr(f.label)} {f.required && <span className="text-red-500">*</span>}
                      </label>
                      {f.help && <p className="text-xs text-muted-foreground">{tr(f.help)}</p>}
                    </div>
                    <div>
                      {f.type === "bool" ? (
                        <input id={`${p.key}-${f.key}`} type="checkbox" checked={Boolean(value(p, f))} onChange={(e) => edit(p, f, e.target.checked)} />
                      ) : f.type === "select" ? (
                        <select id={`${p.key}-${f.key}`} className="w-full rounded-lg border px-3 py-2" value={String(value(p, f))} onChange={(e) => edit(p, f, e.target.value)}>
                          {Object.entries(f.options).map(([v, label]) => <option key={v} value={v}>{tr(label)}</option>)}
                        </select>
                      ) : f.type === "text" ? (
                        <textarea id={`${p.key}-${f.key}`} className="w-full rounded-lg border px-3 py-2 font-mono text-xs" rows={5} value={String(value(p, f))} onChange={(e) => edit(p, f, e.target.value)} />
                      ) : f.type === "secret" ? (
                        <div>
                          <Input id={`${p.key}-${f.key}`} type="password" autoComplete="new-password" placeholder={f.has_value ? tr("•••••••• (saved; leave blank to keep)") : ""} value={String(drafts[p.key]?.[f.key] ?? "")} onChange={(e) => edit(p, f, e.target.value)} />
                          <Badge className="mt-1" variant={f.has_value ? "default" : "outline"}>{f.has_value ? tr("Set") : tr("Not set")}</Badge>
                        </div>
                      ) : (
                        <Input id={`${p.key}-${f.key}`} value={String(value(p, f))} onChange={(e) => edit(p, f, e.target.value)} />
                      )}
                    </div>
                  </div>
                ))}
                {p.fields.length > 0 && <Button onClick={() => save(p)}>{tr("Save settings")}</Button>}
              </div>
            )}
          </section>
        ))}
      </div>

      <h2 className="mb-3 mt-10 text-xl font-semibold">{tr("Recent payments")}</h2>
      {payments.length === 0 ? (
        <EmptyState>No payments yet.</EmptyState>
      ) : (
        <div className="divide-y rounded-lg border text-sm">
          {payments.map((x) => (
            <div key={x.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
              <span>{tr("Order #{id}", { id: x.order_id })} · {formatMoney(x.amount, x.currency)}</span>
              <span className="flex items-center gap-2 text-muted-foreground">
                {x.provider ?? "—"} · {formatDateTime(x.created_at)}
                <Badge variant={x.status === "completed" ? "default" : "outline"}>{x.status}</Badge>
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
