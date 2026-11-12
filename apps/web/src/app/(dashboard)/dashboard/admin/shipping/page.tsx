"use client";

import { Suspense, useEffect, useState } from "react";

import { ProviderManager } from "@/components/admin/provider-manager";
import { MarketScopeNotice } from "@/components/layout/market-scope";
import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { tr } from "@/lib/i18n";
import {
  activateShippingProvider,
  getShippingProviders,
  saveShippingProvider,
  testShippingProvider,
} from "@/services/admin.service";
import type { PaymentProviderInfo } from "@/types/admin";

/** Shipping providers are drivers an admin connects here. The built-in manual one works with any carrier. */
export default function AdminShippingPage() {
  const [providers, setProviders] = useState<PaymentProviderInfo[] | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getShippingProviders().then((r) => setProviders(r.providers)).catch(() => setForbidden(true));
  }, []);

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!providers) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div className="max-w-4xl">
      <PageHeader title="Shipping" />
      <Suspense fallback={null}><MarketScopeNotice /></Suspense>
      <p className="mb-6 text-sm text-muted-foreground">
        {tr("Connect the carriers you ship with. Staff can pick any ready provider for each parcel. The manual provider works with any carrier: staff enter the tracking number themselves. To connect a carrier with an API, use the custom carrier and describe its API.")}
      </p>

      <ProviderManager
        providers={providers}
        onChange={setProviders}
        api={{ save: saveShippingProvider, activate: activateShippingProvider, test: testShippingProvider }}
        activatedText="This provider is now the default for new shipments."
        activateLabel="Make default"
        noWebhookFor={["manual", "simulated"]}
      />
    </div>
  );
}
