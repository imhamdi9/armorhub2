"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, tr } from "@/lib/i18n";
import {
  getEventCatalog,
  getWorkflows,
  resetWorkflow,
  saveWorkflow,
  type WorkflowInfo,
  getPlatformEvent,
  getPlatformEvents,
  getPlatformModules,
  replayPlatformEvent,
  setTenantModule,
  type PlatformEvent,
  type PlatformModule,
} from "@/services/admin.service";
import { getTenants } from "@/services/platform.service";
import type { Tenant } from "@/types/platform";

type Catalog = Awaited<ReturnType<typeof getEventCatalog>>;
const STATUSES = ["", "pending", "delivered", "failed", "dead", "skipped"];

/**
 * The platform core, as an operator sees it: the modules installed (ArmorHub is one of them) and which markets have
 * each switched on, every event type and its contract, and what happened to each event (with replay).
 */
export default function PlatformPage() {
  const [tab, setTab] = useState<"modules" | "workflows" | "catalog" | "log">("modules");
  const [forbidden, setForbidden] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // modules
  const [markets, setMarkets] = useState<Tenant[]>([]);
  const [market, setMarket] = useState<number | undefined>();
  const [modules, setModules] = useState<PlatformModule[] | null>(null);
  // catalog
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  // log
  const [events, setEvents] = useState<PlatformEvent[] | null>(null);
  const [name, setName] = useState("");
  const [status, setStatus] = useState("");
  const [open, setOpen] = useState<PlatformEvent | null>(null);
  const [tick, setTick] = useState(0);
  // workflows
  const [workflows, setWorkflows] = useState<WorkflowInfo[] | null>(null);
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [note, setNote] = useState<string | null>(null);

  useEffect(() => {
    getTenants().then((t) => { setMarkets(t); setMarket((m) => m ?? t[0]?.id); }).catch(() => setForbidden(true));
  }, []);

  useEffect(() => {
    getPlatformModules(market).then(setModules).catch(() => setForbidden(true));
  }, [market, tick]);

  useEffect(() => {
    if (tab === "workflows") getWorkflows(market).then(setWorkflows).catch(() => setForbidden(true));
  }, [tab, market, tick]);

  useEffect(() => {
    if (tab === "catalog") getEventCatalog().then(setCatalog).catch(() => setForbidden(true));
  }, [tab]);

  useEffect(() => {
    if (tab === "log") getPlatformEvents({ name, status }).then((r) => setEvents(r.data)).catch(() => setForbidden(true));
  }, [tab, name, status, tick]);

  async function toggle(m: PlatformModule) {
    if (!market) return;
    setError(null);
    try {
      await setTenantModule(market, m.id, !m.enabled);
      setTick((t) => t + 1);
    } catch (e) {
      setError(errorMessage(e));
    }
  }

  async function saveWf(w: WorkflowInfo) {
    if (!market) return;
    setError(null);
    setNote(null);
    try {
      await saveWorkflow(market, w.key, JSON.parse(draft[w.key] ?? JSON.stringify(w.active, null, 2)));
      setDraft((d) => { const next = { ...d }; delete next[w.key]; return next; });
      setNote(tr("Saved. This market now runs its own version."));
      setTick((t) => t + 1);
    } catch (e) {
      setError(e instanceof SyntaxError ? tr("That is not valid JSON.") : errorMessage(e));
    }
  }

  async function resetWf(w: WorkflowInfo) {
    if (!market) return;
    setError(null);
    await resetWorkflow(market, w.key);
    setDraft((d) => { const next = { ...d }; delete next[w.key]; return next; });
    setNote(tr("Back to the product's own workflow."));
    setTick((t) => t + 1);
  }

  async function show(e: PlatformEvent) {
    setOpen(await getPlatformEvent(e.event_id));
  }

  async function replay(e: PlatformEvent, subscriber?: string) {
    setError(null);
    try {
      await replayPlatformEvent(e.event_id, subscriber);
      setOpen(await getPlatformEvent(e.event_id));
      setTick((t) => t + 1);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;

  const tabButton = (key: typeof tab, label: string) => (
    <button key={key} role="tab" aria-selected={tab === key} type="button" onClick={() => setTab(key)}
      className={`rounded-full px-4 py-1.5 text-sm font-semibold ${tab === key ? "bg-lime text-forest" : "text-secondary-foreground"}`}>{tr(label)}</button>
  );

  return (
    <div className="max-w-5xl">
      <PageHeader title="Platform" />
      <p className="mb-4 text-sm text-muted-foreground">
        {tr("The platform core is business-neutral. Products such as ArmorHub plug in as modules that declare their own events and reactions; nothing in the core refers to them.")}
      </p>
      <div role="tablist" className="mb-6 inline-flex gap-1 rounded-full bg-secondary p-1">
        {tabButton("modules", "Modules")}
        {tabButton("workflows", "Workflows")}
        {tabButton("catalog", "Event catalog")}
        {tabButton("log", "Event log")}
      </div>
      {error && <p className="mb-4 text-sm text-red-500" role="alert">{error}</p>}

      {tab === "modules" && (
        <section>
          <div className="mb-4 flex items-center gap-2 text-sm">
            <label htmlFor="market">{tr("Market")}</label>
            <select id="market" className="rounded-lg border px-3 py-2" value={market ?? ""} onChange={(e) => setMarket(Number(e.target.value))}>
              {markets.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
          </div>
          {modules === null ? (
            <p className="text-muted-foreground">{tr("Loading...")}</p>
          ) : (
            <div className="space-y-3">
              {modules.map((m) => (
                <article key={m.id} className="rounded-xl border p-5">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                      <h2 className="text-lg font-bold">{m.name} <span className="text-sm font-normal text-muted-foreground">v{m.version}</span></h2>
                      <p className="text-sm text-muted-foreground">{m.description}</p>
                    </div>
                    {market && (
                      <label className="flex items-center gap-2 text-sm font-medium">
                        <input type="checkbox" aria-label={`${m.name} ${tr("enabled")}`} checked={!!m.enabled} onChange={() => toggle(m)} />
                        {m.enabled ? tr("On for this market") : tr("Off for this market")}
                      </label>
                    )}
                  </div>
                  <p className="mt-3 text-sm"><strong>{tr("Events")}:</strong> {m.events.length ? m.events.join(", ") : "—"}</p>
                  <p className="mt-1 text-sm"><strong>{tr("Reactions")}:</strong> {m.subscribers.length ? m.subscribers.join(", ") : "—"}</p>
                </article>
              ))}
            </div>
          )}
        </section>
      )}

      {tab === "workflows" && (
        <section>
          <div className="mb-4 flex items-center gap-2 text-sm">
            <label htmlFor="wf-market">{tr("Market")}</label>
            <select id="wf-market" className="rounded-lg border px-3 py-2" value={market ?? ""} onChange={(e) => setMarket(Number(e.target.value))}>
              {markets.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select>
          </div>
          {note && <p className="mb-3 text-sm text-green-600" role="status">{note}</p>}
          {workflows === null ? <p className="text-muted-foreground">{tr("Loading...")}</p> : workflows.map((w) => (
            <article key={w.key} className="mb-4 rounded-xl border p-5">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-bold"><code>{w.key}</code> <span className="text-sm font-normal text-muted-foreground">{w.owner}</span></h2>
                <Badge variant={w.customised ? "default" : "secondary"}>{w.customised ? `${tr("Customised")} v${w.version}` : tr("Product default")}</Badge>
              </div>
              <p className="mt-2 text-sm"><strong>{tr("States")}:</strong> {Object.entries(w.active.states).map(([k, s]) => `${k}${s.final ? " ✓" : ""}`).join(" → ")}</p>
              <ul className="mt-2 space-y-1 text-sm" dir="ltr">
                {Object.entries(w.active.transitions).map(([name, t]) => (
                  <li key={name}><code className="font-semibold">{name}</code>: {(t.from === "*" ? ["any"] : t.from).join(" | ")} → <strong>{t.to}</strong>{t.actions.length ? ` · actions: ${t.actions.join(", ")}` : ""}{t.guards.length ? ` · guards: ${t.guards.join(", ")}` : ""}</li>
                ))}
              </ul>
              <label htmlFor={`def-${w.key}`} className="mt-4 block text-sm font-medium">{tr("This market's version (JSON)")}</label>
              <textarea id={`def-${w.key}`} aria-label={tr("Workflow definition")} rows={10} dir="ltr" className="w-full rounded-lg border px-3 py-2 font-mono text-xs"
                value={draft[w.key] ?? JSON.stringify(w.active, null, 2)} onChange={(e) => setDraft((d) => ({ ...d, [w.key]: e.target.value }))} />
              <p className="text-xs text-muted-foreground">{tr("You can re-wire the steps between the existing states. States cannot be added or removed because existing records live in them.")}</p>
              <div className="mt-3 flex gap-2">
                <Button onClick={() => saveWf(w)} disabled={!market}>{tr("Save for this market")}</Button>
                <Button variant="outline" onClick={() => resetWf(w)} disabled={!market || !w.customised}>{tr("Revert to product default")}</Button>
              </div>
            </article>
          ))}
        </section>
      )}

      {tab === "catalog" && (
        <section>
          {catalog === null ? <p className="text-muted-foreground">{tr("Loading...")}</p> : (
            <div className="divide-y rounded-xl border text-sm">
              {catalog.map((c) => (
                <div key={c.name} className="grid gap-1 p-4 md:grid-cols-[14rem_1fr]">
                  <div><code className="font-semibold">{c.name}</code><p className="text-xs text-muted-foreground">v{c.version} · {c.owner}</p></div>
                  <div>
                    <p>{c.description}</p>
                    <p className="mt-1 font-mono text-xs text-muted-foreground" dir="ltr">{Object.entries(c.schema).map(([k, t]) => `${k}: ${t}`).join(" · ")}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {tab === "log" && (
        <section>
          <div className="mb-4 flex flex-wrap gap-2">
            <Input className="w-64" placeholder={tr("Event name, e.g. order.*")} aria-label={tr("Event name")} value={name} onChange={(e) => { setEvents(null); setName(e.target.value); }} />
            <select aria-label={tr("Delivery status")} className="rounded-lg border px-3 py-2" value={status} onChange={(e) => { setEvents(null); setStatus(e.target.value); }}>
              {STATUSES.map((s) => <option key={s} value={s}>{s ? tr(s) : tr("Any status")}</option>)}
            </select>
          </div>

          {events === null ? <p className="text-muted-foreground">{tr("Loading...")}</p> : events.length === 0 ? <EmptyState>{tr("No events yet.")}</EmptyState> : (
            <div className="divide-y rounded-xl border text-sm">
              {events.map((e) => (
                <button key={e.event_id} type="button" onClick={() => show(e)} className="flex w-full flex-wrap items-center justify-between gap-2 p-3 text-start hover:bg-muted">
                  <span><code className="font-semibold">{e.name}</code> <span className="text-muted-foreground">{e.aggregate_type ? `${e.aggregate_type} #${e.aggregate_id}` : ""}</span></span>
                  <span className="flex items-center gap-2">
                    {e.deliveries.map((d) => <Badge key={d.id} variant={d.status === "delivered" ? "default" : "secondary"}>{d.status}</Badge>)}
                    <span className="text-xs text-muted-foreground">{formatDateTime(e.occurred_at)}</span>
                  </span>
                </button>
              ))}
            </div>
          )}

          {open && (
            <div className="mt-4 rounded-xl border p-4 text-sm" role="dialog" aria-label={tr("Event details")}>
              <div className="flex items-center justify-between"><h3 className="font-bold">{open.name}</h3><button type="button" className="underline" onClick={() => setOpen(null)}>{tr("Close")}</button></div>
              <p className="text-xs text-muted-foreground" dir="ltr">{open.event_id} · {tr("correlation")} {open.correlation_id}</p>
              <pre className="mt-2 overflow-x-auto rounded-lg bg-muted p-3 text-xs" dir="ltr">{JSON.stringify(open.payload, null, 2)}</pre>
              <h4 className="mt-3 font-semibold">{tr("Deliveries")}</h4>
              <ul className="mt-1 space-y-2">
                {open.deliveries.map((d) => (
                  <li key={d.id} className="flex flex-wrap items-center justify-between gap-2">
                    <span><code>{d.subscriber}</code> · <Badge variant={d.status === "delivered" ? "default" : "secondary"}>{d.status}</Badge> · {tr("attempts")} {d.attempts}{d.last_error ? ` · ${d.last_error}` : ""}</span>
                    <Button size="sm" variant="outline" onClick={() => replay(open, d.subscriber)}>{tr("Replay")}</Button>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </section>
      )}
    </div>
  );
}
