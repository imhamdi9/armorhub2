"use client";

import { CONSENT_EVENT, readConsent, type ConsentDetail } from "@/components/layout/cookie-consent-banner";

/**
 * Advertising: the platform's own pixels (Meta, TikTok, Google), loaded only after the visitor accepts marketing cookies,
 * the campaign the visitor came from, and one tracking call for the whole site. Nothing here runs without consent.
 */
export interface MarketingConfig {
  meta_pixel_id: string | null; tiktok_pixel_id: string | null; ga4_id: string | null;
  google_ads_id: string | null; google_ads_purchase_label: string | null; google_ads_lead_label: string | null;
}

export type TrackName = "view_item" | "add_to_cart" | "begin_checkout" | "purchase" | "lead" | "search";
export interface TrackData { value?: number; currency?: string; id?: string | number; name?: string; ids?: (string | number)[]; query?: string; quantity?: number }

const KEY = "campaign";
const DAYS = 30;
const PARAMS = ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term", "fbclid", "ttclid", "gclid", "promo"] as const;

export const marketingAllowed = () => typeof window !== "undefined" && readConsent()?.decision === "accepted";

// ---- the campaign a visitor came from -------------------------------------------------------------------------------------------
type Campaign = Partial<Record<(typeof PARAMS)[number] | "landing" | "referrer" | "first_seen", string>>;

/** Keeps the campaign tags of the address for 30 days (a newer tagged visit replaces an older one). Does nothing without consent. */
export function captureCampaign(): void {
  if (!marketingAllowed()) return;
  try {
    const q = new URLSearchParams(window.location.search);
    const found: Campaign = {};
    for (const k of PARAMS) { const v = q.get(k); if (v) found[k] = v.slice(0, 200); }
    const saved = readCampaign();
    if (Object.keys(found).length > 0) {
      const next: Campaign = { ...found, landing: window.location.pathname, first_seen: new Date().toISOString() };
      if (document.referrer && !document.referrer.startsWith(window.location.origin)) next.referrer = new URL(document.referrer).hostname;
      window.localStorage.setItem(KEY, JSON.stringify(next));
    } else if (!saved && document.referrer && !document.referrer.startsWith(window.location.origin)) {
      window.localStorage.setItem(KEY, JSON.stringify({ referrer: new URL(document.referrer).hostname, landing: window.location.pathname, first_seen: new Date().toISOString() }));
    }
  } catch { /* storage unavailable */ }
}

function readCampaign(): Campaign | null {
  try {
    const raw = window.localStorage.getItem(KEY);
    const c = raw ? (JSON.parse(raw) as Campaign) : null;
    if (c?.first_seen && Date.now() - new Date(c.first_seen).getTime() > DAYS * 86_400_000) { window.localStorage.removeItem(KEY); return null; }

    return c;
  } catch { return null; }
}

export const savedCampaign = () => (typeof window === "undefined" ? null : readCampaign());

function cookie(name: string): string | undefined {
  return document.cookie.split("; ").find((c) => c.startsWith(`${name}=`))?.split("=").slice(1).join("=");
}

/** The X-Campaign header: what the server needs to credit the right campaign and to report the purchase. Only with consent. */
export function campaignHeader(): string | null {
  if (!marketingAllowed()) return null;
  const c = readCampaign() ?? {};
  const ga = cookie("_ga")?.split(".").slice(2).join(".");
  const body: Record<string, string | undefined> = {
    ...c, fbp: cookie("_fbp"), fbc: cookie("_fbc") ?? (c.fbclid ? `fb.1.${Date.now()}.${c.fbclid}` : undefined), ttp: cookie("_ttp"), ga_client_id: ga, source_url: window.location.href.split("#")[0],
  };

  return JSON.stringify(Object.fromEntries(Object.entries(body).filter(([, v]) => v)));
}

// ---- pixels -----------------------------------------------------------------------------------------------------------------------
type Fn = (...args: unknown[]) => void;
interface Win { fbq?: Fn; ttq?: { track: Fn; page: Fn; load: Fn }; gtag?: Fn; dataLayer?: unknown[]; __marketing?: { cfg: MarketingConfig; loaded: boolean } }
const w = () => window as unknown as Win;
const queue: [TrackName, TrackData, string | undefined][] = [];
let state: { cfg: MarketingConfig; loaded: boolean } | null = null;

function script(src: string) { const s = document.createElement("script"); s.async = true; s.src = src; document.head.appendChild(s); }

function loadPixels(cfg: MarketingConfig) {
  if (state?.loaded) return;
  state = { cfg, loaded: true };
  const win = w();

  if (cfg.meta_pixel_id) {
    const q: unknown[][] = [];
    const fbq = ((...a: unknown[]) => { (fbq as unknown as { queue: unknown[][] }).queue.push(a); }) as unknown as Fn & { queue: unknown[][]; loaded: boolean; version: string };
    fbq.queue = q; fbq.loaded = true; fbq.version = "2.0";
    win.fbq = fbq; script("https://connect.facebook.net/en_US/fbevents.js");
    win.fbq("init", cfg.meta_pixel_id); win.fbq("track", "PageView");
  }
  if (cfg.tiktok_pixel_id) {
    const calls: unknown[][] = [];
    const ttq = { track: (...a: unknown[]) => calls.push(["track", ...a]), page: () => calls.push(["page"]), load: (...a: unknown[]) => calls.push(["load", ...a]) };
    win.ttq = ttq as Win["ttq"];
    // The official snippet queues calls until the library arrives; this keeps the same names.
    (win as unknown as { TiktokAnalyticsObject: string }).TiktokAnalyticsObject = "ttq";
    script(`https://analytics.tiktok.com/i18n/pixel/events.js?sdkid=${encodeURIComponent(cfg.tiktok_pixel_id)}&lib=ttq`);
    win.ttq!.page();
  }
  if (cfg.ga4_id || cfg.google_ads_id) {
    win.dataLayer = win.dataLayer ?? [];
    // gtag.js only understands the Arguments object in the data layer, not an array: `arguments` is needed on purpose.
    // eslint-disable-next-line prefer-rest-params
    win.gtag = function () { (win.dataLayer as unknown[]).push(arguments); } as Fn;
    win.gtag("js", new Date());
    if (cfg.ga4_id) win.gtag("config", cfg.ga4_id);
    if (cfg.google_ads_id) win.gtag("config", cfg.google_ads_id);
    script(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(cfg.ga4_id ?? cfg.google_ads_id ?? "")}`);
  }
  while (queue.length) { const [n, d, id] = queue.shift()!; fire(n, d, id); }
}

const META: Record<TrackName, string> = { view_item: "ViewContent", add_to_cart: "AddToCart", begin_checkout: "InitiateCheckout", purchase: "Purchase", lead: "Lead", search: "Search" };
const TIKTOK: Record<TrackName, string> = { view_item: "ViewContent", add_to_cart: "AddToCart", begin_checkout: "InitiateCheckout", purchase: "CompletePayment", lead: "SubmitForm", search: "Search" };

function fire(name: TrackName, d: TrackData, eventId?: string) {
  const win = w();
  const cfg = state?.cfg;
  if (!cfg) return;
  const ids = (d.ids ?? (d.id !== undefined ? [d.id] : [])).map(String);
  const value = d.value;
  if (cfg.meta_pixel_id && win.fbq) {
    win.fbq("track", META[name], { ...(value !== undefined ? { value, currency: d.currency } : {}), ...(ids.length ? { content_ids: ids, content_type: "product" } : {}), ...(d.query ? { search_string: d.query } : {}) }, eventId ? { eventID: eventId } : undefined);
  }
  if (cfg.tiktok_pixel_id && win.ttq) {
    win.ttq.track(TIKTOK[name], { ...(value !== undefined ? { value, currency: d.currency } : {}), ...(ids.length ? { content_id: ids[0], content_type: "product" } : {}), ...(d.query ? { query: d.query } : {}), ...(eventId ? { event_id: eventId } : {}) });
  }
  if (win.gtag) {
    const ga = { view_item: "view_item", add_to_cart: "add_to_cart", begin_checkout: "begin_checkout", purchase: "purchase", lead: "generate_lead", search: "search" }[name];
    const items = ids.map((id) => ({ item_id: id, ...(d.name ? { item_name: d.name } : {}), ...(d.quantity ? { quantity: d.quantity } : {}) }));
    win.gtag("event", ga, { ...(value !== undefined ? { value, currency: d.currency } : {}), ...(items.length ? { items } : {}), ...(d.query ? { search_term: d.query } : {}), ...(name === "purchase" && eventId ? { transaction_id: eventId.replace(/^order-/, "") } : {}) });
    if (cfg.google_ads_id && name === "purchase" && cfg.google_ads_purchase_label) win.gtag("event", "conversion", { send_to: `${cfg.google_ads_id}/${cfg.google_ads_purchase_label}`, value, currency: d.currency, transaction_id: eventId });
    if (cfg.google_ads_id && name === "lead" && cfg.google_ads_lead_label) win.gtag("event", "conversion", { send_to: `${cfg.google_ads_id}/${cfg.google_ads_lead_label}` });
  }
}

/** One tracking call for the whole site. Held until the pixels are ready; does nothing without consent. */
export function track(name: TrackName, data: TrackData = {}, eventId?: string): void {
  if (!marketingAllowed()) return;
  if (!state?.loaded) { queue.push([name, data, eventId]); if (queue.length > 30) queue.shift(); return; }
  fire(name, data, eventId);
}

/** A discount code in the address (?promo=CODE) is kept for the cart whatever the cookie choice: it is a convenience, not tracking. */
export function capturePromo(): void {
  try {
    const code = new URLSearchParams(window.location.search).get("promo")?.trim().toUpperCase().slice(0, 40);
    if (code && /^[A-Z0-9_-]+$/.test(code)) window.sessionStorage.setItem("promo", code);
  } catch { /* storage unavailable */ }
}

/** The code waiting for the cart, if any (taken once). */
export function takePromo(): string | null {
  try { const c = window.sessionStorage.getItem("promo"); if (c) window.sessionStorage.removeItem("promo"); return c; } catch { return null; }
}

let started = false;

/** Starts everything for this market once the visitor has accepted. Called by <MarketingScripts>. */
export function startMarketing(market?: string | null): () => void {
  capturePromo();
  const begin = async () => {
    if (!marketingAllowed()) return;
    captureCampaign();
    if (state?.loaded) return;
    try {
      const r = await fetch(`${process.env.NEXT_PUBLIC_API_URL ?? ""}/public/marketing-config${market ? `?market=${encodeURIComponent(market)}` : ""}`);
      if (r.ok) loadPixels((await r.json()) as MarketingConfig);
    } catch { /* ads are optional: the site works without them */ }
  };
  const onConsent = (e: Event) => {
    const d = (e as CustomEvent<ConsentDetail>).detail;
    if (d.decision === "accepted") void begin();
    else if (state?.loaded) window.location.reload();      // withdrawn: the pixels are removed by loading the page afresh
  };
  window.addEventListener(CONSENT_EVENT, onConsent);
  if (!started) { started = true; void begin(); }

  return () => window.removeEventListener(CONSENT_EVENT, onConsent);
}
