// Every main screen, per role, on a phone, in English and Arabic:
//  - no horizontal overflow, no page errors
//  - Arabic really switches to right-to-left with Arabic text
// Needs demo data (php artisan platform:demo). Screenshots go to $SHOTS (default /tmp).
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const shots = process.env.SHOTS ?? "/tmp";
const roles = {
  "admin@test.com": ["/dashboard", "/dashboard/orders", "/dashboard/products", "/dashboard/partners", "/dashboard/users",
    "/dashboard/tenants", "/dashboard/companies", "/dashboard/catalogs", "/dashboard/catalog/vehicle_model", "/dashboard/commissions", "/dashboard/notifications"],
  "shop@test.com": ["/dashboard", "/dashboard/requests", "/dashboard/jobs", "/dashboard/users", "/dashboard/partners"],
  customer: ["/dashboard", "/dashboard/orders", "/dashboard/orders/new", "/dashboard/warranties", "/dashboard/products", "/dashboard/items/vehicle_model"],
};
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const problems = [];
let checked = 0;

async function login(context, who) {
  const page = await context.newPage();
  if (who === "customer") {
    await page.goto(`${base}/register`);
    await page.fill("#name", "Mobile Tester");
    await page.fill("#email", `mobile+${Date.now()}@test.com`);
    await page.fill("#password", "password123");
    await page.fill("#password_confirmation", "password123");
    await page.fill("#company_id", "1");
    await page.click("button[type=submit]");
  } else {
    await page.goto(`${base}/login`);
    await page.fill("input[type=email]", who);
    await page.fill("input[type=password]", "password123");
    await page.click("button[type=submit]");
  }
  await page.waitForURL("**/dashboard");
  await page.waitForSelector("text=/Welcome|مرحبًا/");
  return page;
}

try {
  for (const locale of ["en", "ar"]) {
    for (const [who, pages] of Object.entries(roles)) {
      const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true });
      await context.addInitScript((l) => window.localStorage.setItem("locale", l), locale);
      const page = await login(context, who);
      const errors = [];
      page.on("pageerror", (e) => errors.push(e.message));

      for (const path of pages) {
        await page.goto(`${base}${path}`);
        await page.waitForLoadState("networkidle");
        await page.waitForTimeout(400);
        const m = await page.evaluate(() => ({
          overflow: document.documentElement.scrollWidth - window.innerWidth,
          dir: document.documentElement.dir,
          lang: document.documentElement.lang,
          text: document.body.innerText,
        }));
        checked++;
        const label = `${locale} ${who.split("@")[0]} ${path}`;
        if (m.overflow > 1) problems.push(`${label}: horizontal overflow of ${m.overflow}px`);
        if (locale === "ar" && (m.dir !== "rtl" || m.lang !== "ar")) problems.push(`${label}: dir=${m.dir} lang=${m.lang}`);
        if (locale === "ar" && !/[؀-ۿ]/.test(m.text)) problems.push(`${label}: no Arabic text on the page`);
        if (locale === "en" && m.dir === "rtl") problems.push(`${label}: unexpectedly RTL`);
        if (errors.length) problems.push(`${label}: page errors ${errors.join("; ")}`);
      }

      if (who === "admin@test.com") {
        await page.goto(`${base}/dashboard`);
        await page.waitForTimeout(500);
        await page.screenshot({ path: `${shots}/mobile-${locale}-dashboard.png` });
        await page.goto(`${base}/dashboard/orders`);
        await page.waitForTimeout(500);
        await page.screenshot({ path: `${shots}/mobile-${locale}-orders.png` });
      }
      await context.close();
    }
  }

  // Desktop Arabic: the sidebar moves to the right.
  const desktop = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  await desktop.addInitScript(() => window.localStorage.setItem("locale", "ar"));
  const d = await login(desktop, "admin@test.com");
  await d.waitForTimeout(600);
  const sidebarX = await d.evaluate(() => document.querySelector('[data-slot="sidebar-container"]')?.getBoundingClientRect().left ?? -1);
  if (!(sidebarX > 600)) problems.push(`ar desktop: sidebar not on the right (left=${sidebarX})`);
  await d.screenshot({ path: `${shots}/desktop-ar-dashboard.png` });

  // The switcher works both ways without a reload.
  await d.click('button[aria-label="Switch to English"]');
  await d.waitForSelector("text=Welcome");
  if ((await d.evaluate(() => document.documentElement.dir)) !== "ltr") problems.push("switching back to English did not restore LTR");

  console.log(`checked ${checked} page views`);
  if (problems.length) {
    console.error("\nProblems:\n - " + problems.join("\n - "));
    process.exitCode = 1;
  } else {
    console.log("Responsive + RTL passed.");
  }
} catch (e) {
  console.error("Responsive FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
