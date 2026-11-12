// Restructure end to end: category taxonomy, SKU and cartons, warehouse (seller -> warehouse team -> shop), seller page, invitations.
import AxeBuilder from "@axe-core/playwright";
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const SHOTS = process.env.SHOTS ?? "/tmp";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const text = (page) => page.locator("body").innerText();
const signIn = async (email) => {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${base}/login`); await page.fill("input[type=email]", email); await page.fill("input[type=password]", "password123"); await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard**");
  return page;
};
const scan = async (page, name) => {
  const r = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa"]).analyze();
  const bad = r.violations.filter((v) => ["serious", "critical"].includes(v.impact));
  if (bad.length) fail(`${name}: ${bad.map((v) => v.id).join(", ")}`);
};

try {
  const admin = await signIn("admin@demo.eg");

  // ---- 1. The platform's category list: groups with Arabic names, an admin adds one
  await admin.goto(`${base}/dashboard/admin/categories`);
  await admin.waitForSelector("[data-testid=category-list]");
  const list = await text(admin);
  for (const need of ["Automotive", "السيارات", "Beauty and care", "Devices and electronics"]) if (!list.includes(need)) fail(`the category list lacks ${need}`);
  await admin.fill("#cat-key", "tyres"); await admin.fill("#cat-en", "Tyres"); await admin.fill("#cat-ar", "الإطارات"); await admin.selectOption("#cat-parent", { label: "Automotive" });
  await admin.getByRole("button", { name: "Add category" }).click();
  await admin.getByText("Tyres", { exact: false }).first().waitFor();
  await admin.screenshot({ path: `${SHOTS}/fulfil-1-categories.png`, fullPage: true });
  await scan(admin, "categories page");
  ok("the platform category list shows groups with Arabic names and an admin can add a category");

  // ---- 2. The market manager of Egypt has a warehouse screen; the admin opens one for the market
  await admin.goto(`${base}/dashboard/warehouse`);
  await admin.waitForSelector("#wh-name");
  await admin.fill("#wh-name", "Cairo Fulfilment Centre");
  await admin.getByRole("button", { name: "Add warehouse" }).click();
  await admin.getByText("Cairo Fulfilment Centre").first().waitFor();
  ok("a warehouse was opened for the market");

  // ---- 3. A seller sets cartons + SKU, chooses ArmorHub storage, and announces a delivery
  const seller = await signIn("seller.khan@demo.eg");
  await seller.goto(`${base}/dashboard/products`);
  await seller.locator("a[href^='/dashboard/products/']").first().click();
  await seller.waitForSelector("#sku");
  const sku = await seller.inputValue("#sku");
  if (!/^AH-EG-\d{6}$/.test(sku)) fail(`the product has no generated SKU: "${sku}"`);
  await seller.check("input[name=fulfilment][type=radio] >> nth=1");
  await seller.getByRole("button", { name: "Save details" }).click();
  await seller.getByText("Saved").first().waitFor();
  ok(`the product has an automatic SKU (${sku}) and ArmorHub storage was chosen`);
  const productUrl = seller.url();
  const productId = productUrl.split("/").pop().split("?")[0];

  await seller.goto(`${base}/dashboard/seller-warehouse`);
  await seller.waitForSelector("[data-testid=seller-stock]");
  await seller.locator("[data-testid=seller-stock] input[type=number]").first().fill("3");
  await seller.fill("#sw-carrier", "Bosta");
  await seller.getByRole("button", { name: "Announce delivery" }).click();
  await seller.waitForSelector("[data-testid=seller-inbound]");
  await seller.screenshot({ path: `${SHOTS}/fulfil-2-seller-warehouse.png`, fullPage: true });
  await scan(seller, "seller warehouse");
  ok("the seller announced a delivery of cartons to the warehouse");

  // ---- 4. Out of stock before the warehouse counts; then the warehouse receives (counting 2 of 3 cartons)
  const pub = await (await browser.newContext()).newPage();
  await pub.goto(`${base}/s/armorhub-eg/products/${productId}`);
  await pub.waitForSelector("[data-testid=fulfilled-badge]");
  if (!/Out of stock/.test(await text(pub))) fail("the product page does not say out of stock before the warehouse has any");

  await admin.goto(`${base}/dashboard/warehouse`);
  await admin.waitForSelector("[data-testid=inbound-list]");
  await admin.locator("[data-testid=inbound-list] input[type=number]").first().fill("2");
  await admin.getByRole("button", { name: "Confirm count and receive" }).first().click();
  await admin.waitForSelector("[data-testid=stock-table]");
  const stock = await admin.locator("[data-testid=stock-table]").innerText();
  if (!/\b24\b/.test(stock)) fail(`2 cartons of 12 should be 24 units in stock: ${stock}`);
  await admin.screenshot({ path: `${SHOTS}/fulfil-3-warehouse.png`, fullPage: true });
  await scan(admin, "warehouse page");
  ok("the warehouse counted 2 cartons and the stock shows 24 units");

  // Public pages are cached for a short while, so the badge may take a moment to follow the warehouse.
  for (let i = 0; i < 20; i++) {
    await pub.reload();
    await pub.waitForSelector("[data-testid=fulfilled-badge]");
    if (!/Out of stock/.test(await text(pub))) break;
    await pub.waitForTimeout(5000);
  }
  if (/Out of stock/.test(await text(pub))) fail("the product still says out of stock after receiving");
  ok("the product page says Fulfilled by ArmorHub and is back in stock");

  // ---- 5. "Sold by" opens the seller's page inside the market
  await pub.getByRole("link", { name: /Khan El-Khalili/ }).first().click();
  await pub.waitForURL(/\/sellers\/\d+/);
  await pub.waitForLoadState("networkidle");
  if (!/Khan El-Khalili/.test(await text(pub))) fail(`the seller page does not show the seller: ${(await text(pub)).slice(0, 300)} @ ${pub.url()}`);
  await pub.screenshot({ path: `${SHOTS}/fulfil-4-seller-page.png`, fullPage: true });
  await scan(pub, "seller page");
  ok("Sold by opens the seller's own page with its products");

  // ---- 6. Invitations: the seller invites a teammate; the accept page rejects a bad link
  await seller.goto(`${base}/dashboard/invitations`);
  await seller.fill("#iv-email", "teammate@demo.eg");
  await seller.selectOption("#iv-role", "seller");
  await seller.getByRole("button", { name: "Send invitation" }).click();
  await seller.getByText("teammate@demo.eg").first().waitFor();
  const roles = await seller.locator("#iv-role option").allInnerTexts();
  if (roles.filter((r) => r && !/Choose/.test(r)).join() !== "seller") fail(`a seller is offered roles it must not have: ${roles}`);
  await scan(seller, "invitations page");
  const bad = await (await browser.newContext()).newPage();
  await bad.goto(`${base}/invitation/${"x".repeat(48)}`);
  await bad.getByText("This invitation is no longer valid").waitFor();
  ok("a seller can invite only its own team role and an invalid invitation link is refused");

  // ---- 7. Departments: the admin sees the ten departments
  await admin.goto(`${base}/dashboard/departments`);
  await admin.waitForSelector("[data-testid=department]");
  if ((await admin.locator("[data-testid=department]").count()) !== 10) fail("the ten platform departments are not all listed");
  await scan(admin, "departments page");
  ok("the ten platform departments are listed for the admin");

  // ---- 8. Customers and technicians have no invitation or warehouse menus
  const cust = await signIn("nour.fawzy@demo.eg");
  const menu = await text(cust);
  if (/Invitations|Departments|Warehouse/.test(menu)) fail("a customer sees team or warehouse menus");
  ok("a customer sees no team or warehouse menus");
} finally {
  await browser.close();
}
