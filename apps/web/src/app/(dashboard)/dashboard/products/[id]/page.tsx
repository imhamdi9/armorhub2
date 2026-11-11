"use client";

import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { aliasLabel, hasRole } from "@/lib/navigation";
import { errorMessage } from "@/lib/errors";
import {
  deleteProduct,
  getCatalog,
  getProduct,
  savePriceRules,
  updateProduct,
} from "@/services/platform.service";
import type { CatalogItem, PriceRule, Product } from "@/types/platform";
import { tr } from "@/lib/i18n";

export default function ProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const user = useCurrentUser();
  const canEdit = hasRole(user, "platform-admin", "company-owner", "seller");
  const targets = user?.industry?.targets ?? [];

  const [product, setProduct] = useState<Product | null>(null);
  const [targetType, setTargetType] = useState(targets[0] ?? "");
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  // price by "type:id"
  const [prices, setPrices] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState(false);
  const [details, setDetails] = useState({
    name: "", brand: "", category: "", base_price: "", warranty_years: "", description: "", is_active: true, requires_shipping: false,
  });
  const [detailsMsg, setDetailsMsg] = useState<string | null>(null);

  useEffect(() => {
    getProduct(id).then((p) => {
      setProduct(p);
      setDetails({
        name: p.name,
        brand: p.brand ?? "",
        category: p.category ?? "",
        base_price: String(p.base_price),
        warranty_years: p.warranty_years == null ? "" : String(p.warranty_years),
        description: p.description ?? "",
        is_active: p.is_active,
        requires_shipping: !!p.requires_shipping,
      });
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

  async function saveDetails(e: React.FormEvent) {
    e.preventDefault();
    setDetailsMsg(null);
    try {
      const updated = await updateProduct(Number(id), {
        name: details.name,
        brand: details.brand || null,
        category: details.category || null,
        base_price: details.base_price,
        warranty_years: details.warranty_years === "" ? null : Number(details.warranty_years),
        description: details.description || null,
        is_active: details.is_active,
        requires_shipping: details.requires_shipping,
      });
      setProduct(updated);
      setDetailsMsg(tr("Saved"));
    } catch (err) {
      setDetailsMsg(errorMessage(err));
    }
  }

  async function remove() {
    if (!window.confirm(tr("Delete this product? This cannot be undone. Products with orders cannot be deleted; hide them instead."))) return;
    try {
      await deleteProduct(Number(id));
      router.push("/dashboard/products");
    } catch (err) {
      setDetailsMsg(errorMessage(err));
    }
  }

  if (!product) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div>
      <PageHeader title={product.name} />
      <p className="mb-6 text-muted-foreground">{tr("Base price")}: {product.base_price}</p>

      {canEdit && (
        <form onSubmit={saveDetails} className="mb-8 grid grid-cols-2 gap-3 rounded-lg border p-4">
          <Input aria-label={tr("Name")} required value={details.name} onChange={(e) => setDetails({ ...details, name: e.target.value })} />
          <Input aria-label={tr("Brand")} placeholder={tr("Brand")} value={details.brand} onChange={(e) => setDetails({ ...details, brand: e.target.value })} />
          <Input aria-label={tr("Category")} placeholder={tr("Category")} value={details.category} onChange={(e) => setDetails({ ...details, category: e.target.value })} />
          <Input aria-label={tr("Base price")} type="number" min="0" step="0.01" required value={details.base_price} onChange={(e) => setDetails({ ...details, base_price: e.target.value })} />
          <Input aria-label={tr("Warranty years")} type="number" min="0" placeholder={tr("Warranty (years)")} value={details.warranty_years} onChange={(e) => setDetails({ ...details, warranty_years: e.target.value })} />
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={details.is_active} onChange={(e) => setDetails({ ...details, is_active: e.target.checked })} />
            {tr("Visible to customers")}
          </label>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" id="requires_shipping" checked={details.requires_shipping} onChange={(e) => setDetails({ ...details, requires_shipping: e.target.checked })} />
            {tr("Physical product: needs a delivery address and shipping")}
          </label>
          <textarea
            aria-label={tr("Description")}
            placeholder={tr("Description")}
            className="col-span-2 rounded-lg border px-3 py-2"
            value={details.description}
            onChange={(e) => setDetails({ ...details, description: e.target.value })}
          />
          <div className="col-span-2 flex items-center gap-3">
            <Button type="submit">{tr("Save details")}</Button>
            <Button type="button" variant="outline" onClick={remove}>{tr("Delete product")}</Button>
            {detailsMsg && <span className="text-sm">{detailsMsg}</span>}
          </div>
        </form>
      )}

      <h2 className="mb-3 text-xl font-semibold">{tr("Price rules")}</h2>

      {targets.length === 0 ? (
        <EmptyState>
          {tr("Your account has no industry assigned, so there is nothing to price yet.")}
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
                {tr(aliasLabel(t))}
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
                    placeholder={tr("Price")}
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
            <Button onClick={onSave}>{tr("Save price rules")}</Button>
            {saved && <span className="text-sm text-green-600">{tr("Saved")}</span>}
          </div>
        </>
      )}
    </div>
  );
}
