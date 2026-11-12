// The admin control panel: configure and switch payment providers live (against a fake gateway),
// change settings, and see it all in the audit log. Needs demo data and the API started with
//   PLATFORM_ALLOW_INSECURE_PROVIDER_URLS=true   (so the local fake gateway may be called)
import { chromium } from "playwright";

import { startFakeGateway } from "./fake-gateway.mjs";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const api = process.env.API_URL ?? "http://127.0.0.1:8000";
const GATEWAY_KEY = "gw_live_key_s3cr3t";
const HOOK_SECRET = "hook_s3cr3t_value";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };

const call = async (path, { method = "GET", token, body } = {}) => {
  const res = await fetch(`${base}/api${path}`, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  return { status: res.status, json };
};

const gateway = await startFakeGateway({ apiUrl: api, apiKey: GATEWAY_KEY, webhookSecret: HOOK_SECRET });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });

/** The public site caches API reads for a few seconds, so a change can take a moment to show: reload until it does. */
async function eventually(page, check, what) {
  for (let i = 0; i < 10; i++) {
    await page.reload();
    if (await check()) return;
    await page.waitForTimeout(3000);
  }
  throw new Error(`never showed: ${what}`);
}

/** A customer with an accepted quote, created through the API, ready to pay 1500. */
async function readyOrder(tag) {
  const challenge = (await call("/signup-challenge")).json;
  await new Promise((r) => setTimeout(r, (challenge.min_seconds + 1) * 1000));
  const email = `admin${tag}${Date.now()}@test.com`;
  const reg = await call("/register", { method: "POST", body: { name: "Pat", email, signup_token: challenge.token, password: "password123", password_confirmation: "password123", company_id: 1, latitude: 24.71, longitude: 46.68 } });
  if (reg.status !== 201) fail(`signup failed: ${JSON.stringify(reg.json)}`);
  const token = reg.json.token;
  const shop = (await call("/login", { method: "POST", body: { email: "shop@test.com", password: "password123" } })).json.token;
  const products = (await call("/products", { token })).json;
  const hood = (await call("/catalog/vehicle_part", { token })).json.find((p) => p.name === "Hood");
  const { order } = (await call("/orders", { method: "POST", token, body: { product_id: products.find((p) => p.name === "Ultimate Plus").id, target_type: "vehicle_part", targets: [hood.id] } })).json;
  const quote = (await call("/partner/orders", { token: shop })).json.find((q) => q.order_id === order.id);
  await call(`/partner-quotes/${quote.id}`, { method: "PATCH", token: shop, body: { price: 300 } });
  await call(`/partner-quotes/${quote.id}/accept`, { method: "PATCH", token });
  return { email, order };
}

async function signIn(context, email) {
  const page = await context.newPage();
  await page.goto(`${base}/login`);
  await page.fill("input[type=email]", email);
  await page.fill("input[type=password]", "password123");
  await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard");
  await page.waitForSelector("text=/Welcome|مرحبًا/");
  return page;
}

try {
  const admin = await signIn(await browser.newContext(), "admin@test.com");
  const nav = (await admin.locator("[data-sidebar=menu-button]").allInnerTexts()).join("|");
  if (!/Overview\|Settings\|Payments\|Payouts\|Shipping\|Roles\|Theme editor\|Content\|Platform\|Security\|Audit log/.test(nav)) fail(`Administration section missing: ${nav}`);
  ok("admin sees the Administration section");

  // ---- Overview
  await admin.click('a:has-text("Overview")');
  await admin.waitForSelector("text=Needs attention");
  await admin.waitForSelector("text=Platform commission");
  ok("overview shows what needs attention and the platform numbers");

  // ---- Settings: change, persist, validate, reset
  await admin.click('a:has-text("Settings")');
  const commission = admin.locator("#platform\\.commission_percentage");
  await commission.fill("12");
  await admin.locator("section:has-text('Platform') button:has-text('Save changes')").click();
  await admin.waitForSelector("text=Saved");
  await admin.reload();
  if ((await admin.locator("#platform\\.commission_percentage").inputValue()) !== "12") fail("commission was not saved");
  await admin.locator("#platform\\.commission_percentage").fill("150");
  await admin.locator("section:has-text('Platform') button:has-text('Save changes')").click();
  await admin.waitForSelector("p.text-red-500");
  await admin.reload();
  await admin.locator("button:has-text('Reset to default') >> nth=0").click();
  await admin.waitForSelector("text=Reset to default");
  ok("settings save immediately, reject invalid values, and reset to defaults");

  // ---- Close signups, then reopen
  await admin.locator("#platform\\.registration_open").uncheck();
  await admin.locator("section:has-text('Platform') button:has-text('Save changes')").click();
  await admin.waitForSelector("text=Saved");
  const challenge = (await call("/signup-challenge")).json;
  const closed = await call("/register", { method: "POST", body: { name: "X", email: `closed${Date.now()}@test.com`, signup_token: challenge.token, password: "password123", password_confirmation: "password123", company_id: 1 } });
  if (closed.status !== 403) fail(`signup should be closed (${closed.status})`);
  await admin.locator("#platform\\.registration_open").check();
  await admin.locator("section:has-text('Platform') button:has-text('Save changes')").click();
  await admin.waitForSelector("text=Saved");
  ok("the signup switch closes and reopens registration platform-wide");

  // ---- Payments: connect a custom gateway entirely from the UI
  await admin.click('a:has-text("Payments")');
  const card = admin.locator("section:has(h2:has-text('Custom gateway'))");
  await card.locator("button:has-text('Settings')").click();
  const fill = async (key, value) => admin.fill(`#http-${key}`, value);
  await fill("create_url", "http://127.0.0.1:12112/v1/payments");
  await fill("auth_header", "X-Api-Key");
  await fill("auth_value", GATEWAY_KEY);
  await fill("response_url_path", "data.pay_url");
  await fill("response_reference_path", "data.id");
  await fill("webhook_secret", HOOK_SECRET);
  await fill("webhook_status_path", "data.status");
  await fill("webhook_order_path", "data.reference");
  await fill("webhook_reference_path", "data.id");
  await fill("webhook_amount_path", "data.amount");
  await card.locator("button:has-text('Save settings')").click();
  await card.locator("text=Saved").waitFor();
  await card.locator("text=Configured").first().waitFor();
  const activated = admin.waitForResponse((r) => r.url().endsWith("/admin/payments/active") && r.status() === 200);
  await card.locator("button:has-text('Make active')").click();
  await activated;
  ok("a custom gateway was configured and activated from the admin panel");

  // The secret is never shown again, only that it is set.
  await admin.reload();
  const page = await admin.content();
  if (page.includes(GATEWAY_KEY) || page.includes(HOOK_SECRET)) fail("a secret leaked into the page");
  ok("saved secrets are never sent back to the browser");

  // ---- A customer pays through it
  const { email, order } = await readyOrder("gw");
  const customer = await signIn(await browser.newContext(), email);
  await customer.goto(`${base}/dashboard/orders/${order.id}`);
  await customer.click('button:has-text("Pay")');
  await customer.waitForURL(/127\.0\.0\.1:12112\/pay\//);
  await customer.waitForSelector("text=Fake Gateway");
  await customer.waitForSelector("text=150000 SAR");
  await customer.click('button:has-text("Confirm payment")');
  await customer.waitForURL(/checkout=success/);
  await customer.waitForSelector("text=Confirming your payment");
  await customer.waitForSelector("text=Payment received", { timeout: 30000 });
  ok("a customer paid through the custom gateway; the signed webhook marked the order paid");

  // ---- Switch back to the simulated provider live: the next order pays instantly
  await admin.click('a:has-text("Payments")');
  const sim = admin.locator("section:has(h2:has-text('Simulated'))");
  await sim.locator("button:has-text('Make active')").click();
  await sim.locator("text=Active").first().waitFor();
  const second = await readyOrder("sim");
  const buyer = await signIn(await browser.newContext(), second.email);
  await buyer.goto(`${base}/dashboard/orders/${second.order.id}`);
  await buyer.click('button:has-text("Pay")');
  await buyer.waitForSelector("text=Payment received", { timeout: 15000 });
  ok("switching provider takes effect immediately");

  // ---- Everything above is in the audit log, without secrets
  await admin.click('a:has-text("Audit log")');
  await admin.waitForSelector("text=payments.provider_activated");
  await admin.waitForSelector("text=settings.updated");
  const audit = await admin.content();
  if (audit.includes(GATEWAY_KEY) || audit.includes(HOOK_SECRET)) fail("a secret leaked into the audit log");
  await admin.click('button:has-text("payments")');
  await admin.waitForSelector("text=payments.provider_activated");
  ok("the audit log recorded the changes, without any secret");

  // The platform core: ArmorHub is just a module; its events are catalogued, delivered and replayable.
  await admin.click('a:has-text("Platform")');
  await admin.waitForSelector("text=v1.0.0");
  await admin.waitForSelector("text=order.paid");
  await admin.click("[role=tab]:has-text('Event catalog')");
  await admin.waitForSelector("text=order_event_id: int");
  await admin.click("[role=tab]:has-text('Event log')");
  await admin.fill('[aria-label="Event name"]', "order.paid");
  await admin.waitForSelector("button:has-text('order.paid')");
  await admin.click("button:has-text('order.paid')");
  await admin.waitForSelector("text=armorhub.order-notifications");
  await admin.click("button:has-text('Replay')");
  await admin.waitForSelector("[role=dialog] >> text=delivered");
  ok("the platform page lists the ArmorHub module, its event contracts, the event log, and replays a delivery");

  // A blueprint launches a whole market (country, storefront, starter products) from configuration.
  await admin.click("[role=tab]:has-text('Blueprints')");
  await admin.waitForSelector("text=Automotive protection: Egypt");
  await admin.fill("#armorhub\\.automotive-egypt-name", "ArmorHub Egypt E2E");
  await admin.locator("article:has-text('Automotive protection: Egypt') >> button:has-text('Launch')").click();
  await admin.waitForSelector("text=Market launched.");
  const storeHref = await admin.getAttribute("text=Open its store", "href");
  const launched = await admin.context().newPage();
  await launched.goto(new URL(storeHref, base).toString());
  await launched.waitForSelector("text=Protect your car like it matters");
  await launched.waitForSelector("text=Paint Protection Film");
  await launched.waitForSelector("text=/EGP/");
  await launched.close();
  ok("a blueprint launched an Egyptian market with a live storefront and starter products, priced in EGP");

  // Workflows are data: a market re-wires the order lifecycle from the admin panel, and can put it back.
  await admin.click("[role=tab]:has-text('Workflows')");
  await admin.waitForSelector("text=accept_quote");
  const editor = admin.locator('[aria-label="Workflow definition"]');
  const definition = JSON.parse(await editor.inputValue());
  definition.transitions.cancel.from.push("paid");
  await editor.fill(JSON.stringify(definition));
  await admin.click("button:has-text('Save for this market')");
  await admin.waitForSelector("text=This market now runs its own version");
  await admin.waitForSelector("text=Customised v1");
  await admin.click("button:has-text('Revert to product default')");
  await admin.waitForSelector("text=Product default");
  ok("the order workflow is shown as data; a market customised it and reverted it from the admin panel");

  // Editable surfaces: an admin changes the public headline and hides a section; nothing shows until it is published.
  await admin.click('a:has-text("Content")');
  await admin.waitForSelector("text=Home page");
  await admin.click("[role=tab]:has-text('Home page')");
  await admin.waitForSelector("#f-hero_title");
  await admin.fill("#f-hero_title", "Edited by an admin E2E");
  await admin.locator("label:has-text('Show: FAQ') input").uncheck();
  await admin.click("button:has-text('Save draft')");
  await admin.waitForSelector("text=Draft saved.");
  const visitor = await (await browser.newContext()).newPage();
  await visitor.goto(new URL("/", base).toString());
  await visitor.waitForSelector("h1");
  if ((await visitor.textContent("body")).includes("Edited by an admin E2E")) throw new Error("a draft was visible to visitors");
  await admin.fill("input[placeholder='Note (what changed)']", "e2e");
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Published.");
  await eventually(visitor, async () => (await visitor.textContent("body")).includes("Edited by an admin E2E"), "the published headline");
  if (await visitor.locator("#faq").count()) throw new Error("the hidden FAQ section is still shown");
  await admin.locator("li:has-text('live')").first().waitFor();
  // Put the built-in content back so later phases see the original home page.
  await admin.fill("#f-hero_title", "");
  await admin.locator("label:has-text('Show: FAQ') input").check();
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Published.");
  await eventually(visitor, async () => (await visitor.locator("#faq").count()) > 0, "the FAQ section again");
  await visitor.close();
  ok("an admin edited the home page: drafts stay private, publishing goes live, a section was hidden and restored");

  // Dashboard menu names and custom roles.
  await admin.click('a:has-text("Content")');
  await admin.click("[role=tab]:has-text('Dashboard menu')");
  await admin.click("button:has-text('Add item')");
  await admin.fill("#f-labels-0-item", "Orders");
  await admin.fill("#f-labels-0-label", "My jobs board");
  await admin.fill("input[placeholder='Note (what changed)']", "rename");
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Published.");
  await admin.reload();
  await admin.waitForSelector("[data-sidebar=menu-button]:has-text('My jobs board')");
  await admin.click("[role=tab]:has-text('Dashboard menu')").catch(() => {});
  await admin.locator("#f-labels-0-label").fill("");
  await admin.locator("button:has-text('Remove')").first().click();
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Published.");
  ok("an admin renamed a dashboard menu item and the signed-in sidebar showed it");

  await admin.click('a:has-text("Roles")');
  await admin.waitForSelector("text=New role");
  await admin.fill("[aria-label='Role name']", "sales-viewer");
  await admin.locator("label:has-text('See products') input").first().check();
  await admin.click("button:has-text('Create role')");
  await admin.waitForSelector("h2:has-text('sales-viewer')");
  if (await admin.locator("label:has-text('admin.panel')").count()) throw new Error("an admin permission was offered");
  await admin.locator("article:has-text('sales-viewer') >> button:has-text('Delete')").click();
  await admin.waitForSelector("h2:has-text('sales-viewer')", { state: "detached" });
  ok("an admin created and deleted a custom role; administrator powers are not offered");

  // Theme editor: live preview of unsaved changes, section order, colours; publishing changes the public page.
  await admin.click('a:has-text("Theme editor")');
  await admin.waitForSelector("[data-section=faq]");
  const preview = admin.frameLocator("iframe[title='Live preview']");
  await preview.locator("h1").waitFor();
  // Click-to-edit: clicking the headline in the preview opens its field; clicking a section selects it in the list.
  await preview.locator("h1").click();
  await admin.waitForSelector("#t-hero_title");
  await admin.waitForFunction(() => document.activeElement?.id === "t-hero_title", null, { timeout: 5000 }).catch(() => { throw new Error("clicking the headline did not focus its field"); });
  await preview.locator("[data-block=faq] h2, [data-block=faq] section").first().click({ force: true });
  await admin.waitForSelector("[data-section=faq][data-selected=true]");
  await admin.click("[role=tab]:has-text('Text')");
  await admin.fill("#t-hero_title", "Live preview headline");
  await preview.locator("h1:has-text('Live preview headline')").waitFor();
  await admin.click("button[aria-pressed=false]:has-text('Mobile')");
  const width = await admin.locator("iframe[title='Live preview']").evaluate((el) => el.getBoundingClientRect().width);
  if (width > 400) throw new Error(`mobile preview is ${width}px wide`);
  await admin.click("[role=tab]:has-text('Sections')");
  for (let i = 0; i < 14; i++) await admin.click("[aria-label='Move up FAQ']");
  await admin.click("[aria-label='Hide Numbers band']");
  await admin.click("[role=tab]:has-text('Colors and fonts')");
  await admin.fill("#c-brand_color", "#ff6600");
  await admin.selectOption("#f-font_heading", "serif");
  await admin.click("[role=tab]:has-text('Images')");
  const PNG = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==", "base64");
  await admin.setInputFiles("input[aria-label='Upload an image Hero image']", { name: "hero.png", mimeType: "image/png", buffer: PNG });
  await admin.waitForSelector("text=Image uploaded.");
  await preview.locator("img[src*='/public/media/']").first().waitFor({ state: "attached" });
  const loaded = await preview.locator("img[src*='/public/media/']").first().evaluate((img) => img.complete && img.naturalWidth > 0);
  if (!loaded) throw new Error("the uploaded hero image did not load in the preview");
  await admin.click("[role=tab]:has-text('Colors and fonts')");
  const bg = await preview.locator("a.btn-brand").first().evaluate((el) => getComputedStyle(el).backgroundColor);
  if (!bg.includes("255, 102, 0")) throw new Error(`preview colour not applied: ${bg}`);
  const visitor2 = await (await browser.newContext()).newPage();
  await visitor2.goto(new URL("/", base).toString());
  if ((await visitor2.textContent("body")).includes("Live preview headline")) throw new Error("an unsaved change reached visitors");
  await admin.fill("input[placeholder='Note (what changed)']", "theme");
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Published.");
  await eventually(visitor2, async () => (await visitor2.textContent("body")).includes("Live preview headline"), "the published theme");
  const ids = await visitor2.locator("main section[id]").evaluateAll((els) => els.map((e) => e.id));
  if (ids.indexOf("faq") > ids.indexOf("stores")) throw new Error(`FAQ was not moved above the markets: ${ids}`);
  // Put the built-in look back.
  await admin.reload();
  await admin.waitForSelector("[data-section=faq]", { timeout: 60000 });
  await admin.click("[role=tab]:has-text('Colors and fonts')");
  await admin.click("button:has-text('Reset')").catch(() => {});
  await admin.selectOption("#f-font_heading", "");
  await admin.click("[role=tab]:has-text('Text')");
  await admin.fill("#t-hero_title", "");
  await admin.click("[role=tab]:has-text('Images')");
  await admin.locator("div:has(> label:has-text('Hero image')) >> button:has-text('Remove')").click();
  await admin.click("[role=tab]:has-text('Sections')");
  await admin.click("[aria-label='Show Numbers band']").catch(() => {});
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Published.");
  await visitor2.close();
  ok("the theme editor previews unsaved changes live (desktop and mobile), reorders and hides sections, recolours, and publishes");

  // Governed publishing: a scheduled change waits in the queue; with approval on, it needs someone else.
  const adminToken = (await call("/login", { method: "POST", body: { email: "admin@test.com", password: "password123" } })).json.token;
  // This script clicks far faster than a person; let the per-minute request allowance recover so the page is not throttled.
  for (let i = 0; i < 30; i++) {
    const r = await fetch(`${base}/api/me`, { headers: { Accept: "application/json", Authorization: `Bearer ${adminToken}` } });
    if (Number(r.headers.get("x-ratelimit-remaining") ?? 0) > 60) break;
    await new Promise((res) => setTimeout(res, 4000));
  }
  await admin.click('a:has-text("Content")');
  await admin.click("[role=tab]:has-text('Header menu')");
  await admin.waitForSelector("#f-nav_faq");
  await admin.fill("#f-nav_faq", "Questions E2E");
  const inTwoHours = new Date(Date.now() + 2 * 3600 * 1000);
  const local = new Date(inTwoHours.getTime() - inTwoHours.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
  await admin.fill("input[type=datetime-local]", local);
  await admin.click("button:has-text('Schedule')");
  await admin.waitForSelector("text=Scheduled.");
  await admin.waitForSelector("section[aria-label='Approvals and schedule'] >> text=scheduled");
  await admin.locator("section[aria-label='Approvals and schedule'] li:has-text('Header menu') >> button:has-text('Cancel')").first().click();
  await admin.waitForSelector("text=Nothing is waiting.");
  await call("/admin/settings", { method: "PUT", token: adminToken, body: { values: { "content.require_approval": true } } });
  await admin.reload();
  await admin.click("[role=tab]:has-text('Header menu')");
  await admin.fill("#f-nav_faq", "Questions E2E");
  await admin.click("button:has-text('Publish')");
  await admin.waitForSelector("text=Sent for approval.");
  await admin.waitForSelector("section[aria-label='Approvals and schedule'] >> text=waiting for approval");
  if (await admin.locator("section[aria-label='Approvals and schedule'] button:has-text('Approve')").count()) fail("an admin was offered to approve their own change");
  await admin.locator("section[aria-label='Approvals and schedule'] li:has-text('Header menu') >> button:has-text('Cancel')").first().click();
  await call("/admin/settings", { method: "PUT", token: adminToken, body: { values: { "content.require_approval": false } } });
  ok("a scheduled change waited in the queue and was cancelled; with approval required, publishing was sent for approval and could not be self-approved");

  await admin.click('a:has-text("Security")');
  await admin.waitForSelector("text=Health checks");
  await admin.waitForSelector("text=Emergency");
  ok("the security page shows health checks and the emergency control");

  console.log("\nAdmin panel passed.");
} catch (e) {
  console.error("\nAdmin panel FAILED:", e.message.split("\n")[0], (e.stack.match(/admin.mjs:\d+/) || [])[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
  gateway.close();
}
