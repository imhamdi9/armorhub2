import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

/** A tiny browser: storage, address, cookies and a head that remembers the scripts added to it. */
function fakeBrowser(search = "") {
  const store = new Map<string, string>();
  const session = new Map<string, string>();
  const storage = (m: Map<string, string>) => ({ getItem: (k: string) => m.get(k) ?? null, setItem: (k: string, v: string) => void m.set(k, v), removeItem: (k: string) => void m.delete(k) });
  const listeners: Record<string, ((e: Event) => void)[]> = {};
  const scripts: string[] = [];
  const win = {
    localStorage: storage(store), sessionStorage: storage(session),
    location: { search, pathname: "/s/nile-shield", href: `http://shop.test/s/nile-shield${search}`, origin: "http://shop.test" },
    addEventListener: (n: string, f: (e: Event) => void) => { (listeners[n] ??= []).push(f); },
    removeEventListener: () => undefined,
    dispatchEvent: (e: Event) => { for (const f of listeners[e.type] ?? []) f(e); return true; },
  } as Record<string, unknown>;
  const doc = {
    referrer: "", cookie: "_fbp=fb.1.1.99; _ttp=tt-1; _ga=GA1.1.555.666",
    createElement: () => ({} as { src?: string; async?: boolean }),
    head: { appendChild: (el: { src?: string }) => { scripts.push(el.src ?? ""); } },
  };
  vi.stubGlobal("window", win);
  vi.stubGlobal("document", doc);
  vi.stubGlobal("CustomEvent", class { type: string; detail: unknown; constructor(t: string, i?: { detail?: unknown }) { this.type = t; this.detail = i?.detail; } });

  return { store, session, scripts, win, listeners };
}

const accept = (b: ReturnType<typeof fakeBrowser>) => b.store.set("cookie-consent", JSON.stringify({ decision: "accepted", timestamp: new Date().toISOString() }));
const reject = (b: ReturnType<typeof fakeBrowser>) => b.store.set("cookie-consent", JSON.stringify({ decision: "rejected", timestamp: new Date().toISOString() }));

beforeEach(() => { vi.resetModules(); });
afterEach(() => { vi.unstubAllGlobals(); vi.restoreAllMocks(); });

describe("campaign tags and consent", () => {
  it("keeps nothing and sends no campaign header until the visitor accepts marketing cookies", async () => {
    const b = fakeBrowser("?utm_source=facebook&utm_campaign=eid-sale&fbclid=abc");
    const m = await import("@/lib/marketing");

    m.captureCampaign();
    expect(b.store.get("campaign")).toBeUndefined();
    expect(m.campaignHeader()).toBeNull();

    reject(b);
    m.captureCampaign();
    expect(b.store.get("campaign")).toBeUndefined();
    expect(m.campaignHeader()).toBeNull();
  });

  it("keeps the tags after consent and builds the header the server reads, dropping anything else", async () => {
    const b = fakeBrowser("?utm_source=facebook&utm_campaign=eid-sale&fbclid=abc&secret=nope");
    accept(b);
    const m = await import("@/lib/marketing");

    m.captureCampaign();
    const saved = JSON.parse(b.store.get("campaign") ?? "{}");
    expect(saved).toMatchObject({ utm_source: "facebook", utm_campaign: "eid-sale", fbclid: "abc", landing: "/s/nile-shield" });
    expect(saved.secret).toBeUndefined();

    const header = JSON.parse(m.campaignHeader() ?? "{}");
    expect(header).toMatchObject({ utm_source: "facebook", fbp: "fb.1.1.99", ttp: "tt-1", ga_client_id: "555.666" });
    expect(header.fbc).toContain("abc");                 // built from the click id when the pixel has not set one yet
    expect(header.source_url).toBe("http://shop.test/s/nile-shield?utm_source=facebook&utm_campaign=eid-sale&fbclid=abc&secret=nope");
  });

  it("forgets a campaign older than 30 days", async () => {
    const b = fakeBrowser();
    accept(b);
    b.store.set("campaign", JSON.stringify({ utm_source: "tiktok", first_seen: new Date(Date.now() - 31 * 86_400_000).toISOString() }));
    const m = await import("@/lib/marketing");

    expect(m.savedCampaign()).toBeNull();
    expect(b.store.get("campaign")).toBeUndefined();
  });

  it("keeps a discount code from the address whatever the cookie choice, and hands it over once", async () => {
    const b = fakeBrowser("?promo=welcome10");
    reject(b);
    const m = await import("@/lib/marketing");

    m.capturePromo();
    expect(m.takePromo()).toBe("WELCOME10");
    expect(m.takePromo()).toBeNull();
  });
});

describe("pixels", () => {
  const config = { meta_pixel_id: "123456789012345", tiktok_pixel_id: "TT123", ga4_id: "G-ABC123", google_ads_id: null, google_ads_purchase_label: null, google_ads_lead_label: null };

  it("loads nothing without consent, and does not even ask the server for the ids", async () => {
    const b = fakeBrowser();
    const fetchSpy = vi.fn();
    vi.stubGlobal("fetch", fetchSpy);
    const m = await import("@/lib/marketing");

    m.startMarketing("EG");
    await Promise.resolve();
    m.track("add_to_cart", { id: 1 });

    expect(fetchSpy).not.toHaveBeenCalled();
    expect(b.scripts).toEqual([]);
  });

  it("loads the three platforms for the market's ids after consent, and never twice", async () => {
    const b = fakeBrowser();
    accept(b);
    const fetchSpy = vi.fn().mockResolvedValue({ ok: true, json: async () => config });
    vi.stubGlobal("fetch", fetchSpy);
    const m = await import("@/lib/marketing");

    m.startMarketing("EG");
    await vi.waitFor(() => expect(b.scripts.length).toBe(3));
    expect(fetchSpy.mock.calls[0][0]).toContain("/public/marketing-config?market=EG");
    expect(b.scripts.join(" ")).toContain("connect.facebook.net/en_US/fbevents.js");
    expect(b.scripts.join(" ")).toContain("analytics.tiktok.com/i18n/pixel/events.js?sdkid=TT123");
    expect(b.scripts.join(" ")).toContain("googletagmanager.com/gtag/js?id=G-ABC123");

    m.startMarketing("EG");                              // a second page view in the same session adds nothing
    await Promise.resolve();
    expect(b.scripts.length).toBe(3);
  });

  it("sends events to each pixel with the same event id, and skips a platform that has no id", async () => {
    const b = fakeBrowser();
    accept(b);
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => ({ ...config, tiktok_pixel_id: null }) }));
    const m = await import("@/lib/marketing");
    m.startMarketing("EG");
    await vi.waitFor(() => expect(b.scripts.length).toBe(2));

    const fbq = vi.fn();
    const gtag = vi.fn();
    (b.win as Record<string, unknown>).fbq = fbq;
    (b.win as Record<string, unknown>).gtag = gtag;
    m.track("purchase", { value: 879, currency: "EGP", id: 17 }, "order-17");

    expect(fbq).toHaveBeenCalledWith("track", "Purchase", expect.objectContaining({ value: 879, currency: "EGP", content_ids: ["17"] }), { eventID: "order-17" });
    expect(gtag).toHaveBeenCalledWith("event", "purchase", expect.objectContaining({ value: 879, transaction_id: "17" }));
    expect((b.win as { ttq?: unknown }).ttq).toBeUndefined();
  });

  it("stops and reloads the page when marketing consent is withdrawn after the pixels loaded", async () => {
    const b = fakeBrowser();
    accept(b);
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue({ ok: true, json: async () => config }));
    const reload = vi.fn();
    (b.win.location as Record<string, unknown>).reload = reload;
    const m = await import("@/lib/marketing");
    m.startMarketing();
    await vi.waitFor(() => expect(b.scripts.length).toBe(3));

    reject(b);
    (b.win.dispatchEvent as (e: Event) => void)({ type: "cookie-consent", detail: { decision: "rejected" } } as unknown as Event);

    expect(reload).toHaveBeenCalled();
  });
});
