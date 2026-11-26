// A brand sells its own products in a marketplace, end to end in a real browser: it applies with documents, a platform
// admin verifies and approves, the seller lists a product, a customer buys it (straight to payment, no partner quotes)
// and the seller ships it. Needs demo data; run after the partners check (same database).
import "./_test-setup.mjs";
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
  // a business picks its plan first (the free one here); the application form follows
  await s.waitForURL("**/pricing**");
  await s.click("[data-testid=choose-free]");
  await s.waitForURL("**/seller-signup**");
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
  await s.waitForSelector("#err-tax_number");                       // the message sits right under the field
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
    // whole menu names, not pieces of words: a seller's own "Price requests" is not the partners' "Requests"
    if (await p.locator("[data-sidebar=menu-button]").filter({ hasText: new RegExp(`^\\s*${hidden}\\s*$`, "i") }).count()) fail(`a seller can see "${hidden}"`);
  }
  await p.click('a:has-text("Products")');
  await p.click('button:has-text("Add product")');
  await p.fill("#name", "Ceramic brake pads");
  // a product is written in both languages (the reviewers read both) and says what kind it is
  await p.fill("#name_ar", "تيل فرامل سيراميك");
  await p.fill("#desc_en", "Ceramic brake pads that stop quietly and leave very little dust on the wheels.");
  await p.fill("#desc_ar", "تيل فرامل سيراميك يتوقف بهدوء ويترك غبارًا قليلًا جدًا على العجلات.");
  if (await p.locator("select#industry").count()) await p.selectOption("select#industry", { index: 1 });
  // Category is picked from the list in use (or added as new); it is never left empty.
  await p.waitForSelector("select#category");
  await p.selectOption("select#category", "spare-parts");
  await p.fill("#price", "90");
  await p.click('button:has-text("Save")');
  // saving opens the new product's own page
  await p.waitForURL(/dashboard\/products\/\d+/);
  // the product's own page shows what was saved
  await p.waitForSelector("text=/Base price: 90\\.00/");
  ok("the approved seller signs in with seller-only screens and lists a product with a price");

  // NOTE: this script used to go on to a customer ordering the product by car part, the seller shipping it, the earnings and the payout.
  // Those screens have since been replaced (a new product is a draft until ArmorHub approves it; customers buy through the cart and a
  // checkout), so that part no longer matched the product. The same chain is checked by the API tests (SellerTest: a sale goes straight to
  // payment and the seller ships it; SellerPayoutTest: earnings are held then released, the admin pays out and the seller is told) and by the
  // guest-checkout phase in the browser. Writing it again for the cart and approval flow is still to do.
  console.log("\nSellers passed.");
} catch (e) {
  console.error("\nSellers FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
