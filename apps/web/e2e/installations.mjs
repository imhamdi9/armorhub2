// The whole installation journey through the real screens, with the Egyptian demo data:
// product page prompt -> request with allowed location -> anonymous offers from two centres -> ranked, no names -> accept terms and pay ->
// details + substitute number + Google Maps -> centre uploads proof -> customer confirms and rates -> centre withdraws -> admin pays;
// then a dispute with evidence from both sides decided by an admin (partial refund), a violation report with a strike,
// and mobile / Arabic / accessibility checks of the main screens.
import AxeBuilder from "@axe-core/playwright";
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const SHOTS = process.env.SHOTS ?? "/tmp";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const PASSWORD = "password123";
const png = Buffer.from("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==", "base64");
const file = (name) => ({ name, mimeType: "image/png", buffer: png });

async function login(email, { geo = false, locale } = {}) {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ...(geo ? { geolocation: { latitude: 30.0626, longitude: 31.2197 }, permissions: ["geolocation"] } : {}), ...(locale ? { locale } : {}) });
  if (locale) await ctx.addInitScript((l) => { try { localStorage.setItem("locale", l); } catch { /* ignore */ } }, locale);
  const page = await ctx.newPage();
  page.on("dialog", (d) => d.dismiss());
  await page.goto(`${base}/login`);
  await page.fill("input[type=email]", email); await page.fill("input[type=password]", PASSWORD); await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard**");
  return page;
}
const text = (page) => page.locator("body").innerText();
async function api(email) {  // a bearer-token client for quick set-up of extra jobs
  const login = await (await fetch(`${base}/api/login`, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify({ email, password: PASSWORD }) })).json();
  const token = login.token ?? fail(`no token for ${email}`);
  return async (method, path, body) => {
    const res = await fetch(`${base}/api${path}`, { method, headers: { "Content-Type": "application/json", Accept: "application/json", Authorization: `Bearer ${token}` }, body: body ? JSON.stringify(body) : undefined });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) fail(`${method} ${path} -> ${res.status} ${JSON.stringify(json)}`);
    return json;
  };
}
const axeFindings = [];
async function scan(page, label) {
  await page.waitForLoadState("networkidle").catch(() => undefined);
  const { violations } = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "best-practice"]).analyze();
  for (const v of violations.filter((x) => ["serious", "critical"].includes(x.impact))) axeFindings.push(`${label}: ${v.id} (${v.impact}) ${v.nodes[0]?.html?.slice(0, 100)}`);
}
const noOverflow = async (page, label) => { if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) fail(`${label}: the page scrolls sideways`); };

try {
  // ---- 0. The product form asks whether (and how) the product comes with installation
  const owner = await login("owner.auto@demo.eg");
  await owner.goto(`${base}/dashboard/products`);
  await owner.getByRole("button", { name: "Add product" }).click();
  await owner.waitForSelector("fieldset:has-text('Installation')");
  const radios = await owner.locator("input[name=installation]").count();
  if (radios !== 3) fail(`expected three installation choices, got ${radios}`);
  if (!(await owner.locator("input[name=installation][value=none]").isChecked())) fail("a new product should default to 'sold on its own'");
  await owner.fill("#name", "Roof rack kit");
  await owner.waitForSelector("select#category, input[aria-label='New category']");   // the field appears once the list of categories has loaded
  if (await owner.locator("select#category").count()) await owner.selectOption("select#category", "__new__");
  await owner.fill("input[aria-label='New category']", "roof-racks");
  await owner.check("input[name=installation][value=required]");
  await owner.fill("#price", "1200");
  await owner.getByRole("button", { name: "Save" }).click();
  await owner.waitForURL(/dashboard\/products\/\d+/);
  await owner.reload();
  await owner.waitForSelector("input[name=installation]");
  if (!(await owner.locator("input[name=installation][value=required]").isChecked())) fail("the installation choice was not saved");
  await owner.check("input[name=installation][value=optional]");
  await owner.getByRole("button", { name: "Save details" }).click();
  await owner.waitForSelector("text=Saved");
  await owner.reload();
  await owner.waitForSelector("input[name=installation]");
  if (!(await owner.locator("input[name=installation][value=optional]").isChecked())) fail("changing the installation choice did not stick");
  await owner.screenshot({ path: `${SHOTS}/inst-0-product-form.png`, fullPage: true });
  await scan(owner, "product form");
  ok("the product form offers three installation choices, saves and changes them");

  const customer = await login("nour.fawzy@demo.eg", { geo: true });

  // ---- 1. The product page offers installation only where it applies
  const dev = await (await fetch(`${base}/api/public/storefronts/cairo-devices/products?per_page=50`)).json();
  const glass = dev.data.find((p) => /Tempered Glass/.test(p.name)) ?? fail("glass product missing");
  const charger = dev.data.find((p) => /Fast Charger/.test(p.name)) ?? fail("charger missing");
  await customer.goto(`${base}/s/cairo-devices/products/${glass.id}`);
  await customer.waitForSelector("[data-testid=buy-buttons] a");
  if ((await customer.locator("[data-testid=buy-buttons] a").count()) !== 2) fail("a product with optional installation should offer two buy choices");
  await customer.goto(`${base}/s/cairo-devices/products/${charger.id}`);
  await customer.waitForSelector("h1");
  await customer.waitForSelector("[data-testid=buy-buttons] a");
  if ((await customer.locator("[data-testid=buy-buttons] a").count()) !== 1) fail("a product that needs no fitting offers more than one buy choice");
  ok("only products that can be fitted offer installation");
  await customer.goto(`${base}/s/cairo-devices/products/${glass.id}`);
  await customer.getByRole("link", { name: "Buy now and find me a suitable installation offer" }).click();
  await customer.waitForURL("**/dashboard/installations/new**");

  // ---- 2. A request needs the consent and the phone's own location
  const send = customer.getByRole("button", { name: "Send request" });
  if (!(await send.isDisabled())) fail("the request can be sent without a location");
  await customer.fill("#subject", "Toyota Camry 2020"); await customer.fill("#parts", "Hood\nBumper");
  const share = customer.getByRole("button", { name: "Share my location" });
  if (!(await share.isDisabled())) fail("location can be shared before consenting");
  await customer.check("input[type=checkbox]");
  await share.click();
  await customer.waitForSelector("[data-testid=location-ok]");
  await customer.screenshot({ path: `${SHOTS}/inst-1-new-request.png` });
  await scan(customer, "new request");
  await send.click();
  await customer.waitForURL(/dashboard\/installations\/\d+$/);
  const jobId = Number(customer.url().match(/(\d+)$/)[1]);
  await customer.waitForSelector("text=Waiting for offers");
  ok("the request was sent with the customer's allowed location");

  // ---- 3. Two centres answer: they see the job, never the customer
  const nasr = await login("manager.auto2@demo.eg");
  const zam = await login("manager.auto1@demo.eg");
  for (const [page, price, days] of [[nasr, "9000", "2"], [zam, "7000", "3"]]) {
    await page.goto(`${base}/dashboard/centre-requests`);
    await page.waitForSelector("[data-testid=open-request]");
    const body = await text(page);
    if (/Nour|nour\.fawzy|30\.06/.test(body)) fail("a centre can see who the customer is");
    if (!/Toyota Camry 2020/.test(body) || !/Hood · Bumper/.test(body)) fail("the centre cannot see what to do");
    await page.fill("input[aria-label='Your price']", price); await page.fill("input[aria-label='Days needed']", days);
    await page.fill("input[aria-label='Note']", "call me 01012345678");
    await page.getByRole("button", { name: "Send offer" }).click();
    await page.waitForSelector("[role=alert]");
    await page.fill("input[aria-label='Note']", "We use XPEL film");
    await page.getByRole("button", { name: "Send offer" }).click();
    await page.waitForSelector("text=your offer");
  }
  await nasr.screenshot({ path: `${SHOTS}/inst-2-centre-requests.png` });
  await scan(nasr, "centre requests");
  ok("centres see the job without the customer, and phone numbers in offers are refused");

  // ---- 4. The customer compares ranked, anonymous offers
  await customer.reload();
  await customer.waitForSelector("[data-testid=offer]");
  const offers = await customer.locator("[data-testid=offer]").allInnerTexts();
  if (offers.length !== 2) fail(`expected 2 offers, got ${offers.length}`);
  const page1 = await text(customer);
  if (/Nasr City|Zamalek|Studio|Auto Film/.test(page1)) fail("the offers show a centre's name");
  if (!/Centre C-[0-9A-Z]{4}/.test(offers[0])) fail("offers are not shown by code");
  await customer.screenshot({ path: `${SHOTS}/inst-3-offers.png`, fullPage: true });
  await scan(customer, "offers");
  ok(`two offers shown by code, best first: ${offers.map((o) => o.match(/EGP[\s ]*[\d,.]+|[\d,.]+[\s ]*EGP|ج\.م[\s ]*[\d,.]+/)?.[0]).join(" / ")}`);

  // ---- 5. Accept the terms, pay
  // Both centres are new (same record), so the cheaper offer is listed first; the journey below is about Nasr City Auto Film (EGP 9,000).
  await customer.locator("[data-testid=offer]", { hasText: /9,000/ }).getByRole("button", { name: "Choose this offer" }).click();
  const accept = customer.getByRole("button", { name: "Accept offer" });
  if (!(await accept.isDisabled())) fail("an offer can be accepted without the terms");
  await customer.check("[role=dialog] input[type=checkbox]");
  await accept.click();
  await customer.waitForSelector("text=Pay to confirm");
  await customer.screenshot({ path: `${SHOTS}/inst-4-pay.png` });
  await customer.getByRole("button", { name: "Pay now" }).click();
  await customer.waitForSelector("text=Your centre");
  const afterPay = await text(customer);
  if (!/Nasr City Auto Film/.test(afterPay)) fail("the centre's name is not shown after payment");
  const maps = await customer.locator("a:has-text('Open in Google Maps')").getAttribute("href");
  if (!maps?.startsWith("https://www.google.com/maps/search/?api=1&query=")) fail(`bad maps link ${maps}`);
  const proxy = await customer.locator("[data-testid=proxy-number]").innerText();
  if (!/ext\./.test(proxy) || /\+20 10/.test(afterPay)) fail("the substitute number is missing or the real phone is shown");
  await customer.screenshot({ path: `${SHOTS}/inst-5-paid.png`, fullPage: true });
  await scan(customer, "paid job");
  ok("after paying: centre name, Google Maps link and a substitute number (not the real phone)");

  // ---- 6. The centre sees the customer, starts, and sends proof
  await nasr.goto(`${base}/dashboard/centre-jobs/${jobId}`);
  await nasr.waitForSelector("text=Nour El-Din Fawzy");
  if (!(await nasr.locator("a:has-text('Open the place in Google Maps')").count())) fail("the centre has no map link to the job");
  if (!/8,?550/.test(await text(nasr))) fail("the centre does not see what it earns (9,000 less 5%)");
  await nasr.getByRole("button", { name: "Start the work" }).click();
  await nasr.waitForSelector("text=Work in progress");
  await nasr.setInputFiles("#before", [file("before.png")]); await nasr.setInputFiles("#after", [file("after.png")]);
  await nasr.fill("#serial", "SN-2201");
  await nasr.getByRole("button", { name: "Send proof" }).click();
  await nasr.waitForSelector("text=Waiting for the customer");
  await nasr.screenshot({ path: `${SHOTS}/inst-6-centre-job.png`, fullPage: true });
  await scan(nasr, "centre job");
  ok("the centre sees the customer after payment and sent the proof");

  // ---- 7. The customer checks the photos, confirms and rates
  await customer.reload();
  await customer.waitForSelector("text=Is the work done?");
  const imgs = customer.locator("img[alt='Before'], img[alt='After']");
  await imgs.first().waitFor();
  const broken = await imgs.evaluateAll((els) => els.filter((i) => !(i.complete && i.naturalWidth > 0)).length);
  if (broken) fail("a proof photo did not load for the customer");
  await customer.screenshot({ path: `${SHOTS}/inst-7-confirm.png`, fullPage: true });
  await customer.getByRole("button", { name: "Yes, confirm and release the payment" }).click();
  await customer.waitForSelector("text=Rate the centre");
  await customer.getByRole("button", { name: "Send rating" }).click();
  await customer.waitForSelector("text=Your rating");
  await customer.goto(`${base}/dashboard/installations`);
  await customer.waitForSelector("[data-testid=loyalty]");
  if (!/905/.test(await customer.locator("[data-testid=loyalty]").innerText())) fail("loyalty points are not 900 + 5");
  ok("confirmed and rated; loyalty points earned");

  // ---- 8. Wallet and withdrawal
  await nasr.goto(`${base}/dashboard/wallet`);
  await nasr.waitForSelector("[data-testid=wallet-available]");
  if (!/8,?550/.test(await nasr.locator("[data-testid=wallet-available]").innerText())) fail("the wallet does not show 8,550 available");
  await nasr.fill("#amount", "5000"); await nasr.fill("#account_name", "Nasr City Auto Film"); await nasr.fill("#account_number", "EG380019000500000000263180002"); await nasr.fill("#bank_name", "NBE");
  await nasr.getByRole("button", { name: "Request withdrawal" }).click();
  await nasr.waitForSelector("text=Your withdrawal request was sent.");
  await nasr.screenshot({ path: `${SHOTS}/inst-8-wallet.png`, fullPage: true });
  await scan(nasr, "wallet");
  const admin = await login("admin@demo.eg");
  await admin.goto(`${base}/dashboard/admin/withdrawals`);
  await admin.waitForSelector("[data-testid=withdrawal]");
  await admin.fill("input[aria-label='Reference of the transfer you made']", "BANK-77");
  await admin.getByRole("button", { name: "Mark as paid" }).click();
  await admin.waitForSelector("text=BANK-77");
  await admin.screenshot({ path: `${SHOTS}/inst-9-admin-withdrawals.png` });
  await scan(admin, "admin withdrawals");
  await nasr.reload();
  await nasr.waitForSelector("[data-testid=wallet-withdrawn]");
  if (!/5,?000/.test(await nasr.locator("[data-testid=wallet-withdrawn]").innerText())) fail("the withdrawn amount is not shown");
  ok("withdrawal requested by the centre and paid by the admin with a reference");

  // ---- 9. A second job goes to dispute (set up through the API, decided through the screens)
  const c = await api("nour.fawzy@demo.eg"); const m = await api("manager.auto2@demo.eg");
  const mk = async (price) => {
    const r = await c("POST", "/installation-requests", { subject: "Honda Civic 2019", parts: ["Roof"], latitude: 30.0626, longitude: 31.2197, location_consent: true });
    await m("POST", `/partner/installation-requests/${r.id}/offer`, { price, eta_days: 2 });
    const offer = (await c("GET", `/installation-requests/${r.id}`)).offers[0].id;
    await c("POST", `/installation-requests/${r.id}/accept`, { offer_id: offer, accept_terms: true });
    await c("POST", `/installation-requests/${r.id}/pay`);
    return r.id;
  };
  const dJob = await mk(5000);
  const form = new FormData();
  form.append("before[]", new Blob([png], { type: "image/png" }), "b.png"); form.append("after[]", new Blob([png], { type: "image/png" }), "a.png"); form.append("serial", "SN-9");
  const tokenLogin = await (await fetch(`${base}/api/login`, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify({ email: "manager.auto2@demo.eg", password: PASSWORD }) })).json();
  const pr = await fetch(`${base}/api/partner/installation-jobs/${dJob}/proof`, { method: "POST", headers: { Accept: "application/json", Authorization: `Bearer ${tokenLogin.token}` }, body: form });
  if (!pr.ok) fail(`proof upload failed ${pr.status}`);

  await customer.goto(`${base}/dashboard/installations/${dJob}`);
  await customer.getByRole("button", { name: "There is a problem" }).click();
  await customer.fill("#dd", "The film has bubbles along the roof edge");
  await customer.getByRole("button", { name: "Open dispute" }).click();
  await customer.waitForSelector("text=Dispute under review");
  await customer.fill("input[placeholder='A note']", "Bubbles are visible on the left edge");
  await customer.setInputFiles("input[type=file]", [file("bubbles.png")]);
  await customer.getByRole("button", { name: "Add evidence" }).click();
  await customer.waitForSelector("text=Added.");
  await customer.screenshot({ path: `${SHOTS}/inst-10-dispute-customer.png`, fullPage: true });
  await nasr.goto(`${base}/dashboard/centre-jobs/${dJob}`);
  await nasr.waitForSelector("text=Dispute under review");
  await nasr.fill("input[placeholder='A note']", "The customer used a pressure washer too early");
  await nasr.getByRole("button", { name: "Add evidence" }).click();
  await nasr.waitForSelector("text=Added.");

  await admin.goto(`${base}/dashboard/admin/disputes`);
  const card = admin.locator("[data-testid=dispute]", { hasText: "Honda Civic" }).first();
  await card.waitFor();
  const dtext = await card.innerText();
  if (!/bubbles/i.test(dtext) || !/pressure washer/.test(dtext)) fail("the admin does not see the evidence of both sides");
  await admin.screenshot({ path: `${SHOTS}/inst-11-admin-dispute.png`, fullPage: true });
  await scan(admin, "admin disputes");
  await card.locator("select").selectOption("refund_partial");
  await card.locator("input[aria-label='Refund amount']").fill("1500");
  await card.locator("input[aria-label^='Reason for the decision']").fill("Part of the film must be redone");
  await card.getByRole("button", { name: "Decide" }).click();
  await admin.waitForSelector("text=Mark refund sent");
  await admin.locator("input[placeholder='Reference of the refund you sent']").first().fill("RF-1");
  await admin.getByRole("button", { name: "Mark refund sent" }).first().click();
  await admin.waitForSelector("text=Refund sent");
  await customer.goto(`${base}/dashboard/installations/${dJob}`);
  await customer.waitForSelector("text=Decision");
  await customer.waitForSelector("text=RF-1");
  ok("a dispute with evidence from both sides was decided (partial refund) and the refund recorded");

  // ---- 10. A report of dealing outside the platform becomes a strike
  const vJob = await mk(3000);
  await customer.goto(`${base}/dashboard/installations/${vJob}`);
  customer.removeAllListeners("dialog"); customer.once("dialog", (d) => d.accept("Asked me to pay cash"));
  await customer.getByRole("button", { name: /Report: the centre asked me to deal outside/ }).click();
  await customer.waitForSelector("text=Thank you. The platform will review this.");
  await admin.goto(`${base}/dashboard/admin/disputes`);
  await admin.getByRole("button", { name: "Confirm and give a strike" }).first().click();
  await admin.waitForSelector("text=Confirmed");
  ok("an off-platform report was confirmed by an admin");

  // ---- 11. Mobile, Arabic and the notification bell of the centre
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const mp = await mobile.newPage();
  await mp.goto(`${base}/login`); await mp.fill("input[type=email]", "nour.fawzy@demo.eg"); await mp.fill("input[type=password]", PASSWORD); await mp.click("button[type=submit]"); await mp.waitForURL("**/dashboard**");
  for (const [path, name] of [[`/dashboard/installations/${jobId}`, "job"], [`/dashboard/installations/${dJob}`, "dispute"], ["/dashboard/installations", "list"], ["/dashboard/installations/new", "new"]]) {
    await mp.goto(base + path); await mp.waitForLoadState("networkidle"); await noOverflow(mp, `mobile ${name}`);
    await mp.screenshot({ path: `${SHOTS}/inst-m-${name}.png`, fullPage: true });
  }
  const mn = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const mnp = await mn.newPage();
  await mnp.goto(`${base}/login`); await mnp.fill("input[type=email]", "manager.auto2@demo.eg"); await mnp.fill("input[type=password]", PASSWORD); await mnp.click("button[type=submit]"); await mnp.waitForURL("**/dashboard**");
  for (const [path, name] of [["/dashboard/wallet", "wallet"], [`/dashboard/centre-jobs/${jobId}`, "centre-job"], ["/dashboard/centre-requests", "centre-requests"]]) {
    await mnp.goto(base + path); await mnp.waitForLoadState("networkidle"); await noOverflow(mnp, `mobile ${name}`);
    await mnp.screenshot({ path: `${SHOTS}/inst-m-${name}.png`, fullPage: true });
  }
  const arabic = await login("nour.fawzy@demo.eg", { geo: true, locale: "ar" });
  await arabic.goto(`${base}/dashboard/installations/${jobId}`); await arabic.waitForLoadState("networkidle");
  if ((await arabic.locator("html").getAttribute("dir")) !== "rtl") fail("Arabic is not right-to-left");
  const arText = await text(arabic);
  if (/Your rating|Proof of the work|Temporary substitute number/.test(arText)) fail("English leaked into the Arabic job page");
  await arabic.screenshot({ path: `${SHOTS}/inst-ar-job.png`, fullPage: true });
  await scan(arabic, "arabic job");
  await arabic.goto(`${base}/dashboard/installations/new`); await arabic.waitForLoadState("networkidle");
  if (/Share my location|Send request/.test(await text(arabic))) fail("English leaked into the Arabic request form");
  ok("mobile screens do not scroll sideways, and Arabic is right-to-left and translated");

  if (axeFindings.length) fail(`accessibility problems:\n  ${axeFindings.join("\n  ")}`);
  ok("no serious accessibility problems on the installation screens");
  console.log("\nAll installation checks passed");
} catch (e) {
  console.error("FAILED:", e.message);
  process.exitCode = 1;
} finally {
  await browser.close();
}
