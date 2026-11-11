"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { hasRole } from "@/lib/navigation";
import { getProducts, getStorefront, saveStorefront, type StorefrontSettings } from "@/services/platform.service";
import type { Product } from "@/types/platform";

export default function StorefrontPage() {
  return (
    <Suspense>
      <StorefrontEditor />
    </Suspense>
  );
}

/** What visitors see on this company's public store: address, wording, colour, logo, featured products. */
function StorefrontEditor() {
  const user = useCurrentUser();
  const asked = Number(useSearchParams().get("company")) || null;
  // Owners edit their own company; admins pick a company from the Companies page.
  const companyId = hasRole(user, "platform-admin") ? asked : (user?.company_id ?? null);

  const [form, setForm] = useState<StorefrontSettings | null>(null);
  const [products, setProducts] = useState<Product[]>([]);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (!companyId) return;
    getStorefront(companyId).then(setForm).catch(() => setFailed(true));
    getProducts().then(setProducts).catch(() => undefined);
  }, [companyId]);

  if (!companyId) return <EmptyState>Open this page from the Companies list to choose a company.</EmptyState>;
  if (failed) return <EmptyState>You cannot edit this storefront.</EmptyState>;
  if (!form) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  const set = <K extends keyof StorefrontSettings>(key: K, value: StorefrontSettings[K]) => {
    setMessage(null);
    setForm((f) => (f ? { ...f, [key]: value } : f));
  };

  async function save(e: React.FormEvent) {
    e.preventDefault();
    if (!form || !companyId) return;
    try {
      const { path: _path, ...data } = form;
      void _path;
      setForm(await saveStorefront(companyId, data));
      setMessage({ ok: true, text: tr("Saved") });
    } catch (err) {
      setMessage({ ok: false, text: errorMessage(err) });
    }
  }

  const toggleFeatured = (id: number) =>
    set("featured_product_ids", form.featured_product_ids.includes(id) ? form.featured_product_ids.filter((x) => x !== id) : [...form.featured_product_ids, id].slice(0, 8));

  return (
    <div className="max-w-3xl">
      <PageHeader
        title="Storefront"
        action={form.enabled && <Link href={form.path} target="_blank" className="rounded-lg border px-4 py-2 text-sm hover:bg-muted">{tr("View storefront")}</Link>}
      />

      <form onSubmit={save} className="space-y-5 rounded-lg border p-5">
        <label className="flex items-center gap-2 font-medium">
          <input id="sf-enabled" type="checkbox" checked={form.enabled} onChange={(e) => set("enabled", e.target.checked)} />
          {tr("Your storefront is public")}
        </label>

        <div>
          <label htmlFor="sf-slug" className="font-medium">{tr("Address")}</label>
          <div className="flex items-center gap-2" dir="ltr">
            <span className="text-muted-foreground">/s/</span>
            <Input id="sf-slug" value={form.slug} onChange={(e) => set("slug", e.target.value.toLowerCase())} />
          </div>
          <p className="text-xs text-muted-foreground">{tr("Lowercase letters, numbers and dashes. Changing it breaks old links.")}</p>
        </div>

        <div>
          <label htmlFor="sf-headline" className="font-medium">{tr("Headline")}</label>
          <Input id="sf-headline" maxLength={120} value={form.headline ?? ""} onChange={(e) => set("headline", e.target.value)} />
        </div>
        <div>
          <label htmlFor="sf-subheadline" className="font-medium">{tr("Subheadline")}</label>
          <Input id="sf-subheadline" maxLength={240} value={form.subheadline ?? ""} onChange={(e) => set("subheadline", e.target.value)} />
        </div>
        <div>
          <label htmlFor="sf-about" className="font-medium">{tr("About us")}</label>
          <textarea id="sf-about" rows={4} maxLength={2000} className="w-full rounded-lg border px-3 py-2" value={form.about ?? ""} onChange={(e) => set("about", e.target.value)} />
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <div>
            <label htmlFor="sf-color" className="font-medium">{tr("Brand colour")}</label>
            <div className="flex items-center gap-2">
              <input aria-label={tr("Pick a colour")} type="color" className="h-10 w-12 cursor-pointer rounded border" value={form.primary_color} onChange={(e) => set("primary_color", e.target.value)} />
              <Input id="sf-color" dir="ltr" value={form.primary_color} onChange={(e) => set("primary_color", e.target.value)} />
            </div>
          </div>
          <div>
            <label htmlFor="sf-logo" className="font-medium">{tr("Logo address (https)")}</label>
            <Input id="sf-logo" dir="ltr" placeholder="https://" value={form.logo_url ?? ""} onChange={(e) => set("logo_url", e.target.value)} />
          </div>
          <div>
            <label htmlFor="sf-email" className="font-medium">{tr("Public contact email")}</label>
            <Input id="sf-email" dir="ltr" type="email" value={form.contact_email ?? ""} onChange={(e) => set("contact_email", e.target.value)} />
          </div>
          <div>
            <label htmlFor="sf-phone" className="font-medium">{tr("Public contact phone")}</label>
            <Input id="sf-phone" dir="ltr" value={form.contact_phone ?? ""} onChange={(e) => set("contact_phone", e.target.value)} />
          </div>
        </div>

        <fieldset>
          <legend className="mb-2 font-medium">{tr("Featured products (up to 8)")}</legend>
          {products.length === 0 ? (
            <p className="text-sm text-muted-foreground">{tr("No products found.")}</p>
          ) : (
            <div className="grid gap-2 sm:grid-cols-2">
              {products.filter((p) => p.is_active).map((p) => (
                <label key={p.id} className="flex items-center gap-2 rounded-lg border p-2 text-sm">
                  <input type="checkbox" checked={form.featured_product_ids.includes(p.id)} onChange={() => toggleFeatured(p.id)} />
                  {p.name}
                </label>
              ))}
            </div>
          )}
          <p className="mt-1 text-xs text-muted-foreground">{tr("Leave empty to show your first products automatically.")}</p>
        </fieldset>

        {message && <p className={`text-sm ${message.ok ? "text-green-600" : "text-red-500"}`}>{message.text}</p>}
        <Button type="submit">{tr("Save storefront")}</Button>
      </form>
    </div>
  );
}
