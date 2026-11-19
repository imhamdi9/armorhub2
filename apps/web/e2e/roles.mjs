// One journey per role: sign in, check the menu shows what the role needs (and nothing the API refuses), then open every
// menu page and fail on any API error (401/403/5xx) or script error. Needs the Egypt demo data (php artisan platform:demo-egypt).
// Staff accounts (support, operations, warehouse, catalogue, marketing) are created for the run through the admin API.
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const api = process.env.API_URL ?? "http://localhost:8000/api";
const demoPassword = process.env.DEMO_PASSWORD ?? "password123";
const staffPassword = "Str0ng-Passw0rd!x";
const stamp = Date.now();
const ok = (m) => console.log("OK ", m);
const problems = [];
const fail = (m) => { problems.push(m); console.log("FAIL", m); };

const json = { "Content-Type": "application/json", Accept: "application/json" };
const login = async (email, password) => (await (await fetch(`${api}/login`, { method: "POST", headers: json, body: JSON.stringify({ email, password }) })).json());

// ---- accounts -------------------------------------------------------------------------------------------------
const admin = await login("admin@demo.eg", demoPassword);
const owner = await login("owner.auto@demo.eg", demoPassword);
if (!admin.token || !owner.token) throw new Error("demo admin/owner could not sign in: run the demo seeder first");
const staff = {};
for (const role of ["support-agent", "operations-agent", "warehouse-staff", "catalog-reviewer", "marketing-manager"]) {
  const email = `e2e.${role}.${stamp}@demo.eg`;
  const r = await fetch(`${api}/users`, { method: "POST", headers: { ...json, Authorization: `Bearer ${admin.token}` }, body: JSON.stringify({ name: `E2E ${role}`, email, password: staffPassword, role, company_id: owner.user.company_id }) });
  if (!r.ok) { fail(`could not create ${role}: ${r.status} ${(await r.text()).slice(0, 120)}`); continue; }
  staff[role] = email;
}

// what each role's menu must contain / must not contain
const ROLES = [
  { role: "platform-admin", email: "admin@demo.eg", password: demoPassword, has: ["Products", "Markets", "Users"], lacks: [] },
  { role: "company-owner", email: "owner.auto@demo.eg", password: demoPassword, has: ["Orders", "Products", "Team"], lacks: ["Markets"] },
  { role: "partner-manager", email: "manager.auto1@demo.eg", password: demoPassword, has: ["Requests", "Jobs", "Wallet"], lacks: ["Markets", "Users"] },
  { role: "technician", email: "tech.auto1@demo.eg", password: demoPassword, has: ["Jobs"], lacks: ["Requests", "Products", "Orders"] },
  { role: "seller", email: "seller.aswan@demo.eg", password: demoPassword, has: ["Sales", "Products", "Earnings"], lacks: ["Requests", "Orders"] },
  { role: "customer", email: "dina.farouk@demo.eg", password: demoPassword, has: ["Orders", "Cart", "Warranties"], lacks: ["Requests", "Team"] },
  { role: "support-agent", email: staff["support-agent"], password: staffPassword, has: ["Support", "Orders"], lacks: ["Products", "Marketing"] },
  { role: "operations-agent", email: staff["operations-agent"], password: staffPassword, has: ["Business numbers", "Orders"], lacks: ["Marketing", "Markets"] },
  { role: "warehouse-staff", email: staff["warehouse-staff"], password: staffPassword, has: ["Warehouse", "Orders"], lacks: ["Marketing", "Support"] },
  { role: "catalog-reviewer", email: staff["catalog-reviewer"], password: staffPassword, has: ["Product reviews"], lacks: ["Orders", "Products"] },
  { role: "marketing-manager", email: staff["marketing-manager"], password: staffPassword, has: ["Marketing"], lacks: ["Orders", "Products"] },
];

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });

for (const r of ROLES) {
  if (!r.email) continue;
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
  await ctx.addInitScript(() => { localStorage.setItem("locale", "en"); localStorage.setItem("cookie-consent", JSON.stringify({ decision: "rejected", timestamp: new Date().toISOString() })); });
  const page = await ctx.newPage();
  let errors = [];
  page.on("pageerror", (e) => errors.push(`script error: ${e.message.slice(0, 100)}`));
  page.on("response", (res) => { const u = res.url(); if (u.startsWith(api) && [401, 403].concat([500, 502, 503]).includes(res.status()) && !u.endsWith("/me")) errors.push(`${res.status()} ${u.replace(api, "")}`); });

  await page.goto(`${base}/login`, { waitUntil: "domcontentloaded" });
  await page.fill("input[type=email]", r.email);
  await page.fill("input[type=password]", r.password);
  await page.keyboard.press("Enter");
  await page.waitForSelector("[data-sidebar=menu-button]", { timeout: 90000 }).catch(() => undefined);

  const names = (await page.locator("[data-sidebar=menu-button]").allInnerTexts()).map((t) => t.trim());
  if (names.length === 0) { fail(`${r.role}: no menu after signing in`); await ctx.close(); continue; }
  for (const n of r.has) if (!names.includes(n)) fail(`${r.role}: menu should have "${n}" (has: ${names.join(", ")})`);
  for (const n of r.lacks) if (names.includes(n)) fail(`${r.role}: menu must not have "${n}"`);

  const links = [...new Set(await page.$$eval("a[href^='/dashboard']", (as) => as.map((a) => a.getAttribute("href")).filter((h) => h && !h.includes("?"))))];
  for (const href of links) {
    errors = [];
    await page.goto(`${base}${href}`, { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(1500);
    await page.waitForFunction(() => !document.querySelector(".animate-pulse"), null, { timeout: 30000 }).catch(() => errors.push("still loading after 30s"));
    if (errors.length) fail(`${r.role} ${href}: ${[...new Set(errors)].slice(0, 3).join("; ")}`);
  }
  ok(`${r.role}: menu is right and ${links.length} pages opened`);
  await ctx.close();
}

await browser.close();
if (problems.length) { console.log(`\n${problems.length} problem(s)`); process.exit(1); }
console.log("\nAll roles passed.");
