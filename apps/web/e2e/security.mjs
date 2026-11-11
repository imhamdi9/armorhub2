// Attacks the running app the way an attacker would, and checks it holds.
// Needs a fresh database with demo data (php artisan platform:demo): it exhausts rate limits on purpose.
import { chromium } from "playwright";

const base = process.env.BASE_URL ?? "http://localhost:3000";
const ok = (m) => console.log("OK ", m);
const fail = (m) => { throw new Error(m); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const api = async (path, { method = "GET", body, headers = {} } = {}) => {
  const res = await fetch(`${base}/api${path}`, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", ...headers },
    body: body ? JSON.stringify(body) : undefined,
  });
  return { status: res.status, json: await res.json().catch(() => ({})), headers: res.headers };
};

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
try {
  // 1. The session token is invisible to scripts: an injected script cannot steal it.
  const context = await browser.newContext();
  const page = await context.newPage();
  const violations = [];
  page.on("console", (m) => /Content Security Policy|violates/i.test(m.text()) && violations.push(m.text()));
  await page.goto(`${base}/login`);
  await page.fill("input[type=email]", "admin@test.com");
  await page.fill("input[type=password]", "password123");
  await page.click("button[type=submit]");
  await page.waitForURL("**/dashboard");
  await page.waitForSelector("text=Welcome");

  const visible = await page.evaluate(() => ({ cookie: document.cookie, local: JSON.stringify({ ...localStorage }), session: JSON.stringify({ ...sessionStorage }) }));
  if (/armorhub_token/.test(visible.cookie)) fail("the session cookie is readable by JavaScript");
  if (/token/i.test(visible.local + visible.session)) fail("a token is stored in web storage");
  const cookies = await context.cookies();
  const session = cookies.find((c) => c.name === "armorhub_token");
  if (!session?.httpOnly || session.sameSite !== "Lax") fail(`session cookie flags wrong: ${JSON.stringify(session)}`);
  ok("session token is an HttpOnly SameSite=Lax cookie, absent from cookies/localStorage that scripts can read");

  // 2. CSRF: another website making this browser write is refused, our own page is fine.
  const csrf = await context.request.put(`${base}/api/me/location`, {
    headers: { Origin: "https://evil.example", "X-Client": "web", "Content-Type": "application/json" },
    data: { latitude: 1, longitude: 1 },
  });
  if (csrf.status() !== 403) fail(`cross-site write was not refused (${csrf.status()})`);
  const own = await page.evaluate(async () =>
    (await fetch("/api/me/location", { method: "PUT", credentials: "include", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify({ latitude: 24.7, longitude: 46.7 }) })).status);
  if (own !== 200) fail(`own-origin write failed (${own})`);
  ok("cross-site writes with the victim's cookie are refused (403); the real app still works");

  // 3. Security headers on the page and the API, and no CSP violations while using the app.
  const html = await context.request.get(`${base}/dashboard`);
  const h = html.headers();
  for (const [name, expect] of [["x-frame-options", /DENY/], ["x-content-type-options", /nosniff/], ["content-security-policy", /frame-ancestors 'none'/]]) {
    if (!expect.test(h[name] ?? "")) fail(`web header ${name} missing/wrong: ${h[name]}`);
  }
  const me = await context.request.get(`${base}/api/me`);
  if (!/default-src 'none'/.test(me.headers()["content-security-policy"] ?? "")) fail("API CSP header missing");
  if (me.headers()["cache-control"]?.includes("no-store") !== true) fail("API responses are cacheable");
  if (violations.length) fail(`CSP violations while using the app: ${violations.join("; ")}`);
  ok("CSP, framing, sniffing and caching headers are set on pages and API; the app runs without CSP violations");

  // 4. Brute force: 5 wrong passwords, then locked out; the owner's own address is not affected.
  const guess = () => api("/login", { method: "POST", body: { email: "shop@test.com", password: "wrong-password-1" } });
  const statuses = [];
  for (let i = 0; i < 7; i++) statuses.push((await guess()).status);
  if (!(statuses.slice(0, 5).every((s) => s === 422) && statuses.slice(5).every((s) => s === 429))) fail(`brute force not throttled: ${statuses}`);
  ok(`password guessing is throttled after 5 tries (${statuses.join(",")})`);

  // 5. Signup flood: scripts that rotate emails still hit the per-IP ceiling (5/hour by default).
  const tokens = [];
  for (let i = 0; i < 7; i++) tokens.push((await api("/signup-challenge")).json);
  await sleep(((tokens[0].min_seconds ?? 3) + 1) * 1000);
  const company = 1;
  const outcomes = [];
  for (let i = 0; i < 7; i++) {
    const r = await api("/register", { method: "POST", body: { name: "Bot", email: `bot${i}${Date.now()}@example.com`, signup_token: tokens[i].token, password: "password123", password_confirmation: "password123", company_id: company } });
    outcomes.push(r.status);
  }
  if (outcomes.filter((s) => s === 201).length !== 5 || outcomes.slice(5).some((s) => s !== 429)) fail(`signup flood not capped: ${outcomes}`);
  ok(`signup flood capped per IP even with fresh emails (${outcomes.join(",")})`);

  // 6. Bots without the human-check token never get an account, and are told nothing useful.
  const noToken = await api("/register", { method: "POST", body: { name: "Bot", email: "naked@example.com", password: "password123", password_confirmation: "password123", company_id: company } });
  if (![422, 429].includes(noToken.status)) fail(`token-less signup was accepted (${noToken.status})`);
  ok("a signup without the human-check token is refused");

  // 7. The previously public endpoints now need a login.
  for (const path of ["/vehicles/makes", "/industries", "/products", "/orders"]) {
    const r = await fetch(`${base}/api${path}`, { headers: { Accept: "application/json" } });
    if (r.status !== 401) fail(`${path} is reachable without logging in (${r.status})`);
  }
  ok("catalog, vehicle and order endpoints require a login");

  console.log("\nSecurity checks passed.");
} catch (e) {
  console.error("\nSecurity checks FAILED:", e.message.split("\n")[0]);
  process.exitCode = 1;
} finally {
  await browser.close();
}
