// Partner signup with approval, in a real browser: a business applies, cannot sign in until a reviewer
// approves, and gets the partner role the moment they do. A second application is rejected.
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const stamp = Date.now();
const PASSWORD = "Str0ng-Passw0rd!x";

async function apply(name, email) {
  const ctx = await browser.newContext({ geolocation: { latitude: 24.71, longitude: 46.68 }, permissions: ["geolocation"] });
  const p = await ctx.newPage();
  await p.goto(`${base}/partner-signup?store=armor-store`);
  await p.waitForSelector("#business_name");
  await p.fill("#business_name", name);
  await p.fill("#contact_name", "Sami Ali");
  await p.fill("#email", email);
  await p.fill("#phone", "0501112233");
  await p.fill("#city", "Riyadh");
  await p.fill("#description", "Certified film installers.");
  await p.click("text=Use my location");
  await p.waitForSelector("text=Location saved");
  await p.fill("#password", PASSWORD);
  await p.fill("#password_confirmation", PASSWORD);
  await sleep(4000);
  await p.click("button[type=submit]");
  await p.waitForSelector("text=Application received");
  await ctx.close();
}

async function tryLogin(email) {
  const ctx = await browser.newContext();
  const p = await ctx.newPage();
  await p.goto(`${base}/login`);
  await p.fill("input[type=email]", email);
  await p.fill("input[type=password]", PASSWORD);
  await p.click("button[type=submit]");
  return { p, ctx };
}

try {
  const good = `film${stamp}@filmco.example.org`;
  const bad = `shady${stamp}@filmco.example.org`;

  await apply("Fresh Film Co", good);
  await apply("Shady Garage", bad);
  ok("two businesses applied from the public form (with their location)");

  // Not approved yet: no access, but a clear message.
  let { p, ctx } = await tryLogin(good);
  await p.waitForSelector("text=still being reviewed");
  await ctx.close();
  ok("before approval the applicant cannot sign in and is told the application is under review");

  // The admin reviews.
  const actx = await browser.newContext();
  const a = await actx.newPage();
  await a.goto(`${base}/login`);
  await a.fill("input[type=email]", "admin@test.com");
  await a.fill("input[type=password]", "password123");
  await a.click("button[type=submit]");
  await a.waitForURL("**/dashboard");
  await a.click('a:has-text("Partner applications")');
  await a.waitForSelector("text=Fresh Film Co");
  await a.waitForSelector("text=Shady Garage");

  await a.locator("article:has-text('Fresh Film Co') >> text=Approve").click();
  await a.waitForSelector("text=Fresh Film Co", { state: "detached" });
  await a.locator("article:has-text('Shady Garage') >> [aria-label='Reason (optional)']").fill("Could not verify the business");
  await a.locator("article:has-text('Shady Garage') >> text=Reject").click();
  await a.waitForSelector("text=Shady Garage", { state: "detached" });
  ok("the admin approved one application and rejected the other with a reason");

  // The approved partner has the role immediately.
  ({ p, ctx } = await tryLogin(good));
  await p.waitForURL("**/dashboard");
  await p.waitForSelector('a:has-text("Requests")');
  await p.waitForSelector('a:has-text("Jobs")');
  if (await p.locator('a:has-text("Partner applications")').count()) fail("a new partner can see partner applications");
  if (await p.locator('a:has-text("Tenants")').count()) fail("a new partner can see admin screens");
  ok("the approved partner signs in at once with partner access (Requests, Jobs) and no admin screens");
  await ctx.close();

  ({ p, ctx } = await tryLogin(bad));
  await p.waitForSelector("text=was not approved");
  await ctx.close();
  ok("the rejected applicant is told it was not approved and gets no access");

  await a.click("[role=tab]:has-text('Approved')");
  await a.waitForSelector("text=Fresh Film Co");
  ok("the decision shows under Approved");

  console.log("\nPartners passed.");
} catch (e) {
  console.error("\nPartners FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
