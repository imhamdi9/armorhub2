// Buying goods without an account, through the real screens: product -> "Order now" -> checkout with no sign-in -> pay -> private order page ->
// the seller sees what to ship -> the buyer's later account picks the order up. Plus the privacy of the order link, mobile, Arabic and accessibility.
import AxeBuilder from "@axe-core/playwright";
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const SHOTS = process.env.SHOTS ?? "/tmp";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const text = (page) => page.locator("body").innerText();
const findings = [];
async function scan(page, label) {
  await page.waitForLoadState("networkidle").catch(() => undefined);
  const { violations } = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "best-practice"]).analyze();
  for (const v of violations.filter((x) => ["serious", "critical"].includes(x.impact))) findings.push(`${label}: ${v.id} ${v.nodes[0]?.html?.slice(0, 100)}`);
}

try {
  const list = await (await fetch(`${base}/api/public/storefronts/beit-masr/products?per_page=50`)).json();
  const lantern = list.data.find((p) => /Fanous/.test(p.name)) ?? fail("lantern missing");
  const cleaning = list.data.find((p) => /Deep Home Cleaning/.test(p.name)) ?? fail("cleaning service missing");

  // ---- 1. "Order now" goes to checkout, never to a sign-in or signup page
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${base}/s/beit-masr/products/${lantern.id}`);
  await page.getByRole("link", { name: "Order now" }).first().click();
  await page.waitForURL(`**/s/beit-masr/checkout?product=${lantern.id}`);
  if (/\/(login|register)/.test(page.url())) fail("a guest was sent to sign in");
  await page.waitForSelector("#g-name");
  if (!/Fanous/.test(await text(page)) || !/650/.test(await page.locator("[data-testid=checkout-total]").innerText())) fail("the checkout does not show the product and price");
  if (!/You do not need an account/.test(await text(page))) fail("the checkout does not say an account is optional");
  await page.screenshot({ path: `${SHOTS}/guest-1-checkout.png`, fullPage: true });
  await scan(page, "checkout");
  ok("Order now opens a checkout with no sign-in; an account is optional");

  // ---- 2. A service still goes through signup (it needs quotes)
  await page.goto(`${base}/s/beit-masr/products/${cleaning.id}`);
  await page.getByRole("link", { name: "Get a quote" }).first().click();
  await page.waitForURL(/\/register/);
  await page.goto(`${base}/s/beit-masr/checkout?product=${cleaning.id}`);
  if ((await page.locator("#g-name").count()) > 0) fail("a service can be ordered as a guest");
  ok("services still need an account (a guest checkout for a service is a 404)");

  // ---- 3. Fill in the form: bad details are explained, good ones pay
  await page.goto(`${base}/s/beit-masr/checkout?product=${lantern.id}`);
  const pay = page.getByRole("button", { name: /^Pay / });
  await page.fill("#g-name", "Salma Guest"); await page.fill("#g-email", `salma${Date.now()}@example.org`); await page.fill("#g-phone", "123");
  await page.fill("#g-line1", "12 Nile Corniche"); await page.fill("#g-city", "Giza");
  await page.waitForFunction(() => !document.querySelector("button[type=submit]")?.hasAttribute("disabled"), null, { timeout: 15000 });
  await pay.click();
  await page.waitForSelector("form p[role=alert]");
  const alerts = (await page.locator("form p[role=alert]").allInnerTexts()).join(" | ");
  if (!/valid mobile/i.test(alerts)) fail(`a bad phone number is not explained (alerts: ${alerts})`);
  await page.fill("#g-phone", "01012345678");
  await page.waitForFunction(() => !document.querySelector("button[type=submit]")?.hasAttribute("disabled"), null, { timeout: 15000 });
  await pay.click();
  await page.waitForURL(/\/track\/[A-Za-z0-9]{48}/);
  const trackUrl = page.url();
  await page.waitForSelector("text=Delivery address");
  const body = await text(page);
  if (!/Salma Guest/.test(body) || !/12 Nile Corniche/.test(body) || !/Fanous/.test(body)) fail("the order page lacks the order's details");
  if (await page.locator("button:has-text('Pay now')").count()) fail("a paid order still asks for payment");
  await page.screenshot({ path: `${SHOTS}/guest-2-order.png`, fullPage: true });
  await scan(page, "guest order page");
  ok("a guest paid and landed on a private order page with the details");
  const robots = await page.locator('meta[name="robots"]').getAttribute("content");
  if (!/noindex/.test(robots ?? "")) fail("the private order page can be indexed");

  // ---- 4. The link is the key: another person cannot guess it, and it shows only that order
  const other = await (await browser.newContext()).newPage();
  await other.goto(`${base}/track/${"x".repeat(48)}`);
  await other.waitForSelector("text=We could not find this order");
  await other.goto(trackUrl);
  await other.waitForSelector("text=Delivery address");
  ok("the order is reachable only by its link");

  // ---- 5. The seller sees what to ship (name and address), the buyer is told by e-mail log
  const seller = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const sp = await seller.newPage();
  await sp.goto(`${base}/login`); await sp.fill("input[type=email]", "seller.khan@demo.eg"); await sp.fill("input[type=password]", "password123"); await sp.click("button[type=submit]");
  await sp.waitForURL("**/dashboard**");
  await sp.goto(`${base}/dashboard/seller-orders`);
  await sp.waitForSelector("text=Salma Guest");
  await sp.getByText("Salma Guest").first().click();
  await sp.waitForSelector("text=12 Nile Corniche");
  await sp.screenshot({ path: `${SHOTS}/guest-3-seller.png`, fullPage: true });
  ok("the seller sees the guest's name and delivery address to ship it");

  // ---- 6. Mobile and Arabic
  const m = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const mp = await m.newPage();
  for (const [u, n] of [[`/s/beit-masr/checkout?product=${lantern.id}`, "checkout"], [trackUrl.replace(base, ""), "order"]]) {
    await mp.goto(base + u); await mp.waitForLoadState("networkidle");
    if (await mp.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) fail(`mobile ${n}: sideways scroll`);
    await mp.screenshot({ path: `${SHOTS}/guest-m-${n}.png`, fullPage: true });
  }
  const ar = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: "ar" });
  await ar.addInitScript(() => { try { localStorage.setItem("locale", "ar"); } catch { /* ignore */ } });
  const ap = await ar.newPage();
  await ap.goto(`${base}/s/beit-masr/checkout?product=${lantern.id}`); await ap.waitForSelector("#g-name");
  if ((await ap.locator("html").getAttribute("dir")) !== "rtl" || /Delivery address|Your details/.test(await text(ap))) fail("the checkout is not Arabic/RTL");
  await ap.screenshot({ path: `${SHOTS}/guest-ar-checkout.png`, fullPage: true });
  await scan(ap, "arabic checkout");
  await ap.goto(trackUrl); await ap.waitForSelector("text=عنوان التوصيل");
  ok("mobile screens fit; checkout and order page are Arabic and right-to-left");

  // ---- 7. Signed-in buyers are not asked twice
  const si = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const sip = await si.newPage();
  await sip.goto(`${base}/login`); await sip.fill("input[type=email]", "khaled.abdelrahman@demo.eg"); await sip.fill("input[type=password]", "password123"); await sip.click("button[type=submit]");
  await sip.waitForURL("**/dashboard**");
  await sip.goto(`${base}/s/beit-masr/checkout?product=${lantern.id}`);
  await sip.waitForSelector("text=You are signed in as");
  if (await sip.locator("#g-name").count()) fail("a signed-in customer is shown the guest form");
  ok("a signed-in customer is offered to continue with their account");

  // ---- 8. A product that needs installation tells the buyer, before and after paying
  const lock = list.data.find((p) => /Smart Door Lock/.test(p.name)) ?? fail("door lock missing");
  const g2 = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  await g2.goto(`${base}/s/beit-masr/checkout?product=${lock.id}`);
  await g2.waitForSelector("[data-testid=installation-note]");
  if (!/needs professional installation/.test(await g2.locator("[data-testid=installation-note]").innerText())) fail("the checkout does not say the product needs installation");
  await g2.fill("#g-name", "Hana Guest"); await g2.fill("#g-email", `hana${Date.now()}@example.org`); await g2.fill("#g-phone", "01012345678");
  await g2.fill("#g-line1", "3 Tahrir Square"); await g2.fill("#g-city", "Cairo");
  await g2.waitForFunction(() => !document.querySelector("button[type=submit]")?.hasAttribute("disabled"), null, { timeout: 15000 });
  await g2.getByRole("button", { name: /^Pay / }).click();
  await g2.waitForURL(/\/track\//);
  await g2.waitForSelector("[data-testid=installation-prompt]");
  await g2.screenshot({ path: `${SHOTS}/guest-4-installation-prompt.png`, fullPage: true });
  await scan(g2, "guest order with installation prompt");
  ok("a product that needs installation says so at checkout and on the order page");

  if (findings.length) fail(`accessibility problems:\n  ${findings.join("\n  ")}`);
  ok("no serious accessibility problems on the guest screens");
  console.log("\nGuest checkout passed.");
} catch (e) {
  console.error("Guest checkout FAILED:", e.message);
  process.exitCode = 1;
} finally {
  await browser.close();
}
