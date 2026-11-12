// The Egyptian demo marketplace (php artisan platform:demo-egypt): illustrated hero and product images, EGP prices, and the
// Shopify-style product page: gallery, was-price and saving, highlights, accordions, related products, sticky buy bar, mobile and Arabic.
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const SHOTS = process.env.SHOTS ?? "/tmp";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });

const json = async (path) => (await fetch(`${base}/api${path}`, { headers: { Accept: "application/json" } })).json();
/** Image really loaded (not just present). */
const loaded = (loc) => loc.evaluate((img) => img.complete && img.naturalWidth > 0);

try {
  const stores = (await json("/public/storefronts")).stores;
  if (stores.length !== 4) fail(`expected 4 Egyptian stores, got ${stores.length}`);
  const list = await json("/public/storefronts/beit-masr/products?per_page=50");
  const lantern = list.data.find((p) => /Fanous/.test(p.name)) ?? fail("lantern not in the catalog");

  // ---- Home page: the Egyptian hero and the four markets
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(base);
  await page.waitForSelector("text=Pharaoh Auto Care");
  for (const name of ["Nefertiti Beauty", "Beit Masr", "Cairo Devices"]) await page.waitForSelector(`text=${name}`);
  const hero = page.locator("img[src*='/public/media/']").first();
  await hero.waitFor({ state: "attached" });
  if (!(await loaded(hero))) fail("the published hero image did not load");
  await page.screenshot({ path: `${SHOTS}/egypt-home.png` });
  ok("the home page shows four Egyptian markets and the illustrated hero image");

  // ---- Product page
  await page.goto(`${base}/s/beit-masr/products/${lantern.id}`);
  await page.waitForSelector("h1:has-text('Brass Lantern')");
  const main = page.locator("img[alt*='Brass Lantern']").first();
  if (!(await loaded(main))) fail("the main product image did not load");
  const thumbs = page.locator("ul[aria-label='Product pictures'] button");
  if ((await thumbs.count()) !== 3) fail(`expected 3 thumbnails, got ${await thumbs.count()}`);
  const before = await main.getAttribute("src");
  await thumbs.nth(1).click();
  await page.waitForFunction((b) => document.querySelector("img[alt*='Brass Lantern']")?.getAttribute("src") !== b, before);
  await page.click("[aria-label='Next picture']");
  await page.waitForSelector("text=3 / 3");
  const body = await page.textContent("main");
  if (!/EGP|ج\.م/.test(body)) fail("price is not shown in Egyptian pounds");
  if (!(await page.locator("span.line-through").count())) fail("the was-price is not struck through");
  await page.waitForSelector("text=Save 24%");
  await page.waitForSelector("text=Hand-cut solid brass");
  ok("product page: gallery with thumbnails and arrows, EGP price, struck-through was-price and 24% saving, highlights");

  for (const title of ["Description", "Shipping and delivery", "Warranty and cancellation"]) await page.waitForSelector(`summary:has-text('${title}')`);
  await page.click("summary:has-text('Shipping and delivery')");
  await page.waitForSelector("text=follow every step");
  const related = page.locator("section[aria-label='You may also like'] a");
  if ((await related.count()) < 3) fail("related products are missing");
  if (!(await loaded(related.first().locator("img")))) fail("a related product image did not load");
  ok("accordions open, related products show with pictures");

  // The buy bar follows the shopper once the main button has scrolled away.
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForSelector("[aria-label='Quick order']");
  await page.screenshot({ path: `${SHOTS}/egypt-product.png` });
  ok("a sticky buy bar appears after scrolling past the main button");

  // A service with options: picking one changes the price.
  const autos = await json("/public/storefronts/pharaoh-auto-care/products?per_page=50");
  const ppf = autos.data.find((p) => /PPF/.test(p.name)) ?? fail("PPF missing");
  await page.goto(`${base}/s/pharaoh-auto-care/products/${ppf.id}`);
  await page.waitForSelector("fieldset button:has-text('Hood')");
  await page.click("fieldset button:has-text('Bumper')");
  await page.waitForSelector("text=/7,?800/");
  await page.waitForSelector("a:has-text('Get a quote')");
  ok("a service lets the customer pick an option and see its price");

  // ---- Mobile and Arabic
  const phone = await browser.newPage({ viewport: { width: 390, height: 800 } });
  await phone.goto(`${base}/s/beit-masr/products/${lantern.id}`);
  await phone.waitForSelector("h1");
  const overflow = await phone.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  if (overflow > 1) fail(`the product page scrolls sideways on a phone (${overflow}px)`);
  await phone.screenshot({ path: `${SHOTS}/egypt-product-mobile.png` });
  await phone.click("text=العربية");
  await phone.waitForFunction(() => document.documentElement.dir === "rtl");
  await phone.waitForSelector("text=اطلب الآن");
  const rtlOverflow = await phone.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  if (rtlOverflow > 1) fail(`the Arabic product page scrolls sideways (${rtlOverflow}px)`);
  await phone.screenshot({ path: `${SHOTS}/egypt-product-ar.png` });
  ok("the product page fits a phone and works right-to-left in Arabic");

  // ---- People can sign in; a seller sees their own goods
  const login = async (email) => {
    const p = await browser.newPage();
    await p.goto(`${base}/login`);
    await p.fill("input[type=email]", email);
    await p.fill("input[type=password]", "password123");
    await p.click("button[type=submit]");
    await p.waitForURL("**/dashboard");
    return p;
  };
  const seller = await login("seller.khan@demo.eg");
  await seller.goto(`${base}/dashboard/products`);
  await seller.waitForSelector("text=Brass Lantern");
  if (await seller.locator("text=Egyptian Cotton Bath Towel Set").count()) fail("a seller saw another seller's product");
  await (await login("owner.home@demo.eg")).goto(`${base}/dashboard/storefront`);
  await (await login("nour.fawzy@demo.eg")).goto(`${base}/dashboard/orders`);
  ok("demo sellers, owners and customers can sign in; a seller sees only their own products");

  console.log("\nEgyptian demo passed.");
} catch (e) {
  console.error("\nEgyptian demo FAILED:", e.message.split("\n")[0], (e.stack.match(/egypt.mjs:\d+/) || [])[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
