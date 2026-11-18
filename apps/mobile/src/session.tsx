import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";

import { api, loadToken, saveToken, setUnauthorizedHandler } from "@/api";
import { registerForPush, unregisterPush } from "@/push";

export type User = { id: number; name: string; email: string; phone?: string | null; company_id?: number | null; store_slug?: string | null };
export type SocialProvider = "google" | "apple";

type Session = {
  user: User | null;
  ready: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signInWith: (provider: SocialProvider, credential: string, extra?: { store?: string; name?: string }) => Promise<void>;
  signOut: () => Promise<void>;
  deleteAccount: (proof: { password?: string; confirm?: string }) => Promise<void>;
  refresh: () => Promise<void>;
};

const Ctx = createContext<Session | null>(null);

type Login = { user: User; token?: string };

/** Who is signed in. The same account works on the website: the app only holds a token for it. */
export function SessionProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [ready, setReady] = useState(false);

  const clear = useCallback(async () => {
    await saveToken(null);
    setUser(null);
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(() => { void clear(); });
    (async () => {
      if (await loadToken()) {
        try {
          setUser(await api<User>("/me"));
          void registerForPush();
        } catch {
          // a refused token was already cleared by the handler; being offline keeps the token for next time
        }
      }
      setReady(true);
    })();
    return () => setUnauthorizedHandler(null);
  }, [clear]);

  const accept = useCallback(async (res: Login) => {
    if (!res.token) throw new Error("no token");
    await saveToken(res.token);
    setUser(res.user);
    void registerForPush();
  }, []);

  const value = useMemo<Session>(() => ({
    user,
    ready,
    refresh: async () => { setUser(await api<User>("/me")); },
    signIn: async (email, password) => accept(await api<Login>("/login", { method: "POST", body: { email, password } })),
    signInWith: async (provider, credential, extra) => accept(await api<Login>("/auth/social", { method: "POST", body: { provider, credential, ...extra } })),
    signOut: async () => {
      await unregisterPush();
      try { await api("/logout", { method: "POST" }); } catch { /* the token is dropped here either way */ }
      await clear();
    },
    deleteAccount: async (proof) => {
      await unregisterPush();
      await api("/me", { method: "DELETE", body: proof });
      await clear();
    },
  }), [user, ready, accept, clear]);

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useSession(): Session {
  const s = useContext(Ctx);
  if (!s) throw new Error("useSession outside SessionProvider");
  return s;
}
