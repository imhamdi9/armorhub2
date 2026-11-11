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
  const { order } = (await call("/orders", { method: "POST", token, body: { product_id: products[0].id, target_type: "vehicle_part", targets: [hood.id] } })).json;
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
  if (!/Overview\|Settings\|Payments\|Security\|Audit log/.test(nav)) fail(`Administration section missing: ${nav}`);
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
  await card.locator("button:has-text('Make active')").click();
  await card.locator("text=Active").first().waitFor();
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

  await admin.click('a:has-text("Security")');
  await admin.waitForSelector("text=Health checks");
  await admin.waitForSelector("text=Emergency");
  ok("the security page shows health checks and the emergency control");

  console.log("\nAdmin panel passed.");
} catch (e) {
  console.error("\nAdmin panel FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
  gateway.close();
}
