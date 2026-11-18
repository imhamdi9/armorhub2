"use client";

import { useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { getSubscriptionPlans, updateSubscriptionPlan, type SubscriptionPlan } from "@/services/platform.service";
import { LoadingBlock } from "@/components/ui/loading-block";

type Draft = { name: string; price_monthly: string; customer_limit: string; commission_percentage: string; team_seats: string; is_active: boolean };

const toDraft = (p: SubscriptionPlan): Draft => ({ name: p.name, price_monthly: String(p.price_monthly), customer_limit: p.customer_limit === null ? "" : String(p.customer_limit), commission_percentage: String(p.commission_percentage), team_seats: String(p.team_seats), is_active: p.is_active !== false });

/** The platform administrator sets each plan's price, customer limit, commission and team seats. Changes apply to new charges and new orders. */
export default function SubscriptionPlansPage() {
  const [plans, setPlans] = useState<SubscriptionPlan[] | null>(null);
  const [drafts, setDrafts] = useState<Record<number, Draft>>({});
  const [msg, setMsg] = useState<Record<number, string>>({});

  useEffect(() => {
    getSubscriptionPlans().then((rows) => { setPlans(rows); setDrafts(Object.fromEntries(rows.map((p) => [p.id as number, toDraft(p)]))); });
  }, []);

  async function save(p: SubscriptionPlan) {
    const d = drafts[p.id as number];
    try {
      const updated = await updateSubscriptionPlan(p.id as number, {
        name: d.name, price_monthly: Number(d.price_monthly), customer_limit: d.customer_limit === "" ? null : Number(d.customer_limit),
        commission_percentage: Number(d.commission_percentage), team_seats: Number(d.team_seats), is_active: d.is_active,
      });
      setPlans((rows) => rows?.map((r) => (r.id === updated.id ? updated : r)) ?? null);
      setMsg((m) => ({ ...m, [p.id as number]: tr("Saved") }));
    } catch (e) {
      setMsg((m) => ({ ...m, [p.id as number]: errorMessage(e) }));
    }
  }

  if (!plans) return <LoadingBlock />;

  const set = (id: number, k: keyof Draft, v: string | boolean) => setDrafts((d) => ({ ...d, [id]: { ...d[id], [k]: v } }));

  return (
    <div>
      <PageHeader title="Subscription plans" />
      <p className="mb-4 max-w-2xl text-sm text-muted-foreground">{tr("Partners and sellers start on the free plan. Leave the customer limit empty for no limit. The free plan cannot be switched off.")}</p>
      <div className="grid gap-4 lg:grid-cols-3">
        {plans.map((p) => {
          const d = drafts[p.id as number];
          if (!d) return null;
          return (
            <section key={p.id} className="space-y-3 rounded-2xl border bg-white p-5 shadow-card" aria-label={p.key}>
              <h2 className="font-display text-lg font-semibold">{p.key}</h2>
              {([["name", "Name", "text"], ["price_monthly", "Price per month", "number"], ["customer_limit", "Customers a month", "number"], ["commission_percentage", "Commission (%)", "number"], ["team_seats", "Team accounts", "number"]] as const).map(([k, label, type]) => (
                <div key={k}>
                  <label className="mb-1 block text-sm font-medium" htmlFor={`${p.key}-${k}`}>{tr(label)}</label>
                  <Input id={`${p.key}-${k}`} type={type} step="any" value={String(d[k])} onChange={(e) => set(p.id as number, k, e.target.value)} />
                </div>
              ))}
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={d.is_active} disabled={p.key === "free"} onChange={(e) => set(p.id as number, "is_active", e.target.checked)} />{tr("Available to choose")}</label>
              <div className="flex items-center gap-3"><Button onClick={() => save(p)}>{tr("Save")}</Button>{msg[p.id as number] && <span role="status" className="text-sm text-muted-foreground">{msg[p.id as number]}</span>}</div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
