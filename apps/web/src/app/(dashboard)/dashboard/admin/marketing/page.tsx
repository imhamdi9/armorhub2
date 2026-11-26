"use client";

import { Suspense, useCallback, useEffect, useMemo, useState } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";

import { MarketScopeNotice } from "@/components/layout/market-scope";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LoadingBlock } from "@/components/ui/loading-block";
import { errorMessage } from "@/lib/errors";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import {
  syncAdSpend, getAdLogs, getAttribution, getMarketingMarkets, getMarketingSettings, resetMarketingSetting, saveMarketingSettings, testAdConnections,
  type AdLog, type Attribution,
} from "@/services/admin.service";
import { getOpenStores } from "@/services/auth.service";
import type { SettingRow } from "@/types/admin";

type Tab = "connections" | "results" | "events" | "links" | "feeds";
const TABS: [Tab, string][] = [["connections", "Connections"], ["results", "Results by campaign"], ["events", "Sent to the platforms"], ["links", "Campaign links"], ["feeds", "Product feeds"]];

const PLATFORMS: { key: string; name: string; keys: string[]; live: string[]; test?: string; account?: string[] }[] = [
  { key: "meta", name: "Meta (Facebook and Instagram)", keys: ["meta_pixel_id", "meta_access_token", "meta_test_code", "meta_ad_account_id", "meta_ads_token"], live: ["meta_pixel_id", "meta_access_token"], test: "meta_test_code", account: ["meta_ad_account_id", "meta_ads_token"] },
  { key: "tiktok", name: "TikTok", keys: ["tiktok_pixel_id", "tiktok_access_token", "tiktok_test_code", "tiktok_advertiser_id", "tiktok_ads_token"], live: ["tiktok_pixel_id", "tiktok_access_token"], test: "tiktok_test_code", account: ["tiktok_advertiser_id", "tiktok_ads_token"] },
  { key: "google", name: "Google (Analytics, Ads and YouTube)", keys: ["ga4_id", "ga4_api_secret", "google_ads_id", "google_ads_purchase_label", "google_ads_lead_label"], live: ["ga4_id", "ga4_api_secret"] },
];

function Connections({ rows, setRows }: { rows: SettingRow[]; setRows: (r: SettingRow[]) => void }) {
  const [draft, setDraft] = useState<Record<string, string>>({});
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [tests, setTests] = useState<{ platform: string; status: string; http_status: number | null; note: string | null }[] | null>(null);
  const [busy, setBusy] = useState(false);
  const [spendRes, setSpendRes] = useState<{ platform: string; account: string; status: string; rows: number; note: string | null }[] | null>(null);
  const by = useMemo(() => Object.fromEntries(rows.map((r) => [r.key.replace("marketing.", ""), r])), [rows]);
  const value = (k: string) => (k in draft ? draft[k] : String(by[k]?.value ?? ""));
  const filled = (k: string) => (by[k]?.type === "secret" ? !!by[k]?.has_value || !!draft[k] : !!value(k));

  async function save() {
    setMsg(null);
    try {
      setRows(await saveMarketingSettings(Object.fromEntries(Object.entries(draft).map(([k, v]) => [`marketing.${k}`, v]))));
      setDraft({});
      setMsg({ ok: true, text: tr("Saved") });
    } catch (e) { setMsg({ ok: false, text: errorMessage(e) }); }
  }
  async function clear(k: string) {
    try { setRows(await resetMarketingSetting(`marketing.${k}`)); setDraft((d) => { const n = { ...d }; delete n[k]; return n; }); } catch (e) { setMsg({ ok: false, text: errorMessage(e) }); }
  }
  async function readSpend() {
    setBusy(true); setSpendRes(null);
    try { setSpendRes(await syncAdSpend()); } catch (e) { setMsg({ ok: false, text: errorMessage(e) }); } finally { setBusy(false); }
  }
  async function test() {
    setBusy(true); setTests(null);
    try { setTests(await testAdConnections()); } catch (e) { setMsg({ ok: false, text: errorMessage(e) }); } finally { setBusy(false); }
  }

  return (
    <div className="space-y-5">
      <p className="text-sm text-muted-foreground">{tr("Each platform is on when its ID and its access token are both set. The ID loads the pixel in the browser (only for visitors who accepted marketing cookies); the token lets the server report purchases even when the browser blocks the pixel.")}</p>
      {msg && <p role="status" className={`text-sm ${msg.ok ? "text-green-600" : "text-red-500"}`}>{msg.text}</p>}
      {PLATFORMS.map((p) => {
        const on = p.live.every(filled);
        const testing = !!p.test && filled(p.test);
        return (
          <section key={p.key} className="rounded-xl border p-4" data-testid={`platform-${p.key}`}>
            <div className="mb-3 flex items-center justify-between gap-2">
              <h2 className="text-lg font-semibold">{tr(p.name)}</h2>
              <span className="flex items-center gap-2">
                {p.account && <Badge variant={p.account.every(filled) ? "default" : "outline"} data-testid={`account-${p.key}`}>{p.account.every(filled) ? tr("Ad account connected") : tr("No ad account")}</Badge>}
                <Badge variant={on ? "default" : "outline"}>{on ? (testing ? tr("Test mode") : tr("Live")) : tr("Off")}</Badge>
              </span>
            </div>
            <div className="space-y-3">
              {p.keys.map((k) => {
                const r = by[k];
                if (!r) return null;
                return (
                  <div key={k} className="grid gap-1 md:grid-cols-[1fr_18rem] md:gap-4">
                    <div><label htmlFor={`m-${k}`} className="text-sm font-medium">{tr(r.label)}</label>{r.help && <p className="text-xs text-muted-foreground">{tr(r.help)}</p>}</div>
                    <div className="space-y-1">
                      <Input id={`m-${k}`} dir="ltr" type={r.type === "secret" ? "password" : "text"} autoComplete="off" placeholder={r.type === "secret" && r.has_value ? "••••••••" : ""}
                        value={r.type === "secret" ? (draft[k] ?? "") : value(k)} onChange={(e) => setDraft((d) => ({ ...d, [k]: e.target.value }))} />
                      {r.type === "secret" && <div className="flex items-center gap-2 text-xs"><Badge variant={r.has_value ? "default" : "outline"}>{r.has_value ? tr("Set") : tr("Not set")}</Badge>{r.has_value && <button type="button" className="underline" onClick={() => clear(k)}>{tr("Remove")}</button>}</div>}
                    </div>
                  </div>
                );
              })}
            </div>
          </section>
        );
      })}
      {by.daily_spend_alert && (
        <section className="rounded-xl border p-4" data-testid="spend-limit">
          <div className="grid gap-1 md:grid-cols-[1fr_18rem] md:gap-4">
            <div><label htmlFor="m-daily_spend_alert" className="text-sm font-medium">{tr(by.daily_spend_alert.label)}</label><p className="text-xs text-muted-foreground">{tr(by.daily_spend_alert.help)}</p></div>
            <Input id="m-daily_spend_alert" dir="ltr" type="number" min={0} value={value("daily_spend_alert")} onChange={(e) => setDraft((d) => ({ ...d, daily_spend_alert: e.target.value }))} />
          </div>
        </section>
      )}
      <div className="flex flex-wrap items-center gap-2">
        <Button onClick={save} disabled={Object.keys(draft).length === 0}>{tr("Save changes")}</Button>
        <Button variant="outline" onClick={test} disabled={busy}>{tr("Send a test purchase")}</Button>
        <Button variant="outline" onClick={readSpend} disabled={busy}>{tr("Read ad spend now")}</Button>
      </div>
      {spendRes && (
        <div className="rounded-xl border p-3 text-sm" role="status" data-testid="spend-results">
          {spendRes.length === 0 ? tr("No ad account is connected yet.") : spendRes.map((t) => (
            <p key={`${t.platform}${t.account}`}><strong>{t.platform}</strong> {t.account}: {t.status === "ok" ? `${tr("read")} (${t.rows})` : tr("failed")} {t.note ?? ""}</p>
          ))}
        </div>
      )}
      {tests && (
        <div className="rounded-xl border p-3 text-sm" role="status" data-testid="test-results">
          {tests.length === 0 ? tr("No platform is switched on for this market yet.") : tests.map((t) => (
            <p key={t.platform}><strong>{t.platform}</strong>: {t.status === "sent" ? tr("received") : tr("failed")} {t.http_status ? `(${t.http_status})` : ""} {t.note ?? ""}</p>
          ))}
        </div>
      )}
    </div>
  );
}

function Results() {
  const [days, setDays] = useState(30);
  const [data, setData] = useState<Attribution | null>(null);
  useEffect(() => { getAttribution(days).then(setData).catch(() => setData(null)); }, [days]);
  if (!data) return <LoadingBlock />;
  const t = data.totals;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        {[7, 30, 90].map((d) => <Button key={d} size="sm" variant={days === d ? "default" : "outline"} onClick={() => setDays(d)}>{d} {tr("days")}</Button>)}
      </div>
      <dl className="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
        {([["Orders", t.orders], ["Orders from a known campaign", t.tracked_orders], ["Seller and centre applications", t.applications], ["Ad spend", t.spend]] as const).map(([l, v]) => (
          <div key={l} className="rounded-lg bg-muted p-3"><dt className="text-xs text-muted-foreground">{tr(l)}</dt><dd className="text-xl font-bold">{v}</dd></div>
        ))}
      </dl>
      {data.rows.length === 0 ? <EmptyState>{tr("No orders or applications yet in this period.")}</EmptyState> : (
        <div className="overflow-x-auto rounded-xl border">
          <table className="w-full text-sm" data-testid="attribution-table">
            <thead className="bg-muted text-start"><tr>{["Source", "Campaign", "Orders", "Revenue", "Ad spend", "Cost per order", "Return on spend", "Seller applications", "Centre applications", "Cost per application"].map((h) => <th key={h} className="px-3 py-2 text-start font-medium">{tr(h)}</th>)}</tr></thead>
            <tbody>{data.rows.map((r) => (
              <tr key={`${r.source}|${r.campaign}`} className="border-t"><td className="px-3 py-2 font-medium">{r.source}</td><td className="px-3 py-2">{r.campaign}</td><td className="px-3 py-2">{r.orders}</td><td className="px-3 py-2">{r.revenue ? formatMoney(r.revenue, r.currency ?? "") : "-"}</td><td className="px-3 py-2">{r.spend ? formatMoney(r.spend, r.spend_currency ?? r.currency ?? "") : "-"}{r.shared_account && r.spend ? " *" : ""}</td><td className="px-3 py-2">{r.cost_per_order !== null ? formatMoney(r.cost_per_order, r.spend_currency ?? r.currency ?? "") : "-"}</td><td className="px-3 py-2 font-semibold">{r.roas !== null ? `${r.roas}x` : "-"}</td><td className="px-3 py-2">{r.sellers}</td><td className="px-3 py-2">{r.centres}</td><td className="px-3 py-2">{r.cost_per_application !== null ? formatMoney(r.cost_per_application, r.spend_currency ?? r.currency ?? "") : "-"}</td></tr>
            ))}</tbody>
          </table>
        </div>
      )}
      {data.rows.some((r) => r.shared_account && r.spend > 0) && <p className="text-xs text-muted-foreground" data-testid="shared-note">* {tr("This market uses the platform's ad account, shared with the other markets, so its spend cannot be split by market.")}</p>}
      {data.spend_synced_at && <p className="text-xs text-muted-foreground">{tr("Ad spend last read")}: {formatDateTime(data.spend_synced_at)}</p>}
      <p className="text-xs text-muted-foreground">{tr("Visitors who reject marketing cookies are never tracked, so they appear as direct. Add the tags (utm_source, utm_campaign) to every ad link: the Campaign links tab builds them.")}</p>
    </div>
  );
}

function Events() {
  const [rows, setRows] = useState<AdLog[] | null>(null);
  useEffect(() => { getAdLogs().then(setRows).catch(() => setRows([])); }, []);
  if (!rows) return <LoadingBlock />;
  if (rows.length === 0) return <EmptyState>{tr("Nothing has been reported to the ad platforms yet.")}</EmptyState>;

  return (
    <div className="overflow-x-auto rounded-xl border">
      <table className="w-full text-sm" data-testid="events-table">
        <thead className="bg-muted"><tr>{["When", "Platform", "Event", "Result", "Note"].map((h) => <th key={h} className="px-3 py-2 text-start font-medium">{tr(h)}</th>)}</tr></thead>
        <tbody>{rows.map((r) => (
          <tr key={r.id} className="border-t"><td className="px-3 py-2">{formatDateTime(r.created_at)}</td><td className="px-3 py-2">{r.platform}</td><td className="px-3 py-2">{r.event_name} <span className="text-xs text-muted-foreground" dir="ltr">{r.event_id}</span></td>
            <td className="px-3 py-2"><Badge variant={r.status === "sent" ? "default" : r.status === "failed" ? "destructive" : "outline"}>{tr(r.status === "sent" ? "Sent" : r.status === "failed" ? "Failed" : "Skipped")}</Badge></td><td className="px-3 py-2 text-xs text-muted-foreground">{r.note ?? ""}</td></tr>
        ))}</tbody>
      </table>
    </div>
  );
}

const SOURCES: [string, string, string][] = [["facebook", "Facebook", "paid_social"], ["instagram", "Instagram", "paid_social"], ["tiktok", "TikTok", "paid_social"], ["google", "Google Search", "cpc"], ["youtube", "YouTube", "video"]];

function Links() {
  const [stores, setStores] = useState<{ name: string; slug: string }[]>([]);
  const [dest, setDest] = useState("");
  const [source, setSource] = useState("facebook");
  const [campaign, setCampaign] = useState("");
  const [promo, setPromo] = useState("");
  const [copied, setCopied] = useState(false);
  useEffect(() => { getOpenStores().then((l) => setStores(l.map((s) => ({ name: s.name, slug: s.slug })))).catch(() => undefined); }, []);

  const origin = typeof window === "undefined" ? "" : window.location.origin;
  const medium = SOURCES.find((s) => s[0] === source)?.[2] ?? "cpc";
  const clean = campaign.trim().toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
  const url = `${origin}${dest || "/"}?${new URLSearchParams({ utm_source: source, utm_medium: medium, ...(clean ? { utm_campaign: clean } : {}), ...(promo.trim() ? { promo: promo.trim().toUpperCase() } : {}) }).toString()}`;

  return (
    <div className="max-w-2xl space-y-3">
      <p className="text-sm text-muted-foreground">{tr("Use one link per ad. The tags tell the platform which ad brought each order and application.")}</p>
      <div><label htmlFor="ld" className="text-sm font-medium">{tr("Where should the ad lead?")}</label>
        <select id="ld" className="mt-1 h-10 w-full rounded-lg border px-3" value={dest} onChange={(e) => setDest(e.target.value)}>
          <option value="">{tr("The platform home page")}</option>
          {stores.map((s) => <option key={s.slug} value={`/s/${s.slug}`}>{s.name}</option>)}
          {stores.map((s) => <option key={`p${s.slug}`} value={`/s/${s.slug}/products`}>{s.name}: {tr("All products")}</option>)}
          <option value="/seller-signup">{tr("Seller signup")}</option>
          <option value="/partner-signup">{tr("Installation centre signup")}</option>
        </select></div>
      <div><label htmlFor="ls" className="text-sm font-medium">{tr("Platform")}</label>
        <select id="ls" className="mt-1 h-10 w-full rounded-lg border px-3" value={source} onChange={(e) => setSource(e.target.value)}>{SOURCES.map(([k, n]) => <option key={k} value={k}>{n}</option>)}</select></div>
      <div><label htmlFor="lc" className="text-sm font-medium">{tr("Campaign name")}</label><Input id="lc" dir="ltr" value={campaign} onChange={(e) => setCampaign(e.target.value)} placeholder="eid-sale-2027" /></div>
      <div><label htmlFor="lp" className="text-sm font-medium">{tr("Discount code to apply (optional)")}</label><Input id="lp" dir="ltr" value={promo} onChange={(e) => setPromo(e.target.value)} /></div>
      <div className="rounded-lg border bg-muted p-3 text-sm break-all" dir="ltr" data-testid="campaign-url">{url}</div>
      <Button onClick={() => { void navigator.clipboard?.writeText(url); setCopied(true); setTimeout(() => setCopied(false), 1500); }}>{copied ? tr("Copied") : tr("Copy the link")}</Button>
    </div>
  );
}

function Feeds() {
  const [stores, setStores] = useState<{ name: string; slug: string; market: string | null }[]>([]);
  useEffect(() => { getOpenStores().then(setStores).catch(() => undefined); }, []);
  const api = (process.env.NEXT_PUBLIC_API_URL ?? "/api").replace(/\/$/, "");
  const abs = (p: string) => (api.startsWith("http") ? api : (typeof window === "undefined" ? "" : window.location.origin) + api) + p;

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">{tr("One feed per market. Paste the address into Google Merchant Center, Meta Commerce Manager or TikTok Catalog Manager and set it to refresh every day. Only products that can be bought straight from an ad are listed: a fixed price, in stock, with a picture, no quote and no installation offer.")}</p>
      {stores.map((s) => (
        <section key={s.slug} className="rounded-xl border p-4" data-testid="feed-card">
          <h2 className="font-semibold">{s.name}{s.market ? ` (${s.market})` : ""}</h2>
          <ul className="mt-2 space-y-1 text-sm" dir="ltr">
            {([["Google Merchant Center", "google.xml"], ["Meta catalog", "meta.csv"], ["TikTok catalog", "tiktok.csv"], ["Google Merchant Center (Arabic)", "google-ar.xml"], ["Meta catalog (Arabic)", "meta-ar.csv"], ["TikTok catalog (Arabic)", "tiktok-ar.csv"]] as const).map(([l, f]) => (
              <li key={f} className="flex flex-wrap items-center gap-2"><span className="w-44 shrink-0 font-medium" dir="auto">{tr(l)}</span><a className="break-all underline" href={abs(`/public/feeds/${s.slug}/${f}`)} target="_blank" rel="noreferrer">{abs(`/public/feeds/${s.slug}/${f}`)}</a></li>
            ))}
          </ul>
        </section>
      ))}
    </div>
  );
}

function MarketingPage() {
  const router = useRouter();
  const pathname = usePathname();
  const market = useSearchParams().get("market") ?? "";
  const [tab, setTab] = useState<Tab>("connections");
  const [rows, setRows] = useState<SettingRow[] | null>(null);
  const [markets, setMarkets] = useState<{ id: number; name: string }[]>([]);
  const [forbidden, setForbidden] = useState(false);

  const load = useCallback(() => getMarketingSettings().then(setRows).catch(() => setForbidden(true)), []);
  useEffect(() => { void load(); }, [load, market]);
  useEffect(() => { getMarketingMarkets().then(setMarkets).catch(() => undefined); }, []);

  if (forbidden) return <EmptyState>{tr("Only platform admins can open this page.")}</EmptyState>;
  if (!rows) return <LoadingBlock />;

  return (
    <div className="max-w-4xl">
      <PageHeader title={tr("Marketing")} />
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <label htmlFor="mk" className="text-sm font-medium">{tr("Market")}</label>
        <select id="mk" className="h-10 rounded-lg border px-3" value={market} onChange={(e) => router.replace(e.target.value ? `${pathname}?market=${e.target.value}` : pathname)}>
          <option value="">{tr("All markets (the platform's own ads)")}</option>
          {markets.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
        </select>
      </div>
      <Suspense fallback={null}><MarketScopeNotice /></Suspense>
      <div role="tablist" className="mb-5 flex flex-wrap gap-2">
        {TABS.map(([k, l]) => <Button key={k} size="sm" variant={tab === k ? "default" : "outline"} role="tab" aria-selected={tab === k} onClick={() => setTab(k)}>{tr(l)}</Button>)}
      </div>
      {tab === "connections" && <Connections rows={rows} setRows={setRows} />}
      {tab === "results" && <Results />}
      {tab === "events" && <Events />}
      {tab === "links" && <Links />}
      {tab === "feeds" && <Feeds />}
    </div>
  );
}

export default function Page() {
  return <Suspense fallback={<LoadingBlock />}><MarketingPage /></Suspense>;
}
