// The public storefront: server-rendered (checked with JavaScript OFF, as a search engine sees it),
// branded, searchable, bilingual, mobile-friendly, and the path from a product page to a signed-up customer
// with that product already chosen. Also the owner/admin editor. Needs demo data (php artisan platform:demo).
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });

/** What a crawler gets: the page with JavaScript disabled. */
async function asCrawler(path) {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  const response = await page.goto(`${base}${path}`);
  const html = await response.text(); // the server's own HTML, exactly what a crawler receives
  await context.close();

  const decode = (t) => t.replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"').replace(/&#x27;|&#39;/g, "'").replace(/&amp;/g, "&");
  const text = decode(html.replace(/<(script|style)[\s\S]*?<\/\1>/g, " ").replace(/<[^>]+>/g, " ").replace(/\s+/g, " "));
  const title = decode(/<title>([\s\S]*?)<\/title>/.exec(html)?.[1] ?? "");

  return { status: response.status(), html, text, title };
}

/** Poll a public page until a condition holds (the site caches public data for a few seconds). */
async function eventually(path, test, what, seconds = 40) {
  for (let i = 0; i < seconds; i++) {
    const r = await asCrawler(path);
    if (test(r)) return r;
    await sleep(1000);
  }
  fail(`timed out waiting for ${what}`);
}

try {
  // ---- 1. Search engines can read everything (no JavaScript)
  let r = await asCrawler("/");
  if (r.status !== 200 || !/Trusted services, quoted by experts/.test(r.text) || !/Armor Store/.test(r.text)) fail("platform home page is not server-rendered with the store directory");
  if (!r.html.includes('href="/s/armor-store"')) fail("home page does not link to the store");
  ok("platform home page: real content and the store directory, with JavaScript off");

  r = await asCrawler("/s/armor-store");
  if (!/Protect your car like it matters/.test(r.text) || !/Ultimate Plus/.test(r.text) || !/certified installers/.test(r.text)) fail("storefront content missing without JavaScript");
  if (!/Armor Store/.test(r.title)) fail(`storefront title is "${r.title}"`);
  if (!/1 vetted professionals/.test(r.text) || !/Riyadh/.test(r.text)) fail("partner showcase missing");
  if (/@test\.com|latitude|shop@/i.test(r.html)) fail("private data leaked into the public page");
  ok("storefront: headline, products, partner showcase and a proper <title>, with no private data");

  r = await asCrawler("/s/armor-store/products?q=ultimate");
  if (!/Ultimate Plus/.test(r.text)) fail("catalog search did not find the product");
  r = await asCrawler("/s/armor-store/products?q=zzzznothing");
  if (!/No products found/.test(r.text)) fail("empty search not handled");
  r = await asCrawler("/s/armor-store/products");
  const productId = /\/s\/armor-store\/products\/(\d+)/.exec(r.html)?.[1];
  if (!productId) fail("no product link in the catalog");
  r = await asCrawler(`/s/armor-store/products/${productId}`);
  if (!/Hood/.test(r.text) || !/1,?200/.test(r.text) || !/Get a quote/.test(r.text)) fail("product page lacks public prices / call to action");
  if (!/Ultimate Plus — Armor Store/.test(r.title)) fail(`product title is "${r.title}"`);
  ok("catalog search (a plain GET form), product page with public prices and a call to action");

  for (const [path, status] of [["/s/no-such-store", 404], ["/s/armor-store/products/999999", 404], ["/s/no-such-store/products", 404]]) {
    const x = await asCrawler(path);
    if (x.status !== status) fail(`${path} should be ${status}, got ${x.status}`);
  }
  const robots = await asCrawler("/robots.txt");
  const sitemap = await asCrawler("/sitemap.xml");
  if (!/Disallow: \/dashboard/.test(robots.text) || !/Allow: \//.test(robots.text)) fail("robots.txt wrong");
  if (!sitemap.html.includes("/s/armor-store") || !sitemap.html.includes(`/s/armor-store/products/${productId}`)) fail("sitemap.xml does not list the store and its products");
  ok("unknown stores and products are real 404s; robots.txt and sitemap.xml are correct");

  // ---- 2. In a browser: branding, bilingual, mobile, CSP
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  const violations = [];
  page.on("console", (m) => /Content Security Policy|violates/i.test(m.text()) && violations.push(m.text()));
  await page.goto(`${base}/s/armor-store`);
  const color = await page.evaluate(() => getComputedStyle(document.querySelector(".btn-brand")).backgroundColor);
  if (color !== "rgb(15, 118, 110)") fail(`brand colour not applied: ${color}`);
  ok("the store's brand colour is applied");

  const arabic = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
  await arabic.addInitScript(() => localStorage.setItem("locale", "ar"));
  const ap = await arabic.newPage();
  for (const path of ["/", "/s/armor-store", "/s/armor-store/products", `/s/armor-store/products/${productId}`]) {
    await ap.goto(`${base}${path}`);
    await ap.waitForLoadState("networkidle");
    await ap.waitForTimeout(400);
    const m = await ap.evaluate(() => ({ dir: document.documentElement.dir, overflow: document.documentElement.scrollWidth - innerWidth, text: document.body.innerText }));
    if (m.dir !== "rtl" || !/[؀-ۿ]/.test(m.text)) fail(`${path}: not Arabic/RTL`);
    if (m.overflow > 1) fail(`${path}: horizontal overflow of ${m.overflow}px on a phone`);
  }
  ok("Arabic + right-to-left on a phone for every public page, with no horizontal overflow");

  // ---- 3. From a product to a customer who is already ordering it
  await page.goto(`${base}/s/armor-store/products/${productId}`);
  await page.click("text=Get a quote");
  await page.waitForURL(/\/register\?store=armor-store&product=/);
  await page.waitForSelector("text=You are signing up with Armor Store");
  if (await page.locator("#company_id").count()) fail("a store signup still asks for a Company ID");
  const stamp = Date.now();
  await page.fill("#name", "Store Visitor");
  await page.fill("#email", `visitor${stamp}@test.com`);
  await page.fill("#password", "password123");
  await page.fill("#password_confirmation", "password123");
  await page.click("button[type=submit]");
  await page.waitForURL(/\/dashboard\/orders\/new\?product=/);
  await page.waitForSelector(`input[name=product]:checked`);
  ok("product page -> signup (no Company ID) -> ordering with that product already selected");

  await page.goto(`${base}/s/armor-store`);
  await page.waitForSelector("text=Dashboard"); // signed-in visitors get a Dashboard link
  if (violations.length) fail(`CSP violations: ${violations.join("; ")}`);
  ok("signed-in visitors see a Dashboard link; no CSP violations on the public pages");

  // ---- 4. The editor: admin changes the store, the public page follows; hostile input stays text
  const admin = await (await browser.newContext()).newPage();
  await admin.goto(`${base}/login`);
  await admin.fill("input[type=email]", "admin@test.com");
  await admin.fill("input[type=password]", "password123");
  await admin.click("button[type=submit]");
  await admin.waitForSelector("text=Welcome");
  await admin.click('a:has-text("Companies")');
  await admin.locator("div.rounded-lg:has-text('Armor Store') a:has-text('Storefront')").click();
  await admin.waitForSelector("#sf-headline");

  const evil = `Sale <img src=x onerror="window.__pwned=1"> & more`;
  await admin.fill("#sf-headline", evil);
  await admin.fill("#sf-color", "#7c3aed");
  await admin.click('button:has-text("Save storefront")');
  await admin.waitForSelector("text=Saved");
  const live = await eventually("/s/armor-store", (x) => x.text.includes("Sale <img"), "the edited headline");
  if (live.html.includes('onerror="window.__pwned=1"') && !live.html.includes("&lt;img")) fail("hostile headline was rendered as markup");
  const probe = await context.newPage();
  await probe.goto(`${base}/s/armor-store`);
  await probe.waitForTimeout(500);
  if (await probe.evaluate(() => window.__pwned)) fail("injected markup ran");
  if ((await probe.evaluate(() => getComputedStyle(document.querySelector(".btn-brand")).backgroundColor)) !== "rgb(124, 58, 237)") fail("new colour not applied");
  ok("owner/admin edits show up on the public page; markup in a headline is displayed as text and never runs");

  await admin.fill("#sf-color", "red; background:url(//evil)");
  await admin.click('button:has-text("Save storefront")');
  await admin.waitForSelector("p.text-red-500");
  await admin.fill("#sf-color", "#0f766e");
  await admin.fill("#sf-logo", "http://insecure.example/logo.png");
  await admin.click('button:has-text("Save storefront")');
  await admin.waitForSelector("p.text-red-500");
  ok("an unsafe colour and a non-https logo are refused by the editor");

  await admin.fill("#sf-logo", "");
  await admin.fill("#sf-headline", "Protect your car like it matters");
  await admin.uncheck("#sf-enabled");
  await admin.click('button:has-text("Save storefront")');
  await admin.waitForSelector("text=Saved");
  await eventually("/s/armor-store", (x) => x.status === 404, "the closed store to disappear");
  const dir = await eventually("/", (x) => !x.text.includes("Armor Store"), "the directory to drop it");
  void dir;
  await admin.check("#sf-enabled");
  await admin.click('button:has-text("Save storefront")');
  await admin.waitForSelector("text=Saved");
  await eventually("/s/armor-store", (x) => x.status === 200, "the reopened store");
  ok("closing a storefront removes it (404) and drops it from the directory; reopening restores it");

  // ---- Two countries on one platform: each store trades in its own currency, with its own phone rules.
  const sa = await asCrawler("/s/armor-store");
  const eg = await asCrawler("/s/nile-shield");
  if (!/SAR\s*250/.test(sa.text) || /EGP/.test(sa.text)) fail(`Saudi store should price in SAR (${sa.text.match(/[A-Z]{3}\s*[\d,.]+/g)})`);
  if (!/EGP\s*3,250/.test(eg.text) || /SAR/.test(eg.text)) fail(`Egyptian store should price in EGP (${eg.text.match(/[A-Z]{3}\s*[\d,.]+/g)})`);
  const home = await asCrawler("/");
  if (!/Saudi Arabia/.test(home.text) || !/Egypt/.test(home.text)) fail("the directory should show each store's country");
  ok("each store shows prices in its own country's currency (SAR / EGP) and the directory shows the country");

  const arCtx = await browser.newContext();
  const ar = await arCtx.newPage();
  await ar.goto(`${base}/s/nile-shield`);
  await ar.click("text=العربية");
  await ar.waitForSelector("text=ج.م");
  ok("Arabic shows the Egyptian pound symbol");

  const eCtx = await browser.newContext();
  const e = await eCtx.newPage();
  await e.goto(`${base}/register?store=nile-shield`);
  await e.waitForSelector("text=You are signing up with Nile Shield.");
  const hint = await e.getAttribute("#phone", "placeholder");
  if (!/\+20/.test(hint ?? "")) fail(`phone hint should show Egypt's format (${hint})`);
  await e.fill("#name", "Mona");
  await e.fill("#email", `mona${Date.now()}@example.org`);
  await e.fill("#phone", "0501234567"); // a Saudi number is not valid for an Egyptian store
  await e.fill("#password", "password123");
  await e.fill("#password_confirmation", "password123");
  await sleep(4000);
  await e.click("button[type=submit]");
  await e.waitForSelector("text=valid Egypt mobile number");
  ok("a Saudi number is refused at an Egyptian store, with a clear message");
  await arCtx.close();
  await eCtx.close();

  // ---- Brand layer: FAQ (readable without JavaScript, with FAQ markup), footer, header, mobile menu.
  const brand = await asCrawler("/s/armor-store");
  if (!/Frequently asked questions/.test(brand.text) || !/Is my payment safe\?/.test(brand.text)) fail("the FAQ is not in the server-rendered page");
  if (!brand.html.includes('"@type":"FAQPage"')) fail("FAQ structured data is missing");
  if (!/Powered by/.test(brand.text) || !/Secure payments/.test(brand.text)) fail("the footer is missing");
  const mctx = await browser.newContext({ viewport: { width: 390, height: 800 }, isMobile: true });
  const mp = await mctx.newPage();
  await mp.goto(`${base}/s/armor-store`);
  await mp.click('button[aria-label="Menu"]');
  await mp.waitForSelector('nav[aria-label="Main"]:visible >> text=Products');
  const overflow = await mp.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  if (overflow > 1) fail(`the page scrolls sideways on a phone by ${overflow}px`);
  await mp.goto(`${base}/s/armor-store#faq`);
  await mp.click("summary:has-text('Is my payment safe?')");
  await mp.waitForSelector("text=we never see or store your card details");
  await mctx.close();
  ok("FAQ + structured data + footer are server-rendered; mobile menu works and nothing overflows");

  console.log("\nStorefront passed.");
} catch (e) {
  console.error("\nStorefront FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
