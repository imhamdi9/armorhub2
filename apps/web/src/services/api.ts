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

// An expired or revoked session sends the visitor back to the sign-in page.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (
      typeof window !== "undefined" &&
      error?.response?.status === 401 &&
      !["/login", "/register", "/forgot-password", "/reset-password"].some((p) => window.location.pathname.startsWith(p))
    ) {
      window.location.replace(new URL("/login", window.location.origin).toString());
    }

    return Promise.reject(error);
  },
);
