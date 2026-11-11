// First-run check: a fresh deployment (only the admin from ADMIN_EMAIL/ADMIN_PASSWORD)
// can be set up entirely through the UI: tenant -> company -> partner with location.
//   php artisan migrate:fresh --force && php artisan platform:create-admin admin@test.com --password=password123
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
try {
  const p = await (await browser.newContext()).newPage();
  await p.goto(`${base}/login`);
  await p.fill("input[type=email]", "admin@test.com");
  await p.fill("input[type=password]", "password123");
  await p.click("button[type=submit]");
  await p.waitForURL("**/dashboard");
  await p.waitForSelector("text=Welcome");
  ok("admin signed in");

  await p.click('a:has-text("Tenants")');
  await p.fill('input[placeholder="New tenant name"]', "My Brand");
  await p.selectOption("select >> nth=0", { label: "Automotive" });
  await p.click('button:has-text("Add")');
  await p.waitForSelector("h2:has-text('My Brand')");
  ok("tenant created with an industry");

  await p.click('a:has-text("Companies")');
  await p.fill('input[placeholder="Company name"]', "My Store");
  await p.selectOption("select >> nth=0", { label: "My Brand" });
  await p.selectOption("select >> nth=1", { label: "Storefront / platform" });
  await p.click('button:has-text("Add company")');
  await p.waitForSelector("text=Company ID:");
  ok("storefront company created, ID shown: " + (await p.locator("strong").first().innerText()));

  await p.click('a:has-text("Partners")');
  await p.click('button:has-text("Add partner")');
  await p.fill("#name", "Riyadh Shop");
  await p.fill("#city", "Riyadh");
  await p.fill("#country", "SA");
  await p.fill("#latitude", "24.7");
  await p.fill("#longitude", "46.7");
  await p.click('button:has-text("Save")');
  await p.waitForSelector("h2:has-text('Riyadh Shop')");
  ok("partner created with coordinates");
  console.log("\nSetup passed.");
} catch (e) {
  console.error("\nSetup FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
