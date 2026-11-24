// Imported first by every e2e script. Two things the scripts need from every browser they create:
//
// 1. The cookie-consent banner is fixed to the bottom of every page and covers buttons the scripts click, so each browser context starts with
//    the visitor's choice already made (the value the banner stores on "Reject"). E2E_KEEP_BANNER=1 leaves the banner alone, to test it.
// 2. A page is not interactive the moment it "loads": the app starts (hydrates) a little later, and a click before that submits the form the
//    old-fashioned way instead of through the app (no error message, no sign-in). So every navigation waits, up to 8 seconds, for the network
//    to go quiet. It never fails a script: a page that keeps polling just continues after the wait.
import { chromium } from "playwright";

const answered = () => {
  try { localStorage.setItem("cookie-consent", JSON.stringify({ decision: "rejected", timestamp: new Date().toISOString() })); } catch { /* storage blocked */ }
};

if (!chromium.__e2ePatched) {
  const keepBanner = !!process.env.E2E_KEEP_BANNER;
  const launch = chromium.launch.bind(chromium);
  chromium.launch = async (...args) => {
    const browser = await launch(...args);
    const newContext = browser.newContext.bind(browser);
    browser.newContext = async (...a) => {
      const context = await newContext(...a);
      if (!keepBanner) await context.addInitScript(answered);
      context.on("page", (page) => {
        const goto = page.goto.bind(page);
        page.goto = async (...g) => {
          const response = await goto(...g);
          await page.waitForLoadState("networkidle", { timeout: 8000 }).catch(() => undefined);

          return response;
        };

        // 3. When a wait runs out, say what the page was showing: a bare "Timeout 30000ms exceeded" in a CI log tells nobody anything.
        for (const method of ["waitForSelector", "waitForURL", "click", "fill"]) {
          const original = page[method].bind(page);
          page[method] = async (...w) => {
            try {
              return await original(...w);
            } catch (error) {
              // the main area, not the sidebar menu that starts every signed-in page
              const text = await page.evaluate(() => (document.querySelector("main") ?? document.body).innerText.replace(/\s+/g, " ").slice(0, 700)).catch(() => "(page not readable)");
              console.error(`\n  [${method}(${String(w[0]).slice(0, 80)}) gave up]\n  page: ${page.url()}\n  shows: ${text}`);

              throw error;
            }
          };
        }
      });

      return context;
    };
    // browser.newPage() makes a context of its own: give it the same treatment.
    browser.newPage = async (options) => (await browser.newContext(options)).newPage();

    return browser;
  };
  chromium.__e2ePatched = true;
}
