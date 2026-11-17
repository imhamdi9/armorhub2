import { API_URL } from "@/config";
import { storeGet, storeRemove, storeSet } from "@/storage";

const TOKEN_KEY = "armorhub.token";

let token: string | null = null;
let onUnauthorized: (() => void) | null = null;

export async function loadToken() {
  token = await storeGet(TOKEN_KEY);
  return token;
}

export async function saveToken(value: string | null) {
  token = value;
  if (value) await storeSet(TOKEN_KEY, value);
  else await storeRemove(TOKEN_KEY);
}

export const hasToken = () => token !== null;
export const setUnauthorizedHandler = (fn: (() => void) | null) => { onUnauthorized = fn; };

export class ApiError extends Error {
  constructor(message: string, public status: number, public errors: Record<string, string[]> = {}) {
    super(message);
  }
}

type Options = { method?: "GET" | "POST" | "PUT" | "DELETE"; body?: unknown; params?: Record<string, string | number | undefined> };

/** One call to the platform. Always JSON; the signed-in person's token is added; a refused token signs the person out. */
export async function api<T = unknown>(path: string, { method = "GET", body, params }: Options = {}): Promise<T> {
  const query = params ? "?" + Object.entries(params).filter(([, v]) => v !== undefined && v !== "").map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`).join("&") : "";
  const headers: Record<string, string> = { Accept: "application/json", "X-Client": "mobile" };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (token) headers.Authorization = `Bearer ${token}`;

  let res: Response;
  try {
    res = await fetch(`${API_URL}${path}${query}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
  } catch {
    throw new ApiError("offline", 0);
  }

  const text = await res.text();
  let data: unknown = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = null; }

  if (!res.ok) {
    const d = (data ?? {}) as { message?: string; errors?: Record<string, string[]> };
    if (res.status === 401 && token) onUnauthorized?.();
    throw new ApiError(d.message ?? `error ${res.status}`, res.status, d.errors ?? {});
  }

  return data as T;
}
