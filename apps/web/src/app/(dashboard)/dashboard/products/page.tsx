"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { hasRole } from "@/lib/navigation";
import { createProduct, getProducts } from "@/services/platform.service";
import type { Product } from "@/types/platform";

function slugify(value: string) {
  return value.toLowerCase().trim().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
}

export default function ProductsPage() {
  const user = useCurrentUser();
  const [products, setProducts] = useState<Product[] | null>(null);
  const [search, setSearch] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [price, setPrice] = useState("");
  const [error, setError] = useState<string | null>(null);

  const canManage = hasRole(user, "platform-admin", "company-owner");

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
        slug: slugify(name),
        category: category || null,
        base_price: price,
        industry_id: user?.industry?.id ?? null,
      });
      setProducts((prev) => [...(prev ?? []), product]);
      setName("");
      setCategory("");
      setPrice("");
      setShowForm(false);
    } catch {
      setError("Could not save the product. Check the fields and try again.");
    }
  }

  return (
    <div>
      <PageHeader
        title="Products"
        action={canManage && <Button onClick={() => setShowForm((v) => !v)}>Add product</Button>}
      />

      {showForm && (
        <form onSubmit={onSubmit} className="mb-6 space-y-4 rounded-lg border p-4">
          <div>
            <Label htmlFor="name">Name</Label>
            <Input id="name" required value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="category">Category</Label>
            <Input id="category" value={category} onChange={(e) => setCategory(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="price">Base price</Label>
            <Input id="price" type="number" min="0" step="0.01" required value={price} onChange={(e) => setPrice(e.target.value)} />
          </div>
          {error && <p className="text-sm text-red-500">{error}</p>}
          <Button type="submit">Save</Button>
        </form>
      )}

      <Input
        className="mb-4 max-w-sm"
        placeholder="Search products"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />

      {products === null ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : products.length === 0 ? (
        <EmptyState>No products found.</EmptyState>
      ) : (
        <div className="space-y-3">
          {products.map((p) => (
            <Link
              key={p.id}
              href={`/dashboard/products/${p.id}`}
              className="block rounded-lg border p-4 hover:bg-muted"
            >
              <div className="flex items-center justify-between">
                <h2 className="font-semibold">
                  {p.name} {!p.is_active && <span className="text-xs text-muted-foreground">(hidden)</span>}
                </h2>
                <span className="text-sm">{p.base_price}</span>
              </div>
              <p className="text-sm text-muted-foreground">
                {[p.brand, p.category].filter(Boolean).join(" · ") || p.slug}
              </p>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
