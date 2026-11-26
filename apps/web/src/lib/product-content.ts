import type { PageTemplate } from "@/lib/product-templates";

/** The content a product page can carry. Stored with the product (attributes.page), shown by the layouts that use it. */
export interface PageContent {
  headline?: string;
  steps?: { title: string }[];
  faqs?: { q: string; a: string }[];
  specs?: { label: string; value: string }[];
  tiers?: { name: string; text?: string }[];
  addon_ids?: number[];
  free_delivery_from?: number | string | null;
  warranty_terms?: string;
  lead_time?: string;
  quote_note?: string;
}

export type ContentField = keyof PageContent;

/** Which content each layout asks for: choosing a layout opens exactly these fields. */
export const TEMPLATE_FIELDS: Record<PageTemplate, ContentField[]> = {
  classic: ["addon_ids", "faqs"],
  tiers: ["tiers", "faqs"],
  builder: ["specs", "faqs"],
  story: ["headline", "steps", "faqs"],
  compare: ["specs", "faqs"],
  marketplace: ["addon_ids", "specs", "faqs"],
  bundle: ["addon_ids", "free_delivery_from", "faqs"],
  warranty: ["warranty_terms", "faqs"],
  quote: ["lead_time", "quote_note", "faqs"],
  immersive: ["headline", "specs", "faqs"],
};

/** The content to send: empty rows and empty texts are dropped, so clearing a field really clears it. */
export function cleanContent(c: PageContent): PageContent {
  const out: PageContent = {};
  const text = (v?: string) => (v ?? "").trim();
  if (text(c.headline)) out.headline = text(c.headline);
  if (text(c.warranty_terms)) out.warranty_terms = text(c.warranty_terms);
  if (text(c.lead_time)) out.lead_time = text(c.lead_time);
  if (text(c.quote_note)) out.quote_note = text(c.quote_note);
  const steps = (c.steps ?? []).map((s) => ({ title: text(s.title) })).filter((s) => s.title);
  if (steps.length) out.steps = steps;
  const faqs = (c.faqs ?? []).map((f) => ({ q: text(f.q), a: text(f.a) })).filter((f) => f.q && f.a);
  if (faqs.length) out.faqs = faqs;
  const specs = (c.specs ?? []).map((s) => ({ label: text(s.label), value: text(s.value) })).filter((s) => s.label && s.value);
  if (specs.length) out.specs = specs;
  const tiers = (c.tiers ?? []).map((t) => ({ name: text(t.name), text: text(t.text) })).filter((t) => t.name);
  if (tiers.length) out.tiers = tiers.map((t) => (t.text ? t : { name: t.name }));
  if (c.addon_ids?.length) out.addon_ids = c.addon_ids;
  if (c.free_delivery_from !== undefined && c.free_delivery_from !== null && String(c.free_delivery_from) !== "") out.free_delivery_from = Number(c.free_delivery_from);
  return out;
}
