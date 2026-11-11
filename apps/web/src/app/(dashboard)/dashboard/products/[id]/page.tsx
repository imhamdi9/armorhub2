"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { aliasLabel } from "@/lib/navigation";
import {
  getCatalog,
  getProduct,
  savePriceRules,
} from "@/services/platform.service";
import type { CatalogItem, PriceRule, Product } from "@/types/platform";

export default function ProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const user = useCurrentUser();
  const targets = user?.industry?.targets ?? [];

  const [product, setProduct] = useState<Product | null>(null);
  const [targetType, setTargetType] = useState(targets[0] ?? "");
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  // price by "type:id"
  const [prices, setPrices] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    getProduct(id).then((p) => {
      setProduct(p);
      setPrices(
        Object.fromEntries(
          (p.price_rules ?? []).map((r: PriceRule) => [
            `${r.target_type}:${r.target_id}`,
            String(r.price),
          ]),
        ),
      );
    });
  }, [id]);

  useEffect(() => {
    if (targetType) getCatalog(targetType).then(setCatalog);
  }, [targetType]);

  async function onSave() {
    const rules: PriceRule[] = Object.entries(prices)
      .filter(([, price]) => price !== "")
      .map(([key, price]) => {
        const [type, targetId] = key.split(":");
        return { target_type: type, target_id: Number(targetId), price: Number(price) };
      });
    await savePriceRules(id, rules);
    setSaved(true);
  }

  if (!product) return <p className="text-muted-foreground">Loading...</p>;

  return (
    <div>
      <PageHeader title={product.name} />
      <p className="mb-6 text-muted-foreground">Base price: {product.base_price}</p>

      <h2 className="mb-3 text-xl font-semibold">Price rules</h2>

      {targets.length === 0 ? (
        <EmptyState>
          Your account has no industry assigned, so there is nothing to price yet.
        </EmptyState>
      ) : (
        <>
          <div className="mb-4 flex gap-2">
            {targets.map((t) => (
              <Button
                key={t}
                variant={t === targetType ? "default" : "outline"}
                onClick={() => setTargetType(t)}
              >
                {aliasLabel(t)}
              </Button>
            ))}
          </div>

          <div className="space-y-2">
            {catalog.map((item) => {
              const key = `${targetType}:${item.id}`;
              return (
                <div key={item.id} className="flex items-center justify-between rounded-lg border p-3">
                  <span>{item.name}</span>
                  <Input
                    className="w-32"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Price"
                    value={prices[key] ?? ""}
                    onChange={(e) => {
                      setSaved(false);
                      setPrices((prev) => ({ ...prev, [key]: e.target.value }));
                    }}
                  />
                </div>
              );
            })}
          </div>

          <div className="mt-4 flex items-center gap-3">
            <Button onClick={onSave}>Save price rules</Button>
            {saved && <span className="text-sm text-green-600">Saved</span>}
          </div>
        </>
      )}
    </div>
  );
}
