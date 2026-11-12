// Runs axe-core (the standard accessibility checker) over the main public and signed-in screens, in English and Arabic,
// and fails on serious or critical violations. Needs the demo data (platform:demo and platform:demo-egypt).
import AxeBuilder from "@axe-core/playwright";
import { chromium } from "playwright";
import { writeFileSync } from "node:fs";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const found = new Map();
let scanned = 0;

async function scan(page, label) {
  await page.waitForLoadState("networkidle").catch(() => undefined);
  await page.waitForTimeout(400);
  const { violations } = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "best-practice"]).analyze();
  scanned++;
  for (const v of violations) {
    const key = `${v.id}`;
    const entry = found.get(key) ?? { id: v.id, impact: v.impact, help: v.help, pages: new Set(), sample: "" };
    entry.pages.add(label);
    if (!entry.sample) entry.sample = (v.nodes[0]?.html ?? "").slice(0, 140);
    found.set(key, entry);
  }
}

const stores = await (await fetch(`${base}/api/public/storefronts`)).json().then((j) => j.stores ?? []);
const store = stores.find((s) => s.slug === "beit-masr") ?? stores[0];
const products = await (await fetch(`${base}/api/public/storefronts/${store.slug}/products?per_page=2`)).json().then((j) => j.data ?? []);
const publicPages = ["/", "/login", "/signup", "/seller-signup", "/forgot-password", `/s/${store.slug}`, `/s/${store.slug}/products`, ...products.map((p) => `/s/${store.slug}/products/${p.id}`)];

for (const [locale, name] of [["en", "English"], ["ar", "Arabic"]]) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale });
  await ctx.addInitScript((l) => { try { localStorage.setItem("locale", l); } catch { /* ignore */ } }, locale);
  const page = await ctx.newPage();
  for (const path of publicPages) { await page.goto(base + path); await scan(page, `${name}: ${path}`); }
  await ctx.close();
}

const accounts = [
  ["customer", "nour.fawzy@demo.eg", ["/dashboard", "/dashboard/orders", "/dashboard/orders/new", "/dashboard/warranties", "/dashboard/notifications"]],
  ["seller", "seller.khan@demo.eg", ["/dashboard", "/dashboard/seller-orders", "/dashboard/products", "/dashboard/earnings"]],
  ["partner manager", "shop@test.com", ["/dashboard", "/dashboard/requests", "/dashboard/jobs", "/dashboard/partners"]],
  ["company owner", "owner.home@demo.eg", ["/dashboard", "/dashboard/products", "/dashboard/users", "/dashboard/storefront", "/dashboard/partner-applications"]],
  ["platform admin", "admin@test.com", ["/dashboard", "/dashboard/admin", "/dashboard/admin/settings", "/dashboard/admin/theme", "/dashboard/admin/content", "/dashboard/admin/roles", "/dashboard/admin/audit", "/dashboard/admin/platform", "/dashboard/users"]],
];
for (const [role, email, paths] of accounts) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${base}/login`);
  await page.fill("input[type=email]", email); await page.fill("input[type=password]", "password123"); await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard", { timeout: 20000 });
  for (const path of paths) { await page.goto(base + path); await scan(page, `${role}: ${path}`); }
  await ctx.close();
}
await browser.close();

const list = [...found.values()].sort((a, b) => ["critical", "serious", "moderate", "minor"].indexOf(a.impact) - ["critical", "serious", "moderate", "minor"].indexOf(b.impact));
writeFileSync("/tmp/a11y-report.json", JSON.stringify(list.map((v) => ({ ...v, pages: [...v.pages] })), null, 2));
console.log(`\nAccessibility scan: ${scanned} screens.`);
for (const v of list) console.log(`\n[${v.impact}] ${v.id}: ${v.help}\n  on ${v.pages.size} screen(s), e.g. ${[...v.pages][0]}\n  ${v.sample}`);
const blocking = list.filter((v) => v.impact === "critical" || v.impact === "serious");
console.log(blocking.length ? `\n${blocking.length} serious or critical issue(s).` : "\nNo serious or critical issues.");
process.exitCode = blocking.length ? 1 : 0;
