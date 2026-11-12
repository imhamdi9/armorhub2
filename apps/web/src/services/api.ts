import axios from "axios";

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

// Market settings reuse the admin screens. A market manager's pages (/dashboard/market/...) talk to /market/..., which can only ever reach their
// own market. A platform admin who opened one market (?market=<id>) edits that market's values instead of the platform-wide ones.
api.interceptors.request.use((config) => {
  if (typeof window === "undefined" || !config.url?.startsWith("/admin/")) return config;
  if (window.location.pathname.startsWith("/dashboard/market/")) {
    config.url = config.url.replace(/^\/admin\//, "/market/");
  } else {
    const market = new URLSearchParams(window.location.search).get("market");
    if (market && /^\d+$/.test(market) && /^\/admin\/(settings|payments|shipping)/.test(config.url)) config.params = { ...(config.params ?? {}), market };
  }

  return config;
});

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
