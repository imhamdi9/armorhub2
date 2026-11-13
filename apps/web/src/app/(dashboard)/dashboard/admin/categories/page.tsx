"use client";

import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { createCategory, getAdminCategories, isProposed, updateCategory, type CategoryRow } from "@/services/platform.service";

const SENT = "Sent to the platform admin for approval. It goes live once approved.";

/** Every category of the tree, flattened with its path, for the "move under" list. */
function flatten(rows: CategoryRow[], trail: string[] = []): { row: CategoryRow; path: string }[] {
  return rows.flatMap((r) => [{ row: r, path: [...trail, r.name_en].join(" › ") }, ...flatten(r.children ?? [], [...trail, r.name_en])]);
}
const branchIds = (r: CategoryRow): number[] => [r.id, ...(r.children ?? []).flatMap(branchIds)];

function Node({ c, depth, all, onDone, onMsg }: { c: CategoryRow; depth: number; all: CategoryRow[]; onDone: () => void; onMsg: (m: string) => void }) {
  const [mode, setMode] = useState<"" | "edit" | "add">("");
  const [en, setEn] = useState(c.name_en);
  const [ar, setAr] = useState(c.name_ar ?? "");
  const [parent, setParent] = useState(c.parent_id ? String(c.parent_id) : "");
  const [subEn, setSubEn] = useState("");
  const [subAr, setSubAr] = useState("");

  const run = async (fn: () => Promise<unknown>) => {
    try {
      const r = await fn();
      if (isProposed(r)) onMsg(tr(SENT));
      setMode(""); setSubEn(""); setSubAr("");
      onDone();
    } catch (e) { onMsg(errorMessage(e)); }
  };
  const blocked = flatten(all).filter(({ row }) => !branchIds(c).includes(row.id));

  return (
    <li>
      <div className="flex flex-wrap items-center justify-between gap-2 py-2" style={{ paddingInlineStart: depth * 24 }}>
        <span className={depth === 0 ? "font-semibold" : ""}>
          {c.name_en} <span className="text-muted-foreground" dir="rtl">· {c.name_ar}</span> <code className="text-xs text-muted-foreground">{c.key}</code>
        </span>
        <span className="flex flex-wrap items-center gap-2">
          <Badge variant="outline">{tr("{n} products", { n: c.products })}</Badge>
          {!c.is_active && <Badge variant="secondary">{tr("Hidden")}</Badge>}
          <Button size="sm" variant="outline" onClick={() => setMode(mode === "add" ? "" : "add")}>{tr("Add sub-category")}</Button>
          <Button size="sm" variant="outline" onClick={() => setMode(mode === "edit" ? "" : "edit")}>{tr("Rename or move")}</Button>
          <Button size="sm" variant="outline" disabled={c.is_active && c.products > 0} title={c.is_active && c.products > 0 ? tr("Products still use this category. Move them to another one first.") : undefined}
            onClick={() => run(() => updateCategory(c.id, { is_active: !c.is_active }))}>{tr(c.is_active ? "Hide" : "Show")}</Button>
        </span>
      </div>

      {mode === "edit" && (
        <form className="mb-2 grid gap-2 rounded-lg border p-3 md:grid-cols-4" style={{ marginInlineStart: depth * 24 }}
          onSubmit={(e) => { e.preventDefault(); run(() => updateCategory(c.id, { name_en: en, name_ar: ar, parent_id: parent ? Number(parent) : null })); }}>
          <div><Label htmlFor={`en-${c.id}`}>{tr("English name")}</Label><Input id={`en-${c.id}`} required value={en} onChange={(e) => setEn(e.target.value)} /></div>
          <div><Label htmlFor={`ar-${c.id}`}>{tr("Arabic name")}</Label><Input id={`ar-${c.id}`} required dir="rtl" value={ar} onChange={(e) => setAr(e.target.value)} /></div>
          <div>
            <Label htmlFor={`pa-${c.id}`}>{tr("Place it under")}</Label>
            <select id={`pa-${c.id}`} className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={parent} onChange={(e) => setParent(e.target.value)}>
              <option value="">{tr("Nothing (top level)")}</option>
              {blocked.map(({ row, path }) => <option key={row.id} value={row.id}>{path}</option>)}
            </select>
          </div>
          <div className="flex items-end"><Button size="sm" type="submit">{tr("Save")}</Button></div>
        </form>
      )}
      {mode === "add" && (
        <form className="mb-2 grid gap-2 rounded-lg border p-3 md:grid-cols-3" style={{ marginInlineStart: depth * 24 }}
          onSubmit={(e) => { e.preventDefault(); run(() => createCategory({ name_en: subEn, name_ar: subAr, parent_id: c.id })); }}>
          <div><Label htmlFor={`sen-${c.id}`}>{tr("English name")}</Label><Input id={`sen-${c.id}`} required value={subEn} onChange={(e) => setSubEn(e.target.value)} /></div>
          <div><Label htmlFor={`sar-${c.id}`}>{tr("Arabic name")}</Label><Input id={`sar-${c.id}`} required dir="rtl" value={subAr} onChange={(e) => setSubAr(e.target.value)} /></div>
          <div className="flex items-end"><Button size="sm" type="submit">{tr("Add sub-category")}</Button></div>
        </form>
      )}

      {(c.children ?? []).length > 0 && <ul>{c.children!.map((k) => <Node key={k.id} c={k} depth={depth + 1} all={all} onDone={onDone} onMsg={onMsg} />)}</ul>}
    </li>
  );
}

/** The platform's product categories: any depth, renamed or moved at will. A market admin's changes wait for approval. */
export default function CategoriesPage() {
  const [rows, setRows] = useState<CategoryRow[] | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [tick, setTick] = useState(0);
  const [form, setForm] = useState({ name_en: "", name_ar: "", parent_id: "" });
  const [msg, setMsg] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    getAdminCategories().then((r) => { if (live) setRows(r); }).catch(() => { if (live) { setForbidden(true); setRows([]); } });
    return () => { live = false; };
  }, [tick]);

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setMsg(null);
    try {
      const r = await createCategory({ name_en: form.name_en, name_ar: form.name_ar, parent_id: form.parent_id ? Number(form.parent_id) : null });
      setForm({ name_en: "", name_ar: "", parent_id: "" });
      if (isProposed(r)) setMsg(tr(SENT));
      setTick((t) => t + 1);
    } catch (err) { setMsg(errorMessage(err)); }
  }

  if (forbidden) return <EmptyState>{tr("Only platform admins can open this page.")}</EmptyState>;
  if (!rows) return <div className="h-24 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />;

  return (
    <div className="space-y-6">
      <PageHeader title="Product categories" />
      <p className="text-sm text-muted-foreground">{tr("Sellers pick a category from this tree when they add a product. Add categories and sub-categories at any level, rename them, or move them under another one.")}</p>
      {msg && <p className="text-sm" role="status">{msg}</p>}

      <ul className="divide-y rounded-xl border px-4" data-testid="category-list">
        {rows.map((g) => <Node key={g.id} c={g} depth={0} all={rows} onDone={() => setTick((t) => t + 1)} onMsg={setMsg} />)}
      </ul>

      <form onSubmit={add} className="grid gap-3 rounded-xl border p-4 md:grid-cols-4">
        <div><Label htmlFor="cat-en">{tr("English name")}</Label><Input id="cat-en" required value={form.name_en} onChange={(e) => setForm({ ...form, name_en: e.target.value })} /></div>
        <div><Label htmlFor="cat-ar">{tr("Arabic name")}</Label><Input id="cat-ar" required dir="rtl" value={form.name_ar} onChange={(e) => setForm({ ...form, name_ar: e.target.value })} /></div>
        <div>
          <Label htmlFor="cat-parent">{tr("Place it under")}</Label>
          <select id="cat-parent" className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm" value={form.parent_id} onChange={(e) => setForm({ ...form, parent_id: e.target.value })}>
            <option value="">{tr("Nothing (top level)")}</option>
            {flatten(rows).map(({ row, path }) => <option key={row.id} value={row.id}>{path}</option>)}
          </select>
        </div>
        <div className="flex items-end"><Button type="submit">{tr("Add category")}</Button></div>
      </form>
    </div>
  );
}
