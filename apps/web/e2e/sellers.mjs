// A brand sells its own products in a marketplace, end to end in a real browser: it applies with documents, a platform
// admin verifies and approves, the seller lists a product, a customer buys it (straight to payment, no partner quotes)
// and the seller ships it. Needs demo data; run after the partners check (same database).
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const stamp = Date.now();
const PASSWORD = "Str0ng-Passw0rd!x";
const email = `parts${stamp}@gulfparts.example.org`;
const pdf = (name) => ({ name, mimeType: "application/pdf", buffer: Buffer.from("%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF") });

async function login(who, password = "password123") {
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  await p.goto(`${base}/login`);
  await p.fill("input[type=email]", who);
  await p.fill("input[type=password]", password);
  await p.click("button[type=submit]");
  return { p, ctx };
}

try {
  // ---- 1. The sign-up page offers the three kinds of account; the seller applies with documents
  const sctx = await browser.newContext();
  const s = await sctx.newPage();
  await s.goto(`${base}/signup`);
  await s.waitForSelector("text=I want to buy");
  await s.waitForSelector("text=I provide services");
  await s.click("text=Sell on ArmorHub");
  await s.waitForURL("**/seller-signup");
  await s.waitForSelector("#target option", { state: "attached" });
  await s.fill("#business_name", "Gulf Parts Brand");
  await s.fill("#registration_number", "1010999888");
  await s.fill("#tax_number", "123");                // wrong on purpose: the server refuses it, and the retry must still work
  await s.fill("#contact_name", "Omar Nasser");
  await s.fill("#email", email);
  await s.fill("#phone", "0501119999");
  await s.fill("#city", "Riyadh");
  await s.fill("#description", "Genuine spare parts and accessories.");
  await s.fill("#password", PASSWORD);
  await s.fill("#password_confirmation", PASSWORD);
  await sleep(4000);                               // the form unlocks after a short human-check delay
  await s.click('button[type=submit]');            // documents are required: nothing is sent without them
  await s.waitForSelector("text=Please upload");
  await s.setInputFiles("#doc-id", pdf("owner-id.pdf"));
  await s.setInputFiles("#doc-commercial_registration", pdf("cr.pdf"));
  await s.setInputFiles("#doc-tax_certificate", pdf("vat.pdf"));
  await s.click("button[type=submit]");
  await s.waitForSelector("text=valid tax registration number");
  // The refused attempt used up its anti-bot token; the form must hand out a fresh one rather than treat the retry as a bot.
  await s.fill("#tax_number", "300000000000003");
  await s.waitForSelector("button[type=submit]:not([disabled])");
  await s.click("button[type=submit]");
  await s.waitForSelector("text=Application received");
  await sctx.close();
  ok("the sign-up page offers customer / partner / seller; a seller applies with its documents");

  // ---- 2. Pending: cannot sign in
  let { p, ctx } = await login(email, PASSWORD);
  await p.waitForSelector("text=still being reviewed");
  await ctx.close();

  // ---- 3. The platform admin opens the documents and approves
  const admin = await login("admin@test.com");
  const a = admin.p;
  await a.waitForURL("**/dashboard");
  await a.click('a:has-text("Partner applications")');
  await a.click("[role=tab]:has-text('Sellers')");
  const card = "article:has-text('Gulf Parts Brand')";
  await a.waitForSelector(`${card} >> text=vat.pdf`);
  const href = await a.getAttribute(`${card} >> text=vat.pdf`, "href");
  const file = await a.request.get(new URL(href, base).toString());
  if (file.status() !== 200 || !/application\/pdf/.test(file.headers()["content-type"] ?? "") || file.headers()["x-content-type-options"] !== "nosniff") fail("a reviewer cannot open the document safely");
  const anon = await (await browser.newContext()).request.get(new URL(href, base).toString());
  if (anon.status() === 200) fail("a document can be opened without signing in");
  await a.check(`${card} >> input[type=checkbox]`);
  await a.click(`${card} >> button:has-text('Approve')`);
  await a.waitForSelector(card, { state: "detached" });
  ok("the admin opens the documents (private: not reachable without signing in) and approves the seller");

  // ---- 4. The seller signs in with seller-only screens and lists a product
  ({ p, ctx } = await login(email, PASSWORD));
  await p.waitForURL("**/dashboard");
  await p.waitForSelector('a:has-text("Sales")');
  await p.waitForSelector('a:has-text("Products")');
  for (const hidden of ["Requests", "Jobs", "Partners", "Team", "Commissions", "Markets"]) {
    if (await p.locator(`a:has-text("${hidden}")`).count()) fail(`a seller can see "${hidden}"`);
  }
  await p.click('a:has-text("Products")');
  await p.click('button:has-text("Add product")');
  await p.fill("#name", "Ceramic brake pads");
  await p.fill("#category", "spare-parts");
  await p.fill("#price", "90");
  await p.click('button:has-text("Save")');
  await p.click("text=Ceramic brake pads");
  await p.waitForSelector("text=Price rules");
  await p.locator("div:has(> span:text-is('Hood')) >> input[placeholder=Price]").fill("90");
  await p.click('button:has-text("Save price rules")');
  await p.waitForSelector("text=Saved");
  ok("the approved seller signs in with seller-only screens and lists a product with a price");

  // ---- 5. A customer buys it: straight to payment, no partner quotes, no location needed
  const cctx = await browser.newContext();
  const c = await cctx.newPage();
  await c.goto(`${base}/register?store=armor-store`);
  await c.fill("#name", "Rania");
  await c.fill("#email", `rania${stamp}@test.com`);
  await c.fill("#password", "password123");
  await c.fill("#password_confirmation", "password123");
  await sleep(4000);
  await c.click("button[type=submit]");
  await c.waitForURL("**/dashboard**");
  await c.goto(`${base}/dashboard/orders/new`);
  await c.click("text=Ceramic brake pads");
  await c.selectOption("select", { label: "M4" });
  await c.click('label:has-text("Hood") input');
  await c.waitForSelector("text=Delivery address");
  await c.fill('[aria-label="Full name"]', "Rania Said");
  await c.fill('[aria-label="Mobile number"]', "0501234567");
  await c.fill('[aria-label="Street address"]', "5 Olaya Street");
  await c.fill('[aria-label="City"]', "Riyadh");
  await c.click('button:has-text("Place order")');
  await c.waitForURL(/orders\/\d+$/);
  await c.waitForSelector("text=Awaiting payment");
  if (await c.locator("text=Waiting for partners").count()) fail("a seller's product asked partners for quotes");
  await c.click('button:has-text("Pay")');
  await c.waitForSelector("text=paid");
  ok("the customer buys the seller's product: straight to payment (no partner quotes), then paid");

  // ---- 6. The seller sees the paid order with the address and ships it
  await p.click('a:has-text("Sales")');
  await p.click("text=Ceramic brake pads");
  await p.waitForSelector("text=5 Olaya Street");
  await p.fill('[aria-label="Carrier name"]', "Aramex");
  await p.fill('[aria-label="Tracking number"]', "AX778899");
  await p.click('button:has-text("Save shipment")');
  await p.waitForSelector("text=AX778899");
  await p.selectOption('[aria-label="Set status"]', "delivered");
  await p.waitForSelector("text=Delivered");
  await c.reload();
  await c.waitForSelector("text=Completed");
  await c.waitForSelector("text=AX778899");
  ok("the seller ships it with any carrier; delivery completes the sale for the customer");

  // ---- 7. Money: held until delivery, then available; the admin pays it out and records the reference
  await p.click('a:has-text("Earnings")');
  await p.waitForSelector('[data-testid="balance-available"]');
  const available = await p.innerText('[data-testid="balance-available"]');
  if (!/81\.00/.test(available)) fail(`the seller should have 81.00 available (90 sold, 9 commission), saw: ${available}`);
  if (!/0\.00/.test(await p.innerText('[data-testid="balance-held"]'))) fail("delivered money is still held");
  ok("the seller's earnings show the sale minus the platform commission, released by delivery");

  const adm = await login("admin@test.com");
  await adm.p.waitForURL("**/dashboard");
  await adm.p.click('a:has-text("Payouts")');
  await adm.p.waitForSelector("text=Gulf Parts Brand");
  await adm.p.click('button[aria-label^="Prepare payout Gulf Parts Brand"]');
  await adm.p.waitForSelector('[aria-label="Bank reference"]');
  await adm.p.fill('[aria-label="Bank reference"]', "BANK-2026-0001");
  await adm.p.click('button:has-text("Mark paid")');
  await adm.p.waitForSelector("text=BANK-2026-0001");
  await p.reload();
  await p.waitForSelector('[data-testid="balance-paid"]');
  if (!/81\.00/.test(await p.innerText('[data-testid="balance-paid"]'))) fail("the paid payout is not shown to the seller");
  await p.waitForSelector("text=BANK-2026-0001");
  await p.click('a[aria-label^="Notifications"]');
  await p.waitForSelector("text=Payout sent");
  ok("the admin prepared and paid the payout with a bank reference; the seller sees it and was notified");

  console.log("\nSellers passed.");
} catch (e) {
  console.error("\nSellers FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
