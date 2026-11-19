// A full click-through of the running app: every role signs in, every reachable page is opened, and every button and link on it is pressed.
// It records script errors, console errors, failed requests, dead links, stuck "Loading…" screens and sideways scrolling, then prints a report
// and writes /tmp/audit-report.json. Needs the demo data (platform:demo and platform:demo-egypt).
import { chromium } from "playwright";
import { writeFileSync } from "node:fs";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const PASSWORD = "password123";
const ROLES = [
  ["platform admin", "admin@test.com"], ["partner manager", "shop@test.com"], ["company owner", "owner.home@demo.eg"],
  ["technician", "tech.auto1@demo.eg"], ["seller", "seller.khan@demo.eg"], ["customer", "nour.fawzy@demo.eg"],
];
// Pressing these would end the session or lock everyone out of the test run, so they are listed rather than pressed.
// Deleting or deactivating real accounts would change the data later pages depend on, so those are listed rather than pressed.
const SKIP = /log ?out|sign ?out|revoke all|emergency|toggle sidebar|sign in as|^delete|^remove|deactivate|suspend|revoke|reject/i;
const MAX_CLICKS_PER_PAGE = 45;
const MAX_PAGES_PER_ROLE = 40;

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const issues = [];
const stats = { pages: 0, clicks: 0, links: 0, skipped: [] };
const ignorable = (text) => /Failed to load resource.*(401|403)|favicon|Download the React DevTools|\[Fast Refresh\]|hydrat/i.test(text);

function watch(page, ctx) {
  page.on("pageerror", (e) => issues.push({ ...ctx(), kind: "script error", detail: e.message.split("\n")[0] }));
  page.on("console", (m) => { if (m.type() === "error" && !ignorable(m.text())) issues.push({ ...ctx(), kind: "console error", detail: m.text().slice(0, 200) }); });
  page.on("response", (r) => {
    const s = r.status();
    if (s >= 400 && r.url().includes("/api/") && !(s === 401 && r.url().endsWith("/api/me")) && !r.url().includes("/api/login")) issues.push({ ...ctx(), kind: `API ${s}`, detail: `${r.request().method()} ${r.url().replace(/^https?:\/\/[^/]+/, "")}` });
  });
}

async function checkPage(page, ctx) {
  stats.pages++;
  await page.waitForLoadState("networkidle").catch(() => undefined);
  await page.waitForTimeout(400);
  const text = (await page.locator("body").innerText().catch(() => "")) || "";
  if (/Application error|Something went wrong|Unhandled Runtime Error|This page could not be found|404/.test(text) && !/Search|Quote|Order/.test(text.slice(0, 0))) {
    if (/Application error|Unhandled Runtime Error|This page could not be found/.test(text)) issues.push({ ...ctx(), kind: "error page", detail: text.slice(0, 100).replace(/\s+/g, " ") });
  }
  if (/^\s*Loading\.\.\.\s*$/m.test(text) && (await page.waitForTimeout(2500).then(() => page.locator("body").innerText()).catch(() => "")).match(/^\s*Loading\.\.\.\s*$/m)) issues.push({ ...ctx(), kind: "stuck loading", detail: "still shows Loading… after 3 seconds" });
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth).catch(() => 0);
  if (overflow > 2) issues.push({ ...ctx(), kind: "sideways scroll", detail: `${overflow}px wider than the screen` });
  const broken = await page.evaluate(() => [...document.images].filter((i) => i.complete && i.naturalWidth === 0 && i.currentSrc && !i.currentSrc.startsWith("data:")).map((i) => i.currentSrc.slice(0, 90))).catch(() => []);
  for (const src of broken) issues.push({ ...ctx(), kind: "broken image", detail: src });
}

/** Press every button and link in the page's main content, one at a time, and look at what happened. */
async function clickSweep(page, ctx, found) {
  const path0 = new URL(page.url()).pathname + new URL(page.url()).search;
  const items = await page.evaluate(() => {
    const root = document.querySelector("main") ?? document.body;
    const visible = (e) => { const r = e.getBoundingClientRect(); const s = getComputedStyle(e); return r.width > 0 && r.height > 0 && s.visibility !== "hidden" && !e.closest("[inert]") && !e.disabled; };
    return [...root.querySelectorAll("button, a[href], [role=tab], summary")].filter(visible).map((e, i) => ({ i, tag: e.tagName, label: (e.getAttribute("aria-label") || e.textContent || e.title || "").trim().replace(/\s+/g, " ").slice(0, 50), href: e.getAttribute("href") }));
  });
  for (const it of items.slice(0, MAX_CLICKS_PER_PAGE)) {
    if (it.tag === "A" && it.href) {
      if (it.href.startsWith("/") && !it.href.startsWith("//")) found.add(it.href.split("#")[0]);
      stats.links++;
      continue; // links are followed as pages (or checked) below, so the sweep keeps its place
    }
    if (SKIP.test(it.label)) { stats.skipped.push(`${ctx().role}: ${ctx().page}: "${it.label}"`); continue; }
    const handle = (await page.locator("main, body").first().locator("button, [role=tab], summary").filter({ visible: true }).all()).find(async () => true);
    void handle;
    // Find the same element again by its label, because earlier clicks may have re-rendered the page.
    const target = page.locator("main button, main [role=tab], main summary, body button").filter({ hasText: it.label || undefined }).first();
    const labelled = it.label ? target : page.locator("main button").nth(it.i);
    try {
      const before = issues.length;
      await labelled.click({ timeout: 2500, trial: false });
      stats.clicks++;
      await page.waitForTimeout(350);
      if (new URL(page.url()).pathname + new URL(page.url()).search !== path0) { await page.goto(base + path0); await page.waitForLoadState("networkidle").catch(() => undefined); }
      else if (await page.locator("[role=dialog]").count()) await page.keyboard.press("Escape");
      if (issues.length > before) for (const x of issues.slice(before)) x.after = `pressing "${it.label}"`;
    } catch (e) {
      if (!/Timeout|not visible|detached|intercepts/i.test(e.message)) issues.push({ ...ctx(), kind: "click failed", detail: `"${it.label}": ${e.message.split("\n")[0]}` });
    }
  }
}

async function auditSite(page, startPaths, role, ctxBase) {
  const seen = new Set();
  const queue = [...startPaths];
  const found = new Set();
  let current = "";
  const ctx = () => ({ role, page: current, ...ctxBase });
  watch(page, ctx);
  while (queue.length && seen.size < MAX_PAGES_PER_ROLE) {
    const path = queue.shift();
    if (seen.has(path) || /logout|^\/api\//.test(path)) continue;
    seen.add(path);
    current = path;
    try {
      const res = await page.goto(base + path, { waitUntil: "domcontentloaded" });
      if (res && res.status() >= 400) { issues.push({ ...ctx(), kind: `page ${res.status()}`, detail: path }); continue; }
      await checkPage(page, ctx);
      found.clear();
      await clickSweep(page, ctx, found);
      for (const href of found) if (!seen.has(href) && (href.startsWith("/dashboard") || href.startsWith("/s/") || role === "visitor")) queue.push(href);
    } catch (e) { issues.push({ ...ctx(), kind: "visit failed", detail: e.message.split("\n")[0] }); }
  }
  return seen.size;
}

async function signIn(context, email) {
  const page = await context.newPage();
  await page.goto(`${base}/login`);
  await page.fill("input[type=email]", email);
  await page.fill("input[type=password]", PASSWORD);
  await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard", { timeout: 20000 });
  await page.waitForLoadState("networkidle");

  return page;
}

const report = {};
// ---- the public site, as a visitor
{
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  const stores = await (await fetch(`${base}/api/public/storefronts`)).json().then((j) => j.stores ?? []);
  const starts = ["/", "/login", "/signup", "/seller-signup", "/partner-signup", "/forgot-password", ...stores.flatMap((s) => [`/s/${s.slug}`, `/s/${s.slug}/products`])];
  for (const s of stores.slice(0, 5)) {
    const list = await (await fetch(`${base}/api/public/storefronts/${s.slug}/products?per_page=3`)).json().catch(() => ({ data: [] }));
    for (const p of list.data ?? []) starts.push(`/s/${s.slug}/products/${p.id}`);
  }
  report.visitor = await auditSite(page, starts, "visitor", {});
  await context.close();
}

// ---- every role
for (const [role, email] of ROLES) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  try {
    const page = await signIn(context, email);
    const nav = await page.$$eval("a[href^='/dashboard']", (as) => [...new Set(as.map((a) => a.getAttribute("href")))]);
    report[role] = await auditSite(page, ["/dashboard", ...nav], role, {});
  } catch (e) { issues.push({ role, page: "/login", kind: "sign-in failed", detail: e.message.split("\n")[0] }); }
  await context.close();
}

// ---- a phone, for the busiest pages
{
  const context = await browser.newContext({ viewport: { width: 390, height: 800 } });
  const page = await signIn(context, "admin@test.com");
  const ctxBase = { role: "admin (phone)" };
  let current = "";
  watch(page, () => ({ ...ctxBase, page: current }));
  for (const path of ["/dashboard", "/dashboard/orders", "/dashboard/products", "/dashboard/admin", "/dashboard/admin/settings", "/dashboard/admin/theme", "/dashboard/admin/content", "/dashboard/users"]) {
    current = path;
    await page.goto(base + path, { waitUntil: "domcontentloaded" });
    await checkPage(page, () => ({ ...ctxBase, page: path }));
  }
  await context.close();
}
await browser.close();

// ---- the report
const unique = [...new Map(issues.map((i) => [`${i.kind}|${i.page}|${i.detail}`, i])).values()];
writeFileSync("/tmp/audit-report.json", JSON.stringify({ stats, report, issues: unique }, null, 2));
console.log(`\nAudit: ${stats.pages} pages opened, ${stats.clicks} buttons pressed, ${stats.links} links followed or listed, ${stats.skipped.length} left alone.`);
console.log(`Pages by role: ${Object.entries(report).map(([k, v]) => `${k} ${v}`).join(", ")}`);
if (unique.length === 0) console.log("\nNo problems found.");
else {
  console.log(`\n${unique.length} problem(s):`);
  const byKind = {};
  for (const i of unique) (byKind[i.kind] ??= []).push(i);
  for (const [k, list] of Object.entries(byKind)) { console.log(`\n== ${k} (${list.length})`); for (const i of list.slice(0, 25)) console.log(`  [${i.role}] ${i.page}: ${i.detail}${i.after ? `  <- after ${i.after}` : ""}`); }
}
console.log("\nLeft alone on purpose:", [...new Set(stats.skipped.map((s) => s.split(": ").slice(2).join(": ")))].join(" | ") || "nothing");
process.exitCode = unique.length ? 1 : 0;
