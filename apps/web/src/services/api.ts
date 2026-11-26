import axios from "axios";

import { campaignHeader } from "@/lib/marketing";
import { forgetShared } from "@/services/shared";

/**
 * Browser sessions use an HttpOnly cookie set by the API: the token is never stored where
 * JavaScript (or an injected script) can read it. `X-Client: web` asks the API for that mode.
 */
export const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
  withCredentials: true,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Client": "web",
  },
});

// The API answers in the language the person reads the site in.
api.interceptors.request.use((config) => {
  if (typeof window !== "undefined") {
    config.headers["Accept-Language"] = window.localStorage.getItem("locale") === "ar" ? "ar" : "en";
    // Only after the visitor allowed marketing cookies: which campaign they came from, so the server can credit it (and report the purchase).
    const campaign = campaignHeader();
    if (campaign) config.headers["X-Campaign"] = campaign;
  }

  return config;
});

// Market settings reuse the admin screens. A market manager's pages (/dashboard/market/...) talk to /market/..., which can only ever reach their
// own market. A platform admin who opened one market (?market=<id>) edits that market's values instead of the platform-wide ones.
api.interceptors.request.use((config) => {
  if (typeof window === "undefined" || !config.url?.startsWith("/admin/")) return config;
  if (window.location.pathname.startsWith("/dashboard/market/")) {
    config.url = config.url.replace(/^\/admin\//, "/market/");
  } else {
    const market = new URLSearchParams(window.location.search).get("market");
    if (market && /^\d+$/.test(market) && /^\/admin\/(settings|payments|shipping|marketing)/.test(config.url)) config.params = { ...(config.params ?? {}), market };
  }

  return config;
});

// Anything that changes data makes the shared answers old, both when it is sent and when it comes back.
const changes = (method?: string) => !["get", "head", "options"].includes((method ?? "get").toLowerCase());
api.interceptors.request.use((config) => {
  if (changes(config.method)) forgetShared();
  return config;
});
api.interceptors.response.use(
  (response) => { if (changes(response.config.method)) forgetShared(); return response; },
  (error) => { if (changes(error?.config?.method)) forgetShared(); return Promise.reject(error); },
);

// An expired or revoked session sends the visitor back to the sign-in page.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      typeof window !== "undefined" &&
      error?.response?.status === 401 &&
      // Only the signed-in app bounces to the login page; public pages just see "not signed in".
      window.location.pathname.startsWith("/dashboard")
    ) {
      window.location.replace(new URL("/login", window.location.origin).toString());
    }

    return Promise.reject(error);
  },
);
