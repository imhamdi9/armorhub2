// Staff management: admin adds a partner manager, who adds a technician; deactivation blocks login;
// forgot-password responds. Needs demo data (php artisan platform:demo).
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const stamp = Date.now();
const manager = `manager${stamp}@test.com`;
const tech = `tech${stamp}@test.com`;
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });

async function login(email, password = "password123") {
  const p = await (await browser.newContext()).newPage();
  await p.goto(`${base}/login`);
  await p.fill("input[type=email]", email);
  await p.fill("input[type=password]", password);
  await p.click("button[type=submit]");
  return p;
}

try {
  const a = await login("admin@test.com");
  await a.waitForSelector("text=Welcome");
  await a.click('a:has-text("Team")');
  await a.click('button:has-text("Add member")');
  await a.fill('input[placeholder="Full name"]', "Mona Manager");
  await a.fill('input[placeholder="Email"]', manager);
  await a.fill('input[placeholder^="Temporary password"]', "temporary123");
  await a.selectOption("form select >> nth=0", { label: "Partner manager" });
  await a.selectOption("form select >> nth=1", { label: "Shop Co" });
  await a.selectOption("form select >> nth=2", { label: "Shop One" });
  await a.click('button:has-text("Create account")');
  await a.waitForSelector(`text=${manager}`);
  ok("admin created a partner manager");

  const m = await login(manager, "temporary123");
  await m.waitForSelector("text=Welcome, Mona Manager");
  const nav = (await m.locator("[data-sidebar=menu-button]").allInnerTexts()).join("|");
  if (!nav.includes("Requests") || !nav.includes("Team")) throw new Error("manager nav wrong: " + nav);
  ok("new manager signed in and sees Requests + Team");

  await m.click('a:has-text("Team")');
  await m.click('button:has-text("Add member")');
  await m.fill('input[placeholder="Full name"]', "Tom Tech");
  await m.fill('input[placeholder="Email"]', tech);
  await m.fill('input[placeholder^="Temporary password"]', "temporary123");
  await m.click('button:has-text("Create account")');
  await m.waitForSelector(`text=${tech}`);
  ok("manager created a technician for their own partner");

  await a.reload();
  await a.fill('input[placeholder="Search by name or email"]', tech);
  await a.waitForSelector(`text=${tech}`);
  await a.locator('div.rounded-lg:has-text("Tom Tech") button:has-text("Deactivate")').click();
  await a.waitForSelector("text=Deactivated");
  const t = await login(tech, "temporary123");
  await t.waitForSelector("text=deactivated");
  ok("deactivated account cannot sign in");

  const f = await (await browser.newContext()).newPage();
  await f.goto(`${base}/forgot-password`);
  await f.fill("#email", "nobody@test.com");
  await f.click('button:has-text("Send reset link")');
  await f.waitForSelector("text=If that email has an account");
  ok("forgot-password gives a neutral answer");
  console.log("\nStaff passed.");
} catch (e) {
  console.error("\nStaff FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
