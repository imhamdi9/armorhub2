import { api } from "@/services/api";
import { me } from "@/services/auth.service";

let session: Promise<void> | null = null;

/**
 * Makes sure this browser has a shopper session for the store: someone already signed in keeps theirs; anyone else gets a quiet guest session, so a
 * cart works without signing up. A guest who started in another market's store is moved to this one (the server says why when it cannot).
 * (One request at a time, even if several buttons are pressed together.)
 */
export function ensureShopperSession(store: string): Promise<void> {
  session ??= me().then(
    async (user) => { if (user?.is_guest) await api.put("/guest-session/store", { store }); },
    async () => { await api.post("/guest-session", { store }); },
  ).finally(() => { session = null; });
  return session;
}

export const setGuestContact = async (email: string, name?: string) => (await api.put("/guest-session/contact", { email, name })).data;
export const claimGuestAccount = async (email: string, password: string, password_confirmation: string) => (await api.post("/guest-session/claim", { email, password, password_confirmation })).data;
export const openGuestSession = async (token: string) => (await api.post("/guest-session/open", { token })).data;
