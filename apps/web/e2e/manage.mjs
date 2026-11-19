// Admin management screens: dashboard numbers, catalog with a parent list, product edit,
// partner edit/deactivate, company edit. Needs demo data (php artisan platform:demo).
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
  await p.waitForSelector("text=Welcome");
  await p.waitForSelector("text=Revenue");
  await p.waitForSelector("text=Payouts pending");
  ok("admin dashboard shows platform numbers");

  // Vehicle makes/models are manageable, and a model requires its make.
  await p.click('a:has-text("Catalogs")');
  await p.click('a:has-text("Vehicle Makes")');
  await p.fill('input[placeholder="Add new..."]', "Audi");
  await p.click('button:has-text("Add")');
  await p.waitForSelector("h2:has-text('Audi')");
  await p.click('a:has-text("Catalogs")');
  await p.click('a:has-text("Vehicle Models")');
  await p.selectOption('select[aria-label="Vehicle Makes"]', { label: "Audi" });
  await p.fill('input[placeholder="Add new..."]', "A4");
  await p.click('button:has-text("Add")');
  await p.waitForSelector("p:has-text('Audi · a4')");
  ok("added a vehicle make, then a model under it");

  // Product: search, edit details, hide.
  await p.click('a:has-text("Products")');
  await p.fill('input[placeholder="Search products"]', "Ultimate");
  await p.click("text=Ultimate Plus");
  await p.fill('input[aria-label="Brand"]', "XPEL Inc");
  await p.fill('input[aria-label="Warranty years"]', "7");
  await p.click('label:has-text("Visible to customers") input');
  await p.click('button:has-text("Save details")');
  await p.waitForSelector("text=Saved");
  await p.goto(`${base}/dashboard/products`);
  await p.waitForSelector("text=(hidden)");
  ok("product edited and hidden from customers");
  await p.click("text=Ultimate Plus");
  await p.click('label:has-text("Visible to customers") input');
  await p.click('button:has-text("Save details")');
  await p.waitForSelector("text=Saved");

  // Partner: edit and deactivate.
  await p.click('a:has-text("Partners")');
  await p.locator('div.rounded-lg:has-text("Shop One") button:has-text("Edit")').click();
  await p.fill("#phone", "+966500000000");
  await p.click('button:has-text("Save")');
  await p.waitForSelector("text=+966500000000");
  await p.locator('div.rounded-lg:has-text("Shop One") button:has-text("Deactivate")').click();
  await p.waitForSelector("text=Inactive");
  await p.locator('div.rounded-lg:has-text("Shop One") button:has-text("Activate")').click();
  ok("partner edited, deactivated and reactivated");

  // Company: rename.
  await p.click('a:has-text("Companies")');
  await p.locator('div.rounded-lg:has-text("Shop Co") button:has-text("Edit")').click();
  await p.fill('input[aria-label="Company name"]', "Shop Company");
  await p.click('button:has-text("Save")');
  await p.waitForSelector("h2:has-text('Shop Company')");
  ok("company renamed");

  // Orders filter works without errors.
  await p.click('a:has-text("Orders")');
  await p.selectOption("select", { label: "Paid" });
  await p.waitForSelector("text=No orders found");
  ok("orders status filter");
  console.log("\nManage passed.");
} catch (e) {
  console.error("\nManage FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
