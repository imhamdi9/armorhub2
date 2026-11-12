// The platform owner runs ArmorHub by country: one market per country with several industries, opened in any country from the screen.
import AxeBuilder from "@axe-core/playwright";
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const SHOTS = process.env.SHOTS ?? "/tmp";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const text = (page) => page.locator("body").innerText();

try {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${base}/login`); await page.fill("input[type=email]", "admin@demo.eg"); await page.fill("input[type=password]", "password123"); await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard**");

  // ---- 1. The Markets screen shows ArmorHub Egypt with its four industries and its people
  await page.goto(`${base}/dashboard/admin/markets`);
  await page.waitForSelector("[data-testid=market-card]");
  const egypt = page.locator("[data-testid=market-card]", { hasText: "ArmorHub Egypt" });
  await egypt.waitFor();
  const t = await egypt.innerText();
  for (const need of ["Automotive", "Beauty", "Devices", "Home Services", "Sellers", "Service centres"]) if (!t.includes(need)) fail(`the Egypt market card lacks "${need}"`);
  await page.screenshot({ path: `${SHOTS}/markets-1-list.png`, fullPage: true });
  ok("the Markets screen shows ArmorHub Egypt with its four industries and its sellers and centres");

  // ---- 2. Open a market in another country (Brazil) with two industries
  const select = page.locator("#country");
  if ((await select.locator("option[value=EG]").count()) !== 0) fail("a country that already has a market is offered again");
  if ((await select.locator("option").count()) < 200) fail("the country list should cover the world");
  await select.selectOption("BR");
  if ((await page.inputValue("#mname")) !== "ArmorHub Brazil") fail("the market is not named after the country by default");
  const form = page.locator("form[aria-label='Open a new market']");
  await form.getByLabel("Automotive").check(); await form.getByLabel("Devices").check();
  await form.getByRole("button", { name: "Open the market" }).click();
  const brazil = page.locator("[data-testid=market-card]", { hasText: "ArmorHub Brazil" });
  await brazil.waitFor();
  if (!/BRL/.test(await brazil.innerText())) fail("the Brazilian market does not use the real (BRL)");
  if ((await select.locator("option[value=BR]").count()) !== 0) fail("Brazil is still offered after opening it");
  await page.screenshot({ path: `${SHOTS}/markets-2-opened.png`, fullPage: true });
  ok("ArmorHub Brazil was opened from the screen with two industries, in BRL");

  // ---- 3. Its public store exists at once; a second market in the same country is refused by the API
  const res = await fetch(`${base}/api/public/storefronts/armorhub-br`);
  const store = await res.json();
  if (res.status !== 200 || store.market?.currency !== "BRL" || store.industries?.length !== 2) fail(`the public store of the new market is wrong: ${res.status} ${JSON.stringify(store.market)}`);
  const pub = await ctx.newPage();
  await pub.goto(`${base}/s/armorhub-br`);
  await pub.waitForSelector("h1");
  if (!/ArmorHub Brazil/.test(await text(pub))) fail("the new market's store page does not show its name");
  ok("the new market has its own public store");

  // ---- 4. Edit: add an industry and pause
  await brazil.getByRole("button", { name: "Edit" }).click();
  await brazil.getByLabel("Beauty").check();
  await brazil.getByRole("button", { name: "Save" }).click();
  await page.waitForFunction(() => [...document.querySelectorAll("[data-testid=market-card]")].some((c) => /ArmorHub Brazil/.test(c.textContent) && /Beauty/.test(c.textContent)));
  await brazil.getByRole("button", { name: "Pause" }).click();
  await brazil.getByText("Paused", { exact: true }).waitFor();
  ok("a market can get more industries and be paused");

  // ---- 5. The store of the Egyptian market lists its industries; a visitor can filter by one
  await pub.goto(`${base}/s/armorhub-eg`);
  await pub.waitForSelector("[data-testid=market-industries]");
  await pub.getByRole("link", { name: "Beauty" }).first().click();
  await pub.waitForURL(/industry=beauty/);
  await pub.waitForSelector("[data-testid=industry-pills]");
  const names = await pub.locator("h3").allInnerTexts();
  if (names.length === 0 || names.some((n) => /Lantern|Charger|Film|Cleaning/.test(n))) fail(`filtering by industry shows other industries' products: ${names.join(", ")}`);
  await pub.screenshot({ path: `${SHOTS}/markets-3-store-industry.png`, fullPage: true });
  const { violations } = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"]).analyze();
  const bad = violations.filter((v) => ["serious", "critical"].includes(v.impact));
  if (bad.length) fail(`accessibility: ${bad.map((v) => v.id).join(", ")}`);
  ok("the market's store shows its industries and filters by one");

  console.log("\nMarkets passed.");
} catch (e) {
  console.error("Markets FAILED:", e.message);
  process.exitCode = 1;
} finally {
  await browser.close();
}
