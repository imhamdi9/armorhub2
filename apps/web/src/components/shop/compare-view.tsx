"use client";

import type { ShellStore } from "@/components/public/shell";
import { CompareTable } from "@/components/shop/memory";
import { ShopShell } from "@/components/shop/shop-shell";
import { tr } from "@/lib/i18n";

/** The comparison page: the products the shopper picked, side by side. */
export function CompareView({ store }: { store: ShellStore }) {
  return (
    <ShopShell store={store} active="products">
      <div className="pt-8">
        <h1 className="display display-md mb-6">{tr("Compare products")}</h1>
        <CompareTable slug={store.slug} />
      </div>
    </ShopShell>
  );
}
