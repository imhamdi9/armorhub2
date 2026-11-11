"use client";

import { useCallback, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage, fieldErrors } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import {
  getSurfaceDetail, getSurfaces, publishSurface, rollbackSurface, saveSurfaceDraft,
  type SurfaceDetail, type SurfaceField, type SurfaceInfo,
} from "@/services/admin.service";

type Values = Record<string, unknown>;

/** One input for one declared field. The server owns the rules; this only renders what the surface declares. */
function FieldInput({ name, spec, value, onChange, error }: { name: string; spec: SurfaceField; value: unknown; onChange: (v: unknown) => void; error?: string }) {
  const label = tr(spec.label ?? name);
  const id = `f-${name}`;

  if (spec.type === "bool") {
    return (
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" checked={value === undefined ? spec.default !== false : value === true} onChange={(e) => onChange(e.target.checked)} />
        {label}
      </label>
    );
  }

  if (spec.type === "list") {
    const rows = Array.isArray(value) ? (value as Values[]) : [];
    return (
      <div>
        <p className="mb-1 text-sm font-medium">{label}</p>
        <div className="space-y-3">
          {rows.map((row, i) => (
            <div key={i} className="space-y-2 rounded-lg border p-3">
              {Object.entries(spec.items ?? {}).map(([k, s]) => (
                <FieldInput key={k} name={`${name}-${i}-${k}`} spec={s} value={row[k]} onChange={(v) => onChange(rows.map((r, j) => (j === i ? { ...r, [k]: v } : r)))} />
              ))}
              <Button type="button" variant="outline" size="sm" onClick={() => onChange(rows.filter((_, j) => j !== i))}>{tr("Remove")}</Button>
            </div>
          ))}
          {rows.length < (spec.maxItems ?? 20) && <Button type="button" variant="outline" size="sm" onClick={() => onChange([...rows, {}])}>{tr("Add item")}</Button>}
        </div>
        {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
      </div>
    );
  }

  return (
    <div>
      <label htmlFor={id} className="mb-1 block text-sm font-medium">{label}</label>
      {spec.type === "longtext" ? (
        <textarea id={id} rows={3} maxLength={spec.max} className="w-full rounded-[10px] border border-input bg-transparent px-2.5 py-2 text-sm" value={(value as string) ?? ""} onChange={(e) => onChange(e.target.value)} />
      ) : spec.type === "select" ? (
        <select id={id} className="h-10 w-full rounded-[10px] border border-input bg-transparent px-2.5 text-sm" value={(value as string) ?? ""} onChange={(e) => onChange(e.target.value)}>
          <option value="">{tr("Built-in default")}</option>
          {(spec.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
        </select>
      ) : (
        <Input id={id} type={spec.type === "number" ? "number" : "text"} maxLength={spec.max} placeholder={spec.type === "color" ? "#163300" : spec.type === "url" ? "https://" : tr("Built-in default")} value={(value as string | number) ?? ""} onChange={(e) => onChange(e.target.value)} />
      )}
      {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
    </div>
  );
}

function Editor({ surface, locale }: { surface: SurfaceInfo; locale: string }) {
  const [detail, setDetail] = useState<SurfaceDetail | null>(null);
  const [values, setValues] = useState<Values>({});
  const [note, setNote] = useState("");
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    const d = await getSurfaceDetail(surface.key, locale);
    setDetail(d);
    setValues((d.draft ?? d.published) as Values);
  }, [surface.key, locale]);

  useEffect(() => {
    let live = true;
    getSurfaceDetail(surface.key, locale).then((d) => { if (live) { setDetail(d); setValues((d.draft ?? d.published) as Values); } }).catch((e) => { if (live) setError(errorMessage(e)); });

    return () => { live = false; };
  }, [surface.key, locale]);

  async function run(action: () => Promise<unknown>, done: string) {
    setError(null); setErrors({}); setMsg(null);
    try {
      await action();
      await load();
      setMsg(done);
    } catch (e) {
      setError(errorMessage(e));
      const f = fieldErrors(e);
      setErrors(Object.fromEntries(Object.entries(f).map(([k, v]) => [k.replace(/^values\./, ""), v])));
    }
  }

  if (!detail) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  // On/off switches apply to every language, so they are edited with the English content only.
  const fields = Object.entries(surface.fields).filter(([, s]) => locale === "en" || s.type !== "bool");

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        {detail.published_version ? `${tr("Live version")}: ${detail.published_version}` : tr("Nothing published yet. The built-in content is shown.")}
        {detail.has_draft && ` · ${tr("You have an unpublished draft.")}`}
      </p>
      {fields.map(([name, spec]) => (
        <FieldInput key={name} name={name} spec={spec} value={values[name]} error={errors[name]} onChange={(v) => setValues((cur) => ({ ...cur, [name]: v }))} />
      ))}
      {error && <p className="text-sm text-red-500" role="alert">{error}</p>}
      {msg && <p className="text-sm text-green-700" role="status">{msg}</p>}
      <div className="flex flex-wrap items-center gap-2">
        <Button variant="outline" onClick={() => run(() => saveSurfaceDraft(surface.key, locale, values), tr("Draft saved."))}>{tr("Save draft")}</Button>
        <Input className="max-w-xs" placeholder={tr("Note (what changed)")} value={note} maxLength={200} onChange={(e) => setNote(e.target.value)} />
        <Button onClick={() => run(async () => { await saveSurfaceDraft(surface.key, locale, values); await publishSurface(surface.key, locale, note); setNote(""); }, tr("Published."))}>{tr("Publish")}</Button>
      </div>

      {detail.history.length > 0 && (
        <div>
          <h3 className="mb-2 text-sm font-bold">{tr("History")}</h3>
          <ul className="space-y-1 text-sm">
            {detail.history.map((h) => (
              <li key={h.version} className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2">
                <span>v{h.version} · {h.status === "published" ? tr("live") : tr("earlier")}{h.note ? ` · ${h.note}` : ""}</span>
                {h.status !== "published" && <Button size="sm" variant="outline" onClick={() => run(() => rollbackSurface(surface.key, locale, h.version), tr("Restored."))}>{tr("Restore")}</Button>}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}

/** Everything a module declared editable. Changes go live only when published, and any version can be restored. */
export default function ContentPage() {
  const [surfaces, setSurfaces] = useState<SurfaceInfo[] | null>(null);
  const [active, setActive] = useState<string | null>(null);
  const [locale, setLocale] = useState("en");
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    getSurfaces().then((s) => { setSurfaces(s); setActive(s[0]?.key ?? null); }).catch(() => setForbidden(true));
  }, []);

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!surfaces) return <p className="text-muted-foreground">{tr("Loading...")}</p>;
  const surface = surfaces.find((s) => s.key === active);

  return (
    <div className="max-w-3xl">
      <PageHeader title="Content" />
      <p className="mb-4 text-sm text-muted-foreground">{tr("Edit the wording, menus and sections of the platform. Changes go live only when you publish, every version is kept, and you can restore an earlier one. Security and payment settings are not editable here.")}</p>
      <div className="mb-4 flex flex-wrap gap-2" role="tablist">
        {surfaces.map((s) => (
          <Button key={s.key} variant={s.key === active ? "default" : "outline"} size="sm" role="tab" aria-selected={s.key === active} onClick={() => setActive(s.key)}>{tr(s.label)}</Button>
        ))}
      </div>
      <div className="mb-4 flex gap-2">
        {(["en", "ar"] as const).map((l) => (
          <Button key={l} variant={l === locale ? "default" : "outline"} size="sm" onClick={() => setLocale(l)}>{l === "en" ? "English" : "العربية"}</Button>
        ))}
      </div>
      {surface && <><p className="mb-3 text-sm">{tr(surface.description)}</p><Editor key={`${surface.key}-${locale}`} surface={surface} locale={locale} /></>}
    </div>
  );
}
