// End-to-end check of the whole marketplace journey in a real browser:
// signup -> order -> partner quote -> accept -> pay -> commissions.
//
// Prerequisites (see DEPLOY.md / README):
//   API running on :8000 with demo data   (php artisan platform:demo)
//   Web running on :3000                  (NEXT_PUBLIC_API_URL=http://localhost:8000/api)
// Then:  npm run e2e   (BASE_URL, STORE_COMPANY_ID, CHROMIUM_PATH can be overridden)
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const storeId = process.env.STORE_COMPANY_ID ?? "1";
const email = `cathy${Date.now()}@test.com`;
const ok = (msg) => console.log("OK ", msg);

async function login(ctx, who) {
  const p = await ctx.newPage();
  await p.goto(`${base}/login`);
  await p.fill("input[type=email]", who);
  await p.fill("input[type=password]", "password123");
  await p.click("button[type=submit]");
  await p.waitForURL("**/dashboard");
  await p.waitForSelector("text=Welcome");
  return p;
}

// CHROMIUM_PATH lets CI or sandboxes reuse an already-installed browser.
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const pageErrors = [];
try {
  // Bad credentials show an error instead of failing silently.
  const bad = await (await browser.newContext()).newPage();
  await bad.goto(`${base}/login`);
  await bad.fill("input[type=email]", "nobody@test.com");
  await bad.fill("input[type=password]", "wrong-password");
  await bad.click("button[type=submit]");
  await bad.waitForSelector("text=Invalid credentials");
  ok("login shows an error for bad credentials");

  const cctx = await browser.newContext({
    geolocation: { latitude: 24.71, longitude: 46.68 },
    permissions: ["geolocation"],
  });
  const c = await cctx.newPage();
  c.on("pageerror", (e) => pageErrors.push(e.message));
  c.on("console", (m) => /Content Security Policy|violates/i.test(m.text()) && pageErrors.push(`CSP: ${m.text()}`));
  await c.goto(`${base}/register`);
  await c.fill("#name", "Cathy");
  await c.fill("#email", email);
  await c.fill("#password", "password123");
  await c.fill("#password_confirmation", "password123");
  await c.fill("#company_id", storeId);
  await c.click("button[type=submit]");
  await c.waitForURL("**/dashboard");
  await c.waitForSelector("text=Welcome, Cathy");
  ok("customer signed up");

  await c.goto(`${base}/dashboard/orders/new`);
  await c.click("text=Ultimate Plus");
  await c.selectOption("select", { label: "M4" });
  await c.click('label:has-text("Hood") input');
  await c.waitForSelector("text=1,200.00");
  ok("live price shown");

  await c.click('button:has-text("Place order")');
  await c.waitForSelector("text=Share my location");
  await c.click("text=Share my location");
  await c.waitForSelector("text=Share my location", { state: "detached" });
  await c.click('button:has-text("Place order")');
  await c.waitForURL(/orders\/\d+$/);
  await c.waitForSelector("text=Waiting for partners");
  ok("order placed");

  const p = await login(await browser.newContext(), "shop@test.com");
  await p.waitForSelector('a[aria-label^="Notifications ("]');
  ok("partner's bell shows the new request");
  await p.click('a:has-text("Requests")');
  await p.waitForSelector("text=Ultimate Plus");
  await p.fill("input[placeholder=Price]", "300");
  await p.click('button:has-text("Send quote")');
  await p.waitForSelector("text=Quoted 300");
  ok("partner quoted");

  await c.reload();
  await c.waitForSelector('a[aria-label^="Notifications ("]');
  await c.click('a[aria-label^="Notifications"]');
  await c.waitForSelector("text=You received a quote");
  await c.click("text=You received a quote");
  await c.waitForURL(/orders\/\d+$/);
  ok("customer was notified of the quote and the link opens the order");
  await c.click('button:has-text("Accept")');
  await c.waitForSelector("text=Total: SAR 1,500.00");
  ok("quote accepted");

  await c.click('button:has-text("Pay")');
  await c.waitForSelector("text=paid");
  ok("order paid");

  // The partner works the job: schedule -> start -> complete.
  await p.click('a:has-text("Jobs")');
  await p.waitForSelector("text=Ultimate Plus");
  await p.fill('input[type="datetime-local"]', new Date(Date.now() + 3 * 864e5).toISOString().slice(0, 16));
  await p.click('button:has-text("Schedule")');
  await p.waitForSelector("text=Appointment scheduled");
  await p.click('button:has-text("Start work")');
  await p.waitForSelector("text=In progress");
  await p.click('button:has-text("Mark completed")');
  await p.waitForSelector("text=Completed");
  ok("partner scheduled, started and completed the job");

  await c.reload();
  await c.waitForSelector("text=Warranty");
  await c.waitForSelector("text=Work completed");
  await c.waitForSelector("text=Appointment scheduled for");
  ok("customer sees the timeline and a warranty");
  await c.click('a:has-text("Warranties")');
  await c.waitForSelector("text=Active");
  ok("warranty listed as active");

  // A second order can be cancelled before it is paid.
  await c.goto(`${base}/dashboard/orders/new`);
  await c.click("text=Ultimate Plus");
  await c.selectOption("select", { label: "M4" });
  await c.click('label:has-text("Hood") input');
  await c.waitForSelector("text=1,200.00");
  await c.click('button:has-text("Place order")');
  await c.waitForURL(/orders\/\d+$/);
  await c.click('button:has-text("Cancel order")');
  await c.waitForSelector("text=Order cancelled");
  ok("order cancelled before payment");

  await p.click('a:has-text("Commissions")');
  await p.waitForSelector("text=37.50");
  await p.waitForSelector("text=60.00");
  ok("partner sees its commission shares");

  const a = await login(await browser.newContext(), "admin@test.com");
  await a.click('a:has-text("Commissions")');
  await a.locator('button:has-text("Mark paid")').first().click();
  await a.waitForSelector("text=paid");
  ok("admin marked a payout paid");

  if (pageErrors.length) throw new Error(`Page errors: ${pageErrors.join("; ")}`);
  console.log("\nJourney passed.");
} catch (e) {
  console.error("\nJourney FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
