"use client";

import { useCallback, useEffect, useRef, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { orderSections, type SectionKey, type SiteContent } from "@/lib/surfaces";
import { imageSrc } from "@/lib/surfaces";
import { getMedia, getSurfaceDetail, publishSurface, saveSurfaceDraft, uploadImage, type MediaImage } from "@/services/admin.service";

type Values = Record<string, unknown>;

const SECTIONS: Record<SectionKey, { label: string; flag?: string }> = {
  partner_strip: { label: "Trusted partners strip", flag: "show_partner_strip" },
  industries: { label: "Industries and products", flag: "show_industries" },
  stores: { label: "ArmorHub Markets" },
  quote_feature: { label: "Compare quotes section", flag: "show_quote_feature" },
  dashboard_feature: { label: "For businesses section", flag: "show_dashboard_feature" },
  value_props: { label: "Three promises", flag: "show_value_props" },
  partners: { label: "Partners by industry", flag: "show_partners" },
  use_cases: { label: "Use cases", flag: "show_use_cases" },
  why: { label: "Why choose us", flag: "show_why" },
  how: { label: "How it works", flag: "show_how" },
  bento: { label: "Numbers band", flag: "show_bento" },
  developers: { label: "For developers", flag: "show_developers" },
  compat: { label: "Works with your tools", flag: "show_compat" },
  markets: { label: "Local from day one", flag: "show_markets" },
  faq: { label: "FAQ", flag: "show_faq" },
};

const FONTS: [string, string][] = [["", "Built-in"], ["system", "System"], ["serif", "Serif"], ["rounded", "Rounded"], ["mono", "Monospace"]];
const DEVICES = { desktop: { label: "Desktop", width: "100%" }, tablet: { label: "Tablet", width: "768px" }, mobile: { label: "Mobile", width: "390px" } } as const;
const TEXTS: [string, string, boolean][] = [
  ["badge_text", "Badge above the headline", false], ["hero_title", "Headline", false], ["hero_subtitle", "Sub-headline", true], ["primary_cta", "Main button text", false],
  ["banner_title", "Closing banner: title", false], ["banner_text", "Closing banner: text", true], ["banner_cta", "Closing banner: button", false],
];

/** A Shopify-style visual editor on top of governed surfaces: change it, watch the real page react, publish when happy. */
export default function ThemeEditorPage() {
  const [home, setHome] = useState<Values | null>(null);
  const [brand, setBrand] = useState<Values>({});
  const [homeAr, setHomeAr] = useState<Values>({});
  const [tab, setTab] = useState<"sections" | "style" | "images" | "text">("sections");
  const [device, setDevice] = useState<keyof typeof DEVICES>("desktop");
  const [note, setNote] = useState("");
  const [msg, setMsg] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [forbidden, setForbidden] = useState(false);
  const [library, setLibrary] = useState<MediaImage[]>([]);
  const loadLibrary = useCallback(async () => { setLibrary(await getMedia().catch(() => [])); }, []);
  const frame = useRef<HTMLIFrameElement>(null);
  const ready = useRef(false);

  useEffect(() => {
    let live = true;
    getMedia().then((m) => { if (live) setLibrary(m); }).catch(() => undefined);

    return () => { live = false; };
  }, []);

  useEffect(() => {
    Promise.all([getSurfaceDetail("site.home", "en"), getSurfaceDetail("site.brand", "en"), getSurfaceDetail("site.home", "ar")])
      .then(([h, b, a]) => { setHome((h.draft ?? h.published) as Values); setBrand((b.draft ?? b.published) as Values); setHomeAr((a.draft ?? a.published) as Values); })
      .catch(() => setForbidden(true));
  }, []);

  const [selected, setSelected] = useState<string | null>(null);
  const push = useCallback(() => {
    if (!ready.current || !home) return;
    const site: SiteContent = { home: { en: home, ar: homeAr }, brand: { en: brand, ar: brand }, nav: {} };
    frame.current?.contentWindow?.postMessage({ type: "theme-draft", site }, window.location.origin);
  }, [home, brand, homeAr]);

  useEffect(() => {
    const onMessage = (e: MessageEvent) => {
      if (e.origin !== window.location.origin) return;
      const data = e.data as { type?: string; field?: string | null; block?: string | null } | null;
      if (data?.type === "theme-preview-ready") { ready.current = true; push(); }
      if (data?.type === "theme-click") {
        // Text on the page opens its text field; anything else opens its place in the section list.
        if (data.field && TEXTS.some(([k]) => k === data.field)) {
          setTab("text");
          setTimeout(() => document.getElementById(`t-${data.field}`)?.focus(), 50);
        } else if (data.block) {
          setTab("sections");
          setSelected(data.block);
          setTimeout(() => document.querySelector(`[data-section="${data.block}"]`)?.scrollIntoView({ block: "nearest" }), 50);
        }
      }
    };
    window.addEventListener("message", onMessage);

    return () => window.removeEventListener("message", onMessage);
  }, [push]);

  useEffect(() => { push(); }, [push]);

  if (forbidden) return <EmptyState>Only platform admins can open this page.</EmptyState>;
  if (!home) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  const order = orderSections(((home.section_order as { section: string }[] | undefined) ?? []).map((r) => r.section));
  const setHomeValue = (k: string, v: unknown) => setHome((h) => ({ ...(h ?? {}), [k]: v }));
  const setBrandValue = (k: string, v: unknown) => setBrand((b) => ({ ...b, [k]: v }));
  const move = (i: number, d: -1 | 1) => {
    const next = [...order];
    [next[i], next[i + d]] = [next[i + d], next[i]];
    setHomeValue("section_order", next.map((section) => ({ section })));
  };

  async function run(action: () => Promise<unknown>, done: string) {
    setError(null); setMsg(null);
    try {
      await action();
      setMsg(done);
    } catch (e) {
      setError(errorMessage(e));
    }
  }
  const saveBoth = async () => { await saveSurfaceDraft("site.home", "en", home as Values); await saveSurfaceDraft("site.brand", "en", brand); };
  const colour = (key: string, fallback: string) => (typeof brand[key] === "string" && brand[key] ? (brand[key] as string) : fallback);

  return (
    <div>
      <PageHeader title="Theme editor" />
      <div className="grid gap-4 lg:grid-cols-[360px_1fr]">
        <div className="space-y-4">
          <div className="flex flex-wrap gap-1" role="tablist">
            {([["sections", "Sections"], ["style", "Colors and fonts"], ["images", "Images"], ["text", "Text"]] as const).map(([k, l]) => (
              <Button key={k} size="sm" variant={tab === k ? "default" : "outline"} role="tab" aria-selected={tab === k} onClick={() => setTab(k)}>{tr(l)}</Button>
            ))}
          </div>

          {tab === "sections" && (
            <ul className="space-y-1" aria-label={tr("Sections")}>
              <li className="rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">{tr("Hero (always first)")}</li>
              {order.map((key, i) => {
                const spec = SECTIONS[key];
                const visible = spec.flag ? home[spec.flag] !== false : true;
                return (
                  <li key={key} data-section={key} data-selected={selected === key} className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm ${selected === key ? "ring-2 ring-blue-500" : ""}`}>
                    <span className={`flex-1 ${visible ? "" : "text-muted-foreground line-through"}`}>{tr(spec.label)}</span>
                    <Button size="sm" variant="outline" disabled={i === 0} aria-label={`${tr("Move up")} ${tr(spec.label)}`} onClick={() => move(i, -1)}>↑</Button>
                    <Button size="sm" variant="outline" disabled={i === order.length - 1} aria-label={`${tr("Move down")} ${tr(spec.label)}`} onClick={() => move(i, 1)}>↓</Button>
                    {spec.flag && <Button size="sm" variant="outline" aria-label={`${visible ? tr("Hide") : tr("Show")} ${tr(spec.label)}`} onClick={() => setHomeValue(spec.flag!, !visible)}>{visible ? tr("Hide") : tr("Show")}</Button>}
                  </li>
                );
              })}
              <li className="rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">{tr("Closing banner (always last)")}</li>
            </ul>
          )}

          {tab === "style" && (
            <div className="space-y-4">
              {([["brand_color", "Accent colour (buttons)", "#9fe870"], ["dark_color", "Dark colour (panels, footer)", "#163300"]] as const).map(([k, l, d]) => (
                <div key={k}>
                  <label htmlFor={`c-${k}`} className="mb-1 block text-sm font-medium">{tr(l)}</label>
                  <div className="flex items-center gap-2">
                    <input type="color" aria-label={tr(l)} value={colour(k, d)} onChange={(e) => setBrandValue(k, e.target.value)} className="h-10 w-14 rounded border" />
                    <Input id={`c-${k}`} value={colour(k, d)} maxLength={7} onChange={(e) => setBrandValue(k, e.target.value)} />
                    <Button size="sm" variant="outline" onClick={() => setBrandValue(k, "")}>{tr("Reset")}</Button>
                  </div>
                </div>
              ))}
              {([["font_heading", "Headline font"], ["font_body", "Text font"]] as const).map(([k, l]) => (
                <div key={k}>
                  <label htmlFor={`f-${k}`} className="mb-1 block text-sm font-medium">{tr(l)}</label>
                  <select id={`f-${k}`} className="h-10 w-full rounded-[10px] border border-input bg-transparent px-2.5 text-sm" value={(brand[k] as string) ?? ""} onChange={(e) => setBrandValue(k, e.target.value)}>
                    {FONTS.map(([v, n]) => <option key={v} value={v}>{tr(n)}</option>)}
                  </select>
                </div>
              ))}
            </div>
          )}

          {tab === "images" && (
            <div className="space-y-4">
              {([["hero_image_url", "Hero image", "home"], ["banner_image_url", "Closing banner background image", "home"], ["logo_url", "Logo", "brand"]] as const).map(([k, l, where]) => {
                const value = ((where === "home" ? home : brand)[k] as string) ?? "";
                const set = (v: string) => (where === "home" ? setHomeValue(k, v) : setBrandValue(k, v));
                return (
                  <div key={k} className="space-y-2 rounded-lg border p-3">
                    <label htmlFor={`i-${k}`} className="block text-sm font-medium">{tr(l)}</label>
                    {value && (
                      // eslint-disable-next-line @next/next/no-img-element -- an admin-chosen image shown as a thumbnail
                      <img src={imageSrc(value) ?? ""} alt="" className="h-20 w-auto max-w-full rounded border object-contain" />
                    )}
                    <Input id={`i-${k}`} placeholder="https://" value={value} onChange={(e) => set(e.target.value)} />
                    <div className="flex flex-wrap gap-2">
                      <label className="inline-flex cursor-pointer items-center rounded-lg border px-3 py-1.5 text-sm font-medium hover:bg-muted">
                        {tr("Upload an image")}
                        <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" aria-label={`${tr("Upload an image")} ${tr(l)}`} className="sr-only"
                          onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ""; if (f) run(async () => { set(await uploadImage(f)); await loadLibrary(); }, tr("Image uploaded.")); }} />
                      </label>
                      <Button size="sm" variant="outline" onClick={() => set("")}>{tr("Remove")}</Button>
                    </div>
                  </div>
                );
              })}
              {library.length > 0 && (
                <div>
                  <p className="mb-1 text-sm font-medium">{tr("Uploaded images")}</p>
                  <div className="grid grid-cols-4 gap-2">
                    {library.map((m) => (
                      // eslint-disable-next-line @next/next/no-img-element -- thumbnails of the admin's own uploads
                      <img key={m.path} src={imageSrc(m.path) ?? ""} alt={m.original_name} title={m.original_name} className="h-16 w-full rounded border object-cover" />
                    ))}
                  </div>
                </div>
              )}
              <p className="text-xs text-muted-foreground">{tr("Upload a JPG, PNG, WebP or GIF up to 3 MB, or paste an https address.")}</p>
            </div>
          )}

          {tab === "text" && (
            <div className="space-y-3">
              {TEXTS.map(([k, l, long]) => (
                <div key={k}>
                  <label htmlFor={`t-${k}`} className="mb-1 block text-sm font-medium">{tr(l)}</label>
                  {long
                    ? <textarea id={`t-${k}`} rows={3} className="w-full rounded-[10px] border border-input bg-transparent px-2.5 py-2 text-sm" value={(home[k] as string) ?? ""} onChange={(e) => setHomeValue(k, e.target.value)} />
                    : <Input id={`t-${k}`} value={(home[k] as string) ?? ""} onChange={(e) => setHomeValue(k, e.target.value)} />}
                </div>
              ))}
              <p className="text-xs text-muted-foreground">{tr("This edits the English text. Arabic text and everything else is under Content.")}</p>
            </div>
          )}

          {error && <p className="text-sm text-red-500" role="alert">{error}</p>}
          {msg && <p className="text-sm text-green-700" role="status">{msg}</p>}
          <div className="flex flex-wrap items-center gap-2">
            <Button variant="outline" onClick={() => run(saveBoth, tr("Draft saved."))}>{tr("Save draft")}</Button>
            <Input className="max-w-[10rem]" placeholder={tr("Note (what changed)")} value={note} maxLength={200} onChange={(e) => setNote(e.target.value)} />
            <Button onClick={() => run(async () => { await saveBoth(); await publishSurface("site.home", "en", note); await publishSurface("site.brand", "en", note); setNote(""); }, tr("Published."))}>{tr("Publish")}</Button>
          </div>
          <p className="text-xs text-muted-foreground">{tr("Nothing goes live until you publish. Earlier versions can be restored under Content.")}</p>
        </div>

        <div>
          <div className="mb-2 flex gap-1" role="group" aria-label={tr("Preview size")}>
            {(Object.keys(DEVICES) as (keyof typeof DEVICES)[]).map((d) => (
              <Button key={d} size="sm" variant={device === d ? "default" : "outline"} aria-pressed={device === d} onClick={() => setDevice(d)}>{tr(DEVICES[d].label)}</Button>
            ))}
          </div>
          <div className="overflow-auto rounded-xl border bg-muted/30 p-2">
            <iframe ref={frame} title={tr("Live preview")} src="/theme-preview" style={{ width: DEVICES[device].width, maxWidth: "100%" }} className="mx-auto block h-[75vh] rounded-lg border bg-white" />
          </div>
        </div>
      </div>
    </div>
  );
}
