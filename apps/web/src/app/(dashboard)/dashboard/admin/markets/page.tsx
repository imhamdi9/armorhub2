"use client";

import Link from "next/link";
import { useEffect, useMemo, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr, useLocale } from "@/lib/i18n";
import { getCountries, getMarkets, openMarket, updateMarket, type MarketRow } from "@/services/platform.service";

/** A flag from the two-letter country code (regional indicator symbols). */
const flag = (code: string) => String.fromCodePoint(...code.toUpperCase().split("").map((c) => 127397 + c.charCodeAt(0)));

type Industry = { id: number; key: string; name: string };

function IndustryChecks({ all, value, onChange }: { all: Industry[]; value: number[]; onChange: (v: number[]) => void }) {
  return (
    <fieldset className="flex flex-wrap gap-3">
      <legend className="sr-only">{tr("Industries")}</legend>
      {all.map((i) => (
        <label key={i.id} className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={value.includes(i.id)} onChange={(e) => onChange(e.target.checked ? [...value, i.id] : value.filter((x) => x !== i.id))} />
          {tr(i.name)}
        </label>
      ))}
    </fieldset>
  );
}

function MarketCard({ m, all, onChange }: { m: MarketRow; all: Industry[]; onChange: (m: MarketRow) => void }) {
  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(m.name);
  const [chosen, setChosen] = useState(m.industries.map((i) => i.id));
  const [error, setError] = useState<string | null>(null);

  async function save() {
    setError(null);
    try { onChange(await updateMarket(m.id, { name, industries: chosen })); setEditing(false); } catch (e) { setError(errorMessage(e)); }
  }

  return (
    <section className="rounded-xl border p-4" data-testid="market-card" aria-label={m.name}>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-xl font-semibold"><span aria-hidden="true">{flag(m.country)}</span> {m.name}</h2>
        <div className="flex items-center gap-2">
          <Badge variant={m.status === "active" ? "default" : "secondary"}>{tr(m.status === "active" ? "Open" : "Paused")}</Badge>
          <Button size="sm" variant="outline" onClick={() => updateMarket(m.id, { status: m.status === "active" ? "paused" : "active" }).then(onChange)}>{tr(m.status === "active" ? "Pause" : "Open again")}</Button>
          <Button size="sm" variant="outline" onClick={() => setEditing((v) => !v)}>{tr("Edit")}</Button>
          <Button size="sm" variant="outline" render={<Link href={`/dashboard/admin/payments?market=${m.id}`} />}>{tr("Payments")}</Button>
          <Button size="sm" variant="outline" render={<Link href={`/dashboard/admin/shipping?market=${m.id}`} />}>{tr("Shipping")}</Button>
          <Button size="sm" variant="outline" render={<Link href={`/dashboard/admin/settings?market=${m.id}`} />}>{tr("Fees and settings")}</Button>
        </div>
      </div>
      <p className="mt-1 text-sm text-muted-foreground">{m.country_name} · {m.currency}{m.store_slug && <> · <Link className="underline" href={`/s/${m.store_slug}`}>/s/{m.store_slug}</Link></>}</p>
      <p className="mt-3 flex flex-wrap gap-2">{m.industries.map((i) => <Badge key={i.id} variant="outline">{tr(i.name)}</Badge>)}</p>
      <dl className="mt-3 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
        {([["sellers", "Sellers"], ["centres", "Service centres"], ["products", "Products"], ["orders", "Orders"]] as const).map(([k, label]) => (
          <div key={k} className="rounded-lg bg-muted p-2"><dt className="text-xs text-muted-foreground">{tr(label)}</dt><dd className="text-lg font-bold">{m.stats[k]}</dd></div>
        ))}
      </dl>
      {editing && (
        <div className="mt-4 space-y-3 rounded-lg border p-3">
          <div><Label htmlFor={`n-${m.id}`}>{tr("Market name")}</Label><Input id={`n-${m.id}`} value={name} onChange={(e) => setName(e.target.value)} /></div>
          <IndustryChecks all={all} value={chosen} onChange={setChosen} />
          {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
          <Button size="sm" onClick={save} disabled={chosen.length === 0}>{tr("Save")}</Button>
        </div>
      )}
    </section>
  );
}

/** The platform owner opens a market in any country of the world and chooses its industries; one market per country. */
export default function MarketsPage() {
  const locale = useLocale();
  const [markets, setMarkets] = useState<MarketRow[] | null>(null);
  const [industries, setIndustries] = useState<Industry[]>([]);
  const [countries, setCountries] = useState<Awaited<ReturnType<typeof getCountries>>>([]);
  const [forbidden, setForbidden] = useState(false);
  const [country, setCountry] = useState("");
  const [name, setName] = useState("");
  const [chosen, setChosen] = useState<number[]>([]);
  const [slug, setSlug] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    getMarkets().then((r) => { setMarkets(r.markets); setIndustries(r.industries); }).catch(() => setForbidden(true));
    getCountries().then(setCountries).catch(() => undefined);
  }, []);

  const free = useMemo(() => countries.filter((c) => !c.open), [countries]);
  const chosenCountry = countries.find((c) => c.code === country);
  const label = (c: { name: string; name_ar: string }) => (locale === "ar" ? c.name_ar : c.name);

  async function open(e: React.FormEvent) {
    e.preventDefault();
    setError(null); setBusy(true);
    try {
      const created = await openMarket({ country, ...(name ? { name } : {}), industries: chosen, ...(slug ? { slug } : {}) });
      setMarkets((m) => [...(m ?? []), created]);
      setCountries((cs) => cs.map((c) => (c.code === country ? { ...c, open: true } : c)));
      setCountry(""); setName(""); setChosen([]); setSlug("");
    } catch (err) { setError(errorMessage(err)); } finally { setBusy(false); }
  }

  if (forbidden) return <EmptyState>{tr("Only platform admins can open this page.")}</EmptyState>;
  if (!markets) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Markets" />
      <p className="mb-6 text-sm text-muted-foreground">{tr("ArmorHub runs one market in each country, for example ArmorHub Egypt or ArmorHub Brazil. A market runs the industries you choose, has its own sellers, service centres, customers, payment and shipping settings, and one public store.")}</p>

      <div className="space-y-4">
        {markets.length === 0 ? <EmptyState>{tr("No markets yet. Open the first one below.")}</EmptyState> : markets.map((m) => (
          <MarketCard key={m.id} m={m} all={industries} onChange={(u) => setMarkets((all) => (all ?? []).map((x) => (x.id === u.id ? u : x)))} />
        ))}
      </div>

      <form onSubmit={open} className="mt-8 space-y-4 rounded-xl border p-4" aria-label={tr("Open a new market")}>
        <h2 className="text-xl font-semibold">{tr("Open a new market")}</h2>
        <div>
          <Label htmlFor="country">{tr("Country")}</Label>
          <select id="country" required className="h-10 w-full rounded-[10px] border border-input bg-transparent px-2.5 text-sm" value={country}
            onChange={(e) => { setCountry(e.target.value); const c = countries.find((x) => x.code === e.target.value); setName(c ? `ArmorHub ${c.name}` : ""); }}>
            <option value="" disabled>{tr("Choose a country")}</option>
            {free.map((c) => <option key={c.code} value={c.code}>{flag(c.code)} {label(c)} ({c.currency})</option>)}
          </select>
          {chosenCountry && <p className="mt-1 text-xs text-muted-foreground">{tr("Currency")}: {chosenCountry.currency}</p>}
        </div>
        <div><Label htmlFor="mname">{tr("Market name")}</Label><Input id="mname" value={name} onChange={(e) => setName(e.target.value)} placeholder="ArmorHub Egypt" /></div>
        <div><p className="mb-1 text-sm font-medium">{tr("Industries this market runs")}</p><IndustryChecks all={industries} value={chosen} onChange={setChosen} /></div>
        <div><Label htmlFor="mslug">{tr("Store address (optional)")}</Label><Input id="mslug" dir="ltr" value={slug} onChange={(e) => setSlug(e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ""))} placeholder={country ? `armorhub-${country.toLowerCase()}` : "armorhub-eg"} /></div>
        {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
        <Button type="submit" disabled={busy || !country || chosen.length === 0}>{tr("Open the market")}</Button>
      </form>
    </div>
  );
}
