"use client";

import { Suspense, useEffect, useState } from "react";

import { ProviderManager } from "@/components/admin/provider-manager";
import { MarketScopeNotice } from "@/components/layout/market-scope";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { formatDateTime, formatMoney, tr } from "@/lib/i18n";
import {
  activateProvider,
  getAdminPayments,
  getProviders,
  saveProvider,
  testProvider,
} from "@/services/admin.service";
import type { AdminPayment, PaymentProviderInfo } from "@/types/admin";

/** Payment providers are drivers an admin configures and switches here at any time. */
export default function AdminPaymentsPage() {
  const [providers, setProviders] = useState<PaymentProviderInfo[] | null>(null);
  const [payments, setPayments] = useState<AdminPayment[]>([]);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getProviders().then((r) => setProviders(r.providers)).catch(() => setForbidden(true));
    getAdminPayments().then((r) => setPayments(r.data)).catch(() => undefined);
  }, []);

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!providers) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Payments" />
      <Suspense fallback={null}><MarketScopeNotice /></Suspense>
      <p className="mb-6 text-sm text-muted-foreground">
        {tr("Choose which provider takes payments and enter its keys. You can switch at any time; payments already started with another provider still complete.")}
      </p>

      <ProviderManager
        providers={providers}
        onChange={setProviders}
        api={{ save: saveProvider, activate: activateProvider, test: testProvider }}
        activatedText="This provider is now active for new payments."
        noWebhookFor={["simulated"]}
      />

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
