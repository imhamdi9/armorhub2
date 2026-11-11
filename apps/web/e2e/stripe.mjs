// Hosted-checkout payments: Pay -> redirected to the (fake) Stripe page -> back to the order,
// which waits for the signed webhook before showing "Paid".
//
// API must run with:  PLATFORM_PAYMENT_GATEWAY=stripe STRIPE_SECRET=sk_test_fake
//   STRIPE_WEBHOOK_SECRET=whsec_e2e STRIPE_API_BASE=http://127.0.0.1:12111 WEB_URL=http://localhost:3000
import { chromium } from "playwright";

import { startFakeStripe } from "./fake-stripe.mjs";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const api = process.env.API_URL ?? "http://127.0.0.1:8000";
const ok = (m) => console.log("OK ", m);

const call = async (path, { method = "GET", token, body } = {}) => {
  const res = await fetch(`${base}/api${path}`, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(`${method} ${path} -> ${res.status} ${JSON.stringify(json)}`);
  return json;
};

const fake = await startFakeStripe({ apiUrl: api, webhookSecret: process.env.STRIPE_WEBHOOK_SECRET ?? "whsec_e2e" });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
try {
  // Arrange through the API: a customer with an accepted 300 quote.
  const email = `stripe+${Date.now()}@test.com`;
  const customer = await call("/register", { method: "POST", body: { name: "Sam", email, password: "password123", password_confirmation: "password123", company_id: 1, latitude: 24.71, longitude: 46.68 } });
  const shop = await call("/login", { method: "POST", body: { email: "shop@test.com", password: "password123" } });
  const products = await call("/products", { token: customer.token });
  const parts = await call("/catalog/vehicle_part", { token: customer.token });
  const hood = parts.find((p) => p.name === "Hood");
  const { order } = await call("/orders", { method: "POST", token: customer.token, body: { product_id: products[0].id, target_type: "vehicle_part", targets: [hood.id] } });
  const requests = await call("/partner/orders", { token: shop.token });
  const quote = requests.find((q) => q.order_id === order.id);
  await call(`/partner-quotes/${quote.id}`, { method: "PATCH", token: shop.token, body: { price: 300 } });
  await call(`/partner-quotes/${quote.id}/accept`, { method: "PATCH", token: customer.token });
  ok(`order #${order.id} ready to pay (1500)`);

  const page = await (await browser.newContext()).newPage();
  await page.goto(`${base}/login`);
  await page.fill("input[type=email]", email);
  await page.fill("input[type=password]", "password123");
  await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard");
  await page.goto(`${base}/dashboard/orders/${order.id}`);

  await page.click('button:has-text("Pay")');
  await page.waitForURL(/localhost:12111\/pay\//);
  await page.waitForSelector("text=Fake Stripe Checkout");
  await page.waitForSelector("text=150000 usd");
  ok("redirected to the payment page with the right amount (150000 minor units)");

  // Cancelling returns to the order, unpaid, with a message.
  await page.click('button:has-text("Back")');
  await page.waitForSelector("text=Payment was cancelled");
  await page.waitForSelector('button:has-text("Pay")');
  ok("cancelled checkout leaves the order unpaid");

  // Pay for real: back at the order straight away, but still waiting for the webhook.
  await page.click('button:has-text("Pay")');
  await page.waitForURL(/localhost:12111\/pay\//);
  await page.click('button:has-text("Pay now")');
  await page.waitForURL(/checkout=success/);
  await page.waitForSelector("text=Confirming your payment");
  ok("returned from checkout: waiting for confirmation, not claiming success");
  await page.waitForSelector("text=Payment received", { timeout: 30000 });
  await page.waitForSelector("text=Paid", { timeout: 5000 });
  ok("order shows Paid once the signed webhook arrived");

  const shopJobs = await call("/partner/jobs", { token: shop.token });
  if (!shopJobs.some((j) => j.id === order.id)) throw new Error("the partner never got the paid job");
  ok("the partner sees the paid job");
  console.log("\nStripe flow passed.");
} catch (e) {
  console.error("\nStripe flow FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
  fake.close();
}
