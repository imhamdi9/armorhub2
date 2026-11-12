"use client";

import { CategoryField } from "@/components/products/category-field";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { aliasLabel } from "@/lib/navigation";
import { errorMessage } from "@/lib/errors";
import {
  deleteProduct,
  getCatalog,
  getProduct,
  savePriceRules,
  updateProduct,
  uploadProductImage,
} from "@/services/platform.service";
import type { CatalogItem, PriceRule, Product } from "@/types/platform";
import { tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";

export default function ProductDetailPage() {
  const { id } = useParams<{ id: string }>();
  const router = useRouter();
  const user = useCurrentUser();
  const targets = user?.industry?.targets ?? [];

  const [product, setProduct] = useState<Product | null>(null);
  // The server decides (it knows whose product this is); until it answers nobody gets an editor.
  const canEdit = product?.can_edit === true;
  const [targetType, setTargetType] = useState(targets[0] ?? "");
  const [catalog, setCatalog] = useState<CatalogItem[]>([]);
  // price by "type:id"
  const [prices, setPrices] = useState<Record<string, string>>({});
  const [saved, setSaved] = useState(false);
  const [details, setDetails] = useState({
    name: "", brand: "", category: "", base_price: "", compare_at_price: "", warranty_years: "", description: "", is_active: true, requires_shipping: false, images: [] as string[], highlights: "",
  });
  const [detailsMsg, setDetailsMsg] = useState<string | null>(null);
  const [newImage, setNewImage] = useState("");

  useEffect(() => {
    getProduct(id).then((p) => {
      setProduct(p);
      setDetails({
        name: p.name,
        brand: p.brand ?? "",
        category: p.category ?? "",
        base_price: String(p.base_price),
        compare_at_price: p.compare_at_price == null ? "" : String(p.compare_at_price),
        images: p.images ?? [],
        highlights: ((p.attributes?.highlights as string[] | undefined) ?? []).join("\n"),
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
        category: details.category,
        base_price: details.base_price,
        compare_at_price: details.compare_at_price === "" ? null : details.compare_at_price,
        images: details.images,
        attributes: { ...(product?.attributes ?? {}), highlights: details.highlights.split("\n").map((l) => l.trim()).filter(Boolean) },
        warranty_years: details.warranty_years === "" ? null : Number(details.warranty_years),
        description: details.description || null,
        is_active: details.is_active,
        requires_shipping: details.requires_shipping,
      });
      setProduct((current) => ({ ...updated, can_edit: current?.can_edit ?? true }));
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
      {canEdit && typeof window !== "undefined" && new URLSearchParams(window.location.search).get("new") === "1" && (
        <p role="note" className="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
          {tr("Your product is saved. Add a price for each option below, and a photo, so customers can order it.")}
        </p>
      )}
      <p className="mb-6 text-muted-foreground">{tr("Base price")}: {product.base_price}</p>

      {canEdit && (
        <form onSubmit={saveDetails} className="mb-8 grid grid-cols-2 gap-3 rounded-lg border p-4">
          <Input aria-label={tr("Name")} required value={details.name} onChange={(e) => setDetails({ ...details, name: e.target.value })} />
          <Input aria-label={tr("Brand")} placeholder={tr("Brand")} value={details.brand} onChange={(e) => setDetails({ ...details, brand: e.target.value })} />
          <CategoryField value={details.category} onChange={(v) => setDetails({ ...details, category: v })} />
          <Input aria-label={tr("Base price")} type="number" min="0" step="0.01" required value={details.base_price} onChange={(e) => setDetails({ ...details, base_price: e.target.value })} />
          <Input aria-label={tr("Warranty years")} type="number" min="0" placeholder={tr("Warranty (years)")} value={details.warranty_years} onChange={(e) => setDetails({ ...details, warranty_years: e.target.value })} />
          <Input aria-label={tr("Was price (shown struck through)")} type="number" min="0" step="0.01" placeholder={tr("Was price (shown struck through)")} value={details.compare_at_price} onChange={(e) => setDetails({ ...details, compare_at_price: e.target.value })} />
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
          <textarea
            aria-label={tr("Highlights (one per line)")}
            placeholder={tr("Highlights (one per line)")}
            className="col-span-2 rounded-lg border px-3 py-2"
            rows={3}
            value={details.highlights}
            onChange={(e) => setDetails({ ...details, highlights: e.target.value })}
          />
          <div className="col-span-2 space-y-2">
            <p className="text-sm font-medium">{tr("Images (the first is the main picture)")}</p>
            <div className="flex flex-wrap gap-2">
              {details.images.map((src, i) => (
                <div key={`${src}-${i}`} className="relative">
                  {/* eslint-disable-next-line @next/next/no-img-element -- thumbnails of the seller's own images */}
                  <img src={imageSrc(src) ?? ""} alt="" className="h-20 w-20 rounded border object-cover" />
                  <button type="button" aria-label={tr("Remove")} className="absolute -end-1 -top-1 rounded-full bg-black px-1.5 text-xs text-white" onClick={() => setDetails({ ...details, images: details.images.filter((_, j) => j !== i) })}>×</button>
                </div>
              ))}
            </div>
            <div className="flex gap-2">
              <Input aria-label={tr("Image address (https)")} placeholder="https://" value={newImage} onChange={(e) => setNewImage(e.target.value)} />
              <label className="inline-flex cursor-pointer items-center whitespace-nowrap rounded-lg border px-3 py-2 text-sm font-medium hover:bg-muted">
                {tr("Upload a photo")}
                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" aria-label={tr("Upload a photo")} className="sr-only" disabled={details.images.length >= 8}
                  onChange={async (e) => {
                    const f = e.target.files?.[0];
                    e.target.value = "";
                    if (!f) return;
                    try { setDetails((d) => ({ ...d, images: [...d.images, "" ] })); const path = await uploadProductImage(f); setDetails((d) => ({ ...d, images: [...d.images.filter(Boolean), path] })); } catch (err) { setDetails((d) => ({ ...d, images: d.images.filter(Boolean) })); setDetailsMsg(errorMessage(err)); }
                  }} />
              </label>
              <Button type="button" variant="outline" disabled={!newImage || details.images.length >= 8} onClick={() => { setDetails({ ...details, images: [...details.images, newImage] }); setNewImage(""); }}>{tr("Add image")}</Button>
            </div>
          </div>
          <div className="col-span-2 flex items-center gap-3">
            <Button type="submit">{tr("Save details")}</Button>
            <Button type="button" variant="outline" onClick={remove}>{tr("Delete product")}</Button>
            {detailsMsg && <span className="text-sm">{detailsMsg}</span>}
          </div>
        </form>
      )}

      {!canEdit && product.seller_id && <p className="mb-6 rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">{tr("This product is managed by its seller, so you can see it but not change it.")}</p>}

      <h2 className="mb-3 text-xl font-semibold">{canEdit ? tr("Price rules") : tr("Prices")}</h2>

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
            {catalog.filter((item) => canEdit || prices[`${targetType}:${item.id}`] !== undefined).map((item) => {
              const key = `${targetType}:${item.id}`;
              return (
                <div key={item.id} className="flex items-center justify-between rounded-lg border p-3">
                  <span>{item.name}</span>
                  {!canEdit ? <span className="font-medium">{prices[key]}</span> : <Input
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
                  />}
                </div>
              );
            })}
          </div>

          {canEdit && (
            <div className="mt-4 flex items-center gap-3">
              <Button onClick={onSave}>{tr("Save price rules")}</Button>
              {saved && <span className="text-sm text-green-600">{tr("Saved")}</span>}
            </div>
          )}
        </>
      )}
    </div>
  );
}
