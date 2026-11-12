// Shipping, end to end in a real browser: an admin sets up the manual provider, a customer orders a
// physical product (address required), the partner quotes, the customer pays, the partner books a
// parcel with any carrier and moves it along, and the customer follows it. Needs demo data.
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });

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

try {
  // ---- 1. The admin sees the carriers and sets the tracking link
  const admin = await login(await browser.newContext(), "admin@test.com");
  await admin.goto(`${base}/dashboard/admin/shipping`);
  await admin.waitForSelector("text=Manual (any carrier)");
  await admin.waitForSelector("text=Custom carrier (HTTP API)");
  await admin.click("section:has-text('Manual (any carrier)') >> text=Settings");
  await admin.fill("#manual-tracking_url_template", "https://track.example.com/?n={tracking}");
  await admin.click("text=Save settings");
  await admin.waitForSelector("text=Saved");
  ok("admin configures the manual carrier (works with any carrier) and sees the custom API carrier");

  // ---- 2. A customer orders a physical product: the delivery address is required
  const cctx = await browser.newContext({ geolocation: { latitude: 24.71, longitude: 46.68 }, permissions: ["geolocation"] });
  const c = await cctx.newPage();
  await c.goto(`${base}/register?store=armor-store`);
  await c.fill("#name", "Layla");
  await c.fill("#email", `layla${Date.now()}@test.com`);
  await c.fill("#phone", "0501234567");
  await c.fill("#password", "password123");
  await c.fill("#password_confirmation", "password123");
  await new Promise((r) => setTimeout(r, 4000));
  await c.click("button[type=submit]");
  await c.waitForURL("**/dashboard**");

  await c.goto(`${base}/dashboard/orders/new`);
  await c.click("text=Ceramic Care Kit");
  await c.selectOption("select", { label: "M4" });
  await c.click('label:has-text("Hood") input');
  await c.waitForSelector("text=Delivery address");
  if (!(await c.locator('button:has-text("Place order")').isDisabled())) fail("the order can be placed without a delivery address");
  await c.fill('[aria-label="Full name"]', "Layla Ahmed");
  await c.fill('[aria-label="Mobile number"]', "0501234567");
  await c.fill('[aria-label="Street address"]', "12 King Fahd Road");
  await c.fill('[aria-label="City"]', "Riyadh");
  await c.click('button:has-text("Place order")');
  await c.waitForSelector("text=Share my location");
  await c.click("text=Share my location");
  await c.waitForSelector("text=Share my location", { state: "detached" });
  await c.click('button:has-text("Place order")');
  await c.waitForURL(/orders\/\d+$/);
  await c.waitForSelector("text=Your parcel will be shipped after payment.");
  await c.waitForSelector("text=12 King Fahd Road");
  ok("a physical product asks for a delivery address, then shows it on the order");

  // ---- 3. Quote, accept, pay
  const p = await login(await browser.newContext(), "shop@test.com");
  await p.click('a:has-text("Requests")');
  await p.waitForSelector("text=Ceramic Care Kit");
  await p.fill("input[placeholder=Price]", "20");
  await p.click('button:has-text("Send quote")');
  await p.waitForSelector("text=Quoted 20");
  await c.reload();
  await c.click('button:has-text("Accept")');
  await c.waitForSelector("text=Total: SAR 100.00");
  await c.click('button:has-text("Pay")');
  await c.waitForSelector("text=paid");
  ok("quote accepted and paid");

  // ---- 4. The partner books a parcel with any carrier
  await p.click('a:has-text("Jobs")');
  await p.waitForSelector("text=Book a shipment");
  await p.fill('[aria-label="Carrier name"]', "SMSA Express");
  await p.fill('[aria-label="Tracking number"]', "SM123456");
  await p.click('button:has-text("Save shipment")');
  await p.waitForSelector("text=SM123456");
  await p.selectOption('[aria-label="Set status"]', "in_transit");
  await p.waitForSelector("text=In transit");
  ok("the partner records a shipment from any carrier and moves it to in transit");

  // ---- 5. The customer follows it
  await c.reload();
  await c.waitForSelector("text=SMSA Express");
  await c.waitForSelector("text=SM123456");
  await c.waitForSelector("text=In transit");
  const link = await c.getAttribute("text=Track with the carrier", "href");
  if (link !== "https://track.example.com/?n=SM123456") fail(`tracking link is wrong (${link})`);
  if (await c.locator('button:has-text("Book shipment")').count()) fail("a customer can book shipments");
  if (await c.locator("text=Refresh tracking").count()) fail("a customer sees staff controls");
  ok("the customer sees the carrier, tracking number, a working tracking link and no staff controls");

  await p.selectOption('[aria-label="Set status"]', "delivered");
  await p.waitForSelector("text=Delivered");
  await c.reload();
  await c.waitForSelector("text=Delivered");
  await c.waitForSelector("text=Order shipped");
  await c.click('a[href="/dashboard/notifications"]');
  await c.waitForSelector("text=Your order has shipped");
  ok("delivery shows on the order timeline and the customer was notified");

  console.log("\nShipping passed.");
} catch (e) {
  console.error("\nShipping FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
