"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { createCategory, getAdminCategories, updateCategory, type CategoryRow } from "@/services/platform.service";

/** The platform's product taxonomy: every seller files products under these (English and Arabic names). */
export default function CategoriesPage() {
  const [rows, setRows] = useState<CategoryRow[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [form, setForm] = useState({ key: "", name_en: "", name_ar: "", parent_id: "" });
  const [error, setError] = useState<string | null>(null);

  const load = () => getAdminCategories().then(setRows).catch(() => { setForbidden(true); setRows([]); });
  useEffect(() => { load(); }, []);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await createCategory({ key: form.key, name_en: form.name_en, name_ar: form.name_ar, parent_id: form.parent_id ? Number(form.parent_id) : null });
      setForm({ key: "", name_en: "", name_ar: "", parent_id: "" });
      await load();
    } catch (err) { setError(errorMessage(err)); }
  }

  async function toggle(c: CategoryRow) {
    setError(null);
    try { await updateCategory(c.id, { is_active: !c.is_active }); await load(); } catch (err) { setError(errorMessage(err)); }
  }

  if (forbidden) return <EmptyState>{tr("Only platform admins can open this page.")}</EmptyState>;
  if (!rows) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  const Row = ({ c, child }: { c: CategoryRow; child?: boolean }) => (
    <li className={`flex flex-wrap items-center justify-between gap-2 py-2 ${child ? "ps-6" : "font-semibold"}`}>
      <span>{c.name_en} <span className="text-muted-foreground" dir="rtl">· {c.name_ar}</span> <code className="text-xs text-muted-foreground">{c.key}</code></span>
      <span className="flex items-center gap-2">
        <Badge variant="outline">{tr("{n} products", { n: c.products })}</Badge>
        {!c.is_active && <Badge variant="secondary">{tr("Hidden")}</Badge>}
        <Button size="sm" variant="outline" onClick={() => toggle(c)}>{tr(c.is_active ? "Hide" : "Show")}</Button>
      </span>
    </li>
  );

  return (
    <div className="space-y-6">
      <PageHeader title="Product categories" />
      <p className="text-sm text-muted-foreground">{tr("Sellers pick a category from this list when they add a product. Add one here when the platform needs it.")}</p>
      {error && <p className="text-sm text-red-600" role="alert">{error}</p>}

      <ul className="divide-y rounded-xl border px-4" data-testid="category-list">
        {rows.map((g) => (
          <li key={g.id} className="list-none">
            <ul><Row c={g} />{(g.children ?? []).map((c) => <Row key={c.id} c={c} child />)}</ul>
          </li>
        ))}
      </ul>

      <form onSubmit={add} className="grid gap-3 rounded-xl border p-4 md:grid-cols-5">
        <div><Label htmlFor="cat-key">{tr("Key (lowercase, dashes)")}</Label><Input id="cat-key" required dir="ltr" value={form.key} onChange={(e) => setForm({ ...form, key: e.target.value })} /></div>
        <div><Label htmlFor="cat-en">{tr("English name")}</Label><Input id="cat-en" required value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} /></div>
        <div><Label htmlFor="cat-ar">{tr("Arabic name")}</Label><Input id="cat-ar" required dir="rtl" value={form.name_ar} onChange={(e) => setForm({ ...form, name_ar: e.target.value })} /></div>
        <div>
          <Label htmlFor="cat-parent">{tr("Group")}</Label>
          <select id="cat-parent" className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={form.parent_id} onChange={(e) => setForm({ ...form, parent_id: e.target.value })}>
            <option value="">{tr("New top-level group")}</option>
            {rows.map((g) => <option key={g.id} value={g.id}>{g.name_en}</option>)}
          </select>
        </div>
        <div className="flex items-end"><Button type="submit">{tr("Add category")}</Button></div>
      </form>
    </div>
  );
}
