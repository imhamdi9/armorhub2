"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { hasRole } from "@/lib/navigation";
import { createProduct, getProducts } from "@/services/platform.service";
import type { Product } from "@/types/platform";
import { tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";

function slugify(value: string) {
  return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

export default function ProductsPage() {
  const user = useCurrentUser();
  const router = useRouter();
  const [products, setProducts] = useState<Product[] | null>(null);
  const [search, setSearch] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [price, setPrice] = useState("");
  const [error, setError] = useState<string | null>(null);

  const canManage = hasRole(user, "platform-admin", "company-owner", "seller");

  useEffect(() => {
    getProducts({
      ...(search ? { search } : {}),
      ...(canManage ? { include_inactive: 1 } : {}),
    }).then(setProducts);
  }, [search, canManage]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      const product = await createProduct({
        name,
        slug: `${slugify(name)}-${Math.random().toString(36).slice(2, 6)}`,
        category: category || null,
        base_price: price,
        industry_id: user?.industry?.id ?? null,
      });
      // A new product can't be ordered until it has prices (and ideally a photo), so go straight to where those are set.
      router.push(`/dashboard/products/${product.id}?new=1`);
    } catch {
      setError(tr("Could not save the product. Check the fields and try again."));
    }
  }

  return (
    <div>
      <PageHeader
        title={tr("Products")}
        action={canManage && <Button onClick={() => setShowForm((v) => !v)}>{tr("Add product")}</Button>}
      />

      {showForm && (
        <form onSubmit={onSubmit} className="mb-6 space-y-4 rounded-lg border p-4">
          <div>
            <Label htmlFor="name">{tr("Name")}</Label>
            <Input id="name" required value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="category">{tr("Category")}</Label>
            <Input id="category" value={category} onChange={(e) => setCategory(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="price">{tr("Base price")}</Label>
            <Input id="price" type="number" min="0" step="0.01" required value={price} onChange={(e) => setPrice(e.target.value)} />
          </div>
          {error && <p className="text-sm text-red-500">{error}</p>}
          <Button type="submit">{tr("Save")}</Button>
        </form>
      )}

      <Input
        className="mb-4 max-w-sm"
        placeholder={tr("Search products")}
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />

      {products === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : products.length === 0 ? (
        <EmptyState>
          {tr("No products found.")}
          {canManage && !search && <> <button type="button" onClick={() => setShowForm(true)} className="font-medium underline underline-offset-2">{tr("Add your first product")}</button></>}
        </EmptyState>
      ) : (
        <div className="space-y-3">
          {products.map((p) => (
            <Link
              key={p.id}
              href={`/dashboard/products/${p.id}`}
              className="block rounded-lg border p-4 hover:bg-muted"
            >
              <div className="flex items-center gap-3">
                {p.images?.[0] ? (
                  // eslint-disable-next-line @next/next/no-img-element -- the seller's own product photo, as a thumbnail
                  <img src={imageSrc(p.images[0]) ?? ""} alt="" className="size-12 shrink-0 rounded-lg border object-cover" />
                ) : (
                  <span aria-hidden="true" className="grid size-12 shrink-0 place-items-center rounded-lg border bg-muted text-xs text-muted-foreground">{p.name.slice(0, 1)}</span>
                )}
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <h2 className="truncate font-semibold">
                      {p.name} {!p.is_active && <span className="text-xs text-muted-foreground">{tr("(hidden)")}</span>}
                    </h2>
                    <span className="shrink-0 text-sm">{p.base_price}</span>
                  </div>
                  <p className="truncate text-sm text-muted-foreground">
                    {[p.brand, p.category].filter(Boolean).join(" · ") || p.slug}
                  </p>
                  {canManage && p.is_active && p.price_rules_count === 0 && (
                    <p className="mt-1 text-xs font-medium text-amber-700">{tr("Not for sale yet: add a price")}</p>
                  )}
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
