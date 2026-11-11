"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { price } from "@/components/public/product-card";
import { PublicShell, type ShellStore } from "@/components/public/shell";
import { formatMoney, tr } from "@/lib/i18n";
import { me } from "@/services/auth.service";
import type { PublicProductPage } from "@/types/public";

/** Takes signed-in customers straight to ordering, and everyone else to signup (keeping the product). */
function QuoteButton({ slug, productId }: { slug: string; productId: number }) {
  const [signedIn, setSignedIn] = useState(false);

  useEffect(() => {
    me().then(() => setSignedIn(true)).catch(() => setSignedIn(false));
  }, []);

  const href = signedIn
    ? `/dashboard/orders/new?product=${productId}`
    : `/register?store=${encodeURIComponent(slug)}&product=${productId}`;

  return <Link href={href} className="btn-brand inline-block px-8 py-3.5 text-lg">{tr("Get a quote")}</Link>;
}

export function ProductDetail({ store, data }: { store: ShellStore; data: PublicProductPage }) {
  const p = data.product;

  return (
    <PublicShell store={store}>
      <div className="mx-auto max-w-4xl px-4 py-10">
        <Link href={`/s/${store.slug}/products`} className="text-sm font-semibold text-forest underline underline-offset-4">← {tr("All products")}</Link>

        <div className="mt-4 grid gap-8 md:grid-cols-[1fr_20rem]">
          <div>
            {p.category && <span className="text-xs font-bold uppercase tracking-wider text-spruce">{p.category}</span>}
            <h1 className="display display-md mt-1">{p.name}</h1>
            {p.brand && <p className="text-slate">{p.brand}</p>}
            {p.description && <p className="mt-4 whitespace-pre-line text-charcoal">{p.description}</p>}

            {p.prices.length > 0 && (
              <section className="mt-8">
                <h2 className="mb-3 text-xl font-bold text-obsidian">{tr("Prices")}</h2>
                <table className="w-full text-sm">
                  <tbody>
                    {p.prices.map((row) => (
                      <tr key={`${row.target_type}-${row.target}`} className="border-b border-fog">
                        <td className="py-2">{row.target}</td>
                        <td className="py-2 text-end font-medium">{formatMoney(row.price, p.currency, true)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <p className="mt-2 text-xs text-slate">{tr("Service by a partner is quoted separately after you order.")}</p>
              </section>
            )}
          </div>

          <aside className="h-fit rounded-[24px] bg-linen p-6">
            <p className="text-sm text-slate">{tr("From")}</p>
            <p className="text-3xl font-black tracking-tight text-forest">{price(p)}</p>
            {p.warranty_years ? <p className="mt-2 text-sm">🛡 {tr("{n}-year warranty", { n: p.warranty_years })}</p> : null}
            <div className="mt-5"><QuoteButton slug={store.slug} productId={p.id} /></div>
          </aside>
        </div>
      </div>
    </PublicShell>
  );
}
