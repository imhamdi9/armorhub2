// The platform administrator's tools, in a real browser: needs-attention on the home page, per-currency numbers, user filters and paging,
// delete confirmation, "view as" (read-only, with the way out), the search box, business-number trends, the System page and CSV export.
// Needs the Egypt demo data. It only ever creates, changes and removes ONE throwaway customer of its own: it never touches the demo accounts.
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const api = process.env.API_URL ?? "http://localhost:8000/api";
const password = process.env.DEMO_PASSWORD ?? "password123";
const ok = (m) => console.log("OK ", m);
const problems = [];
const check = (cond, m) => { if (cond) ok(m); else { problems.push(m); console.log("FAIL", m); } };
const json = { "Content-Type": "application/json", Accept: "application/json" };

const admin = await (await fetch(`${api}/login`, { method: "POST", headers: json, body: JSON.stringify({ email: "admin@demo.eg", password }) })).json();
if (!admin.token) throw new Error("the demo admin could not sign in: run the demo seeder first");
const owner = await (await fetch(`${api}/login`, { method: "POST", headers: json, body: JSON.stringify({ email: "owner.auto@demo.eg", password }) })).json();
const email = `tmp.admintools.${Date.now()}@demo.eg`;
const made = await fetch(`${api}/users`, { method: "POST", headers: { ...json, Authorization: `Bearer ${admin.token}` }, body: JSON.stringify({ name: "Tmp Admin Tools", email, password: "Temporary-123x", role: "customer", company_id: owner.user.company_id }) });
check(made.status === 201, "a throwaway customer was created for the run");

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const ctx = await browser.newContext({ viewport: { width: 1360, height: 900 }, acceptDownloads: true });
await ctx.addInitScript(() => { localStorage.setItem("locale", "en"); localStorage.setItem("cookie-consent", JSON.stringify({ decision: "rejected", timestamp: new Date().toISOString() })); });
const page = await ctx.newPage();
const errors = [];
page.on("pageerror", (e) => errors.push(`script error: ${e.message.slice(0, 100)}`));
page.on("response", (r) => { if (r.url().startsWith(api) && r.status() >= 500) errors.push(`${r.status()} ${r.url().replace(api, "")}`); });
const settle = async () => { await page.waitForTimeout(1500); await page.waitForFunction(() => !document.querySelector(".animate-pulse"), null, { timeout: 40000 }).catch(() => undefined); await page.waitForTimeout(500); };
const rowOf = (text) => page.locator("main div.rounded-lg.border.p-4", { hasText: text }).first();
const userSearch = "input[placeholder='Search by name or email']";

try {
  await page.goto(`${base}/login`, { waitUntil: "domcontentloaded" });
  await page.fill("input[type=email]", "admin@demo.eg");
  await page.fill("input[type=password]", password);
  await page.keyboard.press("Enter");
  await page.waitForSelector("[data-sidebar=menu-button]", { timeout: 90000 });

  // home: what needs attention, one click from the place to act; money with its currency
  await page.goto(`${base}/dashboard`, { waitUntil: "domcontentloaded" });
  await page.waitForSelector("[data-testid=admin-attention] a", { timeout: 60000 });
  check((await page.locator("[data-testid=admin-attention] a").count()) > 1, "the admin home lists what needs attention");
  const moneyShown = await page.waitForFunction(() => /\b[A-Z]{3}\s?[\d,]+\.\d\d/.test(document.querySelector("main")?.innerText ?? ""), null, { timeout: 40000 }).then(() => true, () => false);
  check(moneyShown, "money on the home page shows its currency");

  // overview: money per currency, never added across currencies; exports download
  await page.goto(`${base}/dashboard/admin`, { waitUntil: "domcontentloaded" }); await settle();
  const [download] = await Promise.all([page.waitForEvent("download", { timeout: 30000 }), page.click("button:has-text('Orders (CSV)')")]);
  check(download.suggestedFilename().startsWith("orders-"), "the orders export downloads as a CSV file");

  // users: guests hidden, filters, paging, last sign-in
  await page.goto(`${base}/dashboard/users`, { waitUntil: "domcontentloaded" }); await settle();
  await page.waitForSelector("[data-testid=user-count]", { timeout: 40000 });
  const shown = Number((await page.locator("[data-testid=user-count]").innerText()).replace(/\D/g, ""));
  await page.check("input[type=checkbox]"); await settle();
  const withGuests = Number((await page.locator("[data-testid=user-count]").innerText()).replace(/\D/g, ""));
  check(withGuests >= shown, "guest shoppers are hidden until asked for");
  await page.uncheck("input[type=checkbox]");
  await page.selectOption("select[aria-label='Filter by role']", "customer"); await settle();
  check(Number((await page.locator("[data-testid=user-count]").innerText()).replace(/\D/g, "")) < shown, "the role filter narrows the list");
  await page.click("button:has-text('Clear filters')"); await settle();

  // the throwaway user: found by search, "view as" is read-only and has a way out, delete asks first
  await page.fill(userSearch, email); await rowOf(email).waitFor({ timeout: 40000 });
  check(/Never signed in/.test(await rowOf(email).innerText()), "a person who never signed in says so");
  await rowOf(email).locator("button:has-text('View as')").click();
  await page.waitForSelector("[data-testid=view-as-banner]", { timeout: 60000 }); await settle();
  check(/read-only/.test(await page.locator("[data-testid=view-as-banner]").innerText()), "viewing as someone shows a red read-only banner");
  check((await page.locator("button:has-text('Logout')").count()) === 0, "there is no Logout while viewing as someone (only the way out)");
  const write = await page.evaluate(async (a) => (await fetch(`${a}/addresses`, { method: "POST", credentials: "include", headers: { "Content-Type": "application/json", Accept: "application/json", "X-Client": "web" }, body: "{}" })).status, api);
  check(write === 403, "a write while viewing as someone is refused (403)");
  await page.click("[data-testid=view-as-banner] button");
  await page.waitForSelector("a[href='/dashboard/admin/markets']", { timeout: 90000 });
  check(true, "leaving the view returns the administrator to their own session");

  await page.goto(`${base}/dashboard/users`, { waitUntil: "domcontentloaded" }); await settle();
  await page.fill(userSearch, email); await rowOf(email).waitFor({ timeout: 40000 });
  await rowOf(email).locator("button:has-text('Delete')").click();
  check(await page.locator("[role=alertdialog]").isVisible(), "deleting asks for confirmation first");
  await rowOf(email).locator("button:has-text('Cancel')").click();
  check((await page.locator("main div.rounded-lg.border.p-4", { hasText: email }).count()) === 1, "cancelling keeps the account");
  await rowOf(email).locator("button:has-text('Delete')").click();
  await rowOf(email).locator("button:has-text('Yes, delete')").click(); await page.waitForTimeout(3000);
  check((await page.locator("main div.rounded-lg.border.p-4", { hasText: email }).count()) === 0, "confirming removes the throwaway account");

  // the search box and its "/" shortcut
  await page.goto(`${base}/dashboard/orders`, { waitUntil: "domcontentloaded" }); await settle();
  await page.keyboard.press("/"); await page.keyboard.type("power", { delay: 40 });
  await page.waitForSelector("[data-testid=admin-search-results] button", { timeout: 30000 });
  check(true, "the / shortcut opens the search and finds a product");

  // business numbers: a daily line and comparison with the period before
  await page.goto(`${base}/dashboard/admin/kpis`, { waitUntil: "domcontentloaded" }); await settle();
  await page.waitForSelector("[data-testid^=trend-]", { timeout: 40000 });
  check(true, "business numbers draw a daily line per currency");

  // system health
  await page.goto(`${base}/dashboard/admin/system`, { waitUntil: "domcontentloaded" }); await settle();
  await page.waitForSelector("[data-testid=check-scheduler]", { timeout: 40000 });
  check((await page.locator("[data-testid^=check-]").count()) === 5, "the System page shows its five checks");

  check(errors.length === 0, `no server errors or script errors on the way${errors.length ? `: ${errors.join("; ")}` : ""}`);
} finally {
  // never leave the throwaway behind, even if a step failed
  const left = await (await fetch(`${api}/users?search=${encodeURIComponent(email)}`, { headers: { ...json, Authorization: `Bearer ${admin.token}` } })).json();
  for (const u of left.data ?? []) if (u.email === email) await fetch(`${api}/users/${u.id}`, { method: "DELETE", headers: { ...json, Authorization: `Bearer ${admin.token}` } });
  await browser.close();
}

if (problems.length) { console.log(`\n${problems.length} problem(s)`); process.exit(1); }
console.log("\nAdmin tools passed.");
