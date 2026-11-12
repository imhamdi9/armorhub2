"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { aliasLabel, aliasSingular } from "@/lib/navigation";
import { errorMessage } from "@/lib/errors";
import {
  calculatePrice,
  createOrder,
  getCatalog,
  getMyItems,
  getProducts,
  updateLocation,
} from "@/services/platform.service";
import type { CatalogItem, PriceQuote, Product, ShippingAddress } from "@/types/platform";
import { formatMoney, tr } from "@/lib/i18n";

interface Option {
  id: number;
  label: string;
}

/** Pick a product, what to service (if anything) and the options; see the price; order. */
export default function NewOrderPage() {
  return (
    <Suspense>
      <NewOrder />
    </Suspense>
  );
}

function NewOrder() {
  const router = useRouter();
  // Coming from a product page on the storefront: that product is already chosen.
  const search = useSearchParams();
  const preselected = Number(search.get("product")) || null;
  const installationRequest = Number(search.get("installation_request")) || null;
  const user = useCurrentUser();
  const industry = user?.industry;
  const targetType = industry?.targets[0] ?? "";
  const subjectType = industry?.subjects[0] ?? null;

  const [products, setProducts] = useState<Product[]>([]);
  const [productId, setProductId] = useState<number | null>(preselected);
  const [targets, setTargets] = useState<CatalogItem[]>([]);
  const [selected, setSelected] = useState<number[]>([]);
  const [subjects, setSubjects] = useState<Option[]>([]);
  const [subjectId, setSubjectId] = useState<number | null>(null);
  const [price, setPrice] = useState<PriceQuote | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [needsLocation, setNeedsLocation] = useState(false);
  const [busy, setBusy] = useState(false);
  const [address, setAddress] = useState<ShippingAddress>({ name: user?.name ?? "", phone: user?.phone ?? "", line1: "", city: "", country: user?.market?.code ?? "SA" });
  const setAddr = (k: keyof ShippingAddress) => (e: React.ChangeEvent<HTMLInputElement>) => setAddress((a) => ({ ...a, [k]: e.target.value }));

  useEffect(() => {
    getProducts().then(setProducts);
    if (targetType) getCatalog(targetType).then(setTargets);
  }, [targetType]);

  useEffect(() => {
    if (!subjectType) return;
    // A subject is either the customer's own item (a property) or a shared catalog (a vehicle model); the server says which.
    if (industry?.owned_subjects?.includes(subjectType)) {
      getMyItems(subjectType).then((rows) =>
        setSubjects(rows.map((r) => ({ id: Number(r.id), label: [r.type, r.address, r.city].filter(Boolean).join(" · ") || `#${r.id}` }))),
      ).catch(() => setSubjects([]));
    } else {
      getCatalog(subjectType).then((rows) => setSubjects(rows.map((r) => ({ id: r.id, label: r.name })))).catch(() => setSubjects([]));
    }
  }, [subjectType, industry]);

  const canPrice = productId !== null && selected.length > 0;
  const needsShipping = !!products.find((p) => p.id === productId)?.requires_shipping;

  useEffect(() => {
    if (!canPrice) return;
    calculatePrice({ product_id: productId, targets: selected, target_type: targetType })
      .then(setPrice)
      .catch(() => setPrice(null));
  }, [canPrice, productId, selected, targetType]);

  function toggle(id: number) {
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }

  function shareLocation() {
    navigator.geolocation.getCurrentPosition(
      async (pos) => {
        await updateLocation({ latitude: pos.coords.latitude, longitude: pos.coords.longitude });
        setNeedsLocation(false);
        setError(null);
      },
      () => setError(tr("Location permission was denied.")),
    );
  }

  async function submit() {
    if (!productId) return;
    setBusy(true);
    setError(null);
    try {
      const res = await createOrder({
        product_id: productId,
        subject_type: subjectType,
        subject_id: subjectType ? subjectId : null,
        target_type: targetType,
        targets: selected,
        ...(installationRequest ? { installation_request_id: installationRequest } : {}),
        ...(needsShipping ? { shipping_address: address } : {}),
      });
      router.push(`/dashboard/orders/${res.order.id}`);
    } catch (e) {
      const msg = errorMessage(e);
      setNeedsLocation(/location/i.test(msg));
      setError(msg);
    } finally {
      setBusy(false);
    }
  }

  if (!industry) {
    return <p className="text-muted-foreground">{tr("Your account has no industry assigned yet.")}</p>;
  }

  const addressReady = !needsShipping || (address.name && address.phone && address.line1 && address.city && address.country.length === 2);
  const ready = productId && selected.length > 0 && (!subjectType || subjectId) && addressReady;

  return (
    <div className="max-w-2xl space-y-6">
      <PageHeader title={tr("New order")} />

      <section>
        <h2 className="mb-2 font-semibold">{tr("1. Choose a product")}</h2>
        <div className="space-y-2">
          {products.map((p) => (
            <label key={p.id} className="flex cursor-pointer items-center gap-3 rounded-lg border p-3">
              <input type="radio" name="product" checked={productId === p.id} onChange={() => setProductId(p.id)} />
              <span className="flex-1">{p.name}</span>
              <span className="text-sm text-muted-foreground">{p.category}</span>
            </label>
          ))}
        </div>
      </section>

      {subjectType && (
        <section>
          <h2 className="mb-2 font-semibold">2. {tr(aliasSingular(subjectType))}</h2>
          <select
            aria-label={tr(aliasSingular(subjectType))}
            className="w-full rounded-lg border px-3 py-2"
            value={subjectId ?? ""}
            onChange={(e) => setSubjectId(Number(e.target.value) || null)}
          >
            <option value="">{tr("Select...")}</option>
            {subjects.map((s) => (
              <option key={s.id} value={s.id}>{s.label}</option>
            ))}
          </select>
          {subjects.length === 0 && (
            <p className="mt-1 text-sm text-muted-foreground">
              Nothing to choose yet. Add one under &ldquo;My {aliasLabel(subjectType)}&rdquo;.
            </p>
          )}
        </section>
      )}

      <section>
        <h2 className="mb-2 font-semibold">{subjectType ? "3" : "2"}. {tr(aliasLabel(targetType))}</h2>
        <div className="grid grid-cols-2 gap-2">
          {targets.map((t) => (
            <label key={t.id} className="flex cursor-pointer items-center gap-2 rounded-lg border p-3">
              <input type="checkbox" checked={selected.includes(t.id)} onChange={() => toggle(t.id)} />
              {t.name}
            </label>
          ))}
        </div>
      </section>

      {needsShipping && (
        <section>
          <h2 className="mb-2 font-semibold">{tr("Delivery address")}</h2>
          <div className="grid gap-2 sm:grid-cols-2">
            <Input aria-label={tr("Full name")} placeholder={tr("Full name")} autoComplete="name" value={address.name} onChange={setAddr("name")} />
            <Input aria-label={tr("Mobile number")} placeholder={tr("Mobile number")} type="tel" dir="ltr" autoComplete="tel" value={address.phone} onChange={setAddr("phone")} />
            <Input className="sm:col-span-2" aria-label={tr("Street address")} placeholder={tr("Street address")} autoComplete="address-line1" value={address.line1} onChange={setAddr("line1")} />
            <Input className="sm:col-span-2" aria-label={tr("Apartment, building (optional)")} placeholder={tr("Apartment, building (optional)")} autoComplete="address-line2" value={address.line2 ?? ""} onChange={setAddr("line2")} />
            <Input aria-label={tr("City")} placeholder={tr("City")} autoComplete="address-level2" value={address.city} onChange={setAddr("city")} />
            <Input aria-label={tr("Postal code (optional)")} placeholder={tr("Postal code (optional)")} dir="ltr" autoComplete="postal-code" value={address.postal_code ?? ""} onChange={setAddr("postal_code")} />
            <Input aria-label={tr("Country code")} placeholder="SA" maxLength={2} dir="ltr" value={address.country} onChange={(e) => setAddress((a) => ({ ...a, country: e.target.value.toUpperCase() }))} />
          </div>
        </section>
      )}

      <div className="rounded-lg border p-4">
        <p className="text-sm text-muted-foreground">{tr("Estimated price")}</p>
        <p className="text-2xl font-bold">
          {canPrice && price ? formatMoney(price.total, price.currency) : "—"}
        </p>
        <p className="text-xs text-muted-foreground">{tr("Partner service quotes are added after you order.")}</p>
      </div>

      {error && <p className="text-sm text-red-500">{error}</p>}
      {needsLocation && <Button variant="outline" onClick={shareLocation}>{tr("Share my location")}</Button>}

      <Button disabled={!ready || busy} onClick={submit}>{tr("Place order")}</Button>
    </div>
  );
}
