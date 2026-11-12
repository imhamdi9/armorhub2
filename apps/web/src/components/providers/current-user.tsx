"use client";

import { isAxiosError } from "axios";
import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { useRouter } from "next/navigation";

import { Button } from "@/components/ui/button";
import { tr } from "@/lib/i18n";
import { me } from "@/services/auth.service";
import type { User } from "@/types/auth";

const CurrentUserContext = createContext<User | null>(null);

export function useCurrentUser() {
  return useContext(CurrentUserContext);
}

/**
 * Loads /me once. Only a 401 (the session is really gone) sends the person to /login: a dropped connection, a busy server (429) or a
 * hiccup (5xx) shows a "try again" message instead, so a flaky network never throws someone out of their work.
 */
export function CurrentUserProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [failed, setFailed] = useState(false);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let live = true;
    me()
      .then((u) => { if (live) { setUser(u); setFailed(false); } })
      .catch((e) => {
        if (!live) return;
        if (isAxiosError(e) && e.response?.status === 401) router.replace("/login");
        else setFailed(true);
      });

    return () => { live = false; };
  }, [router, attempt]);

  // Try again by itself a few seconds later: most of these clear up on their own.
  useEffect(() => {
    if (!failed) return;
    const id = setTimeout(() => setAttempt((a) => a + 1), 5000);

    return () => clearTimeout(id);
  }, [failed, attempt]);

  const retry = useCallback(() => { setFailed(false); setAttempt((a) => a + 1); }, []);

  if (!user && failed) {
    return (
      <div className="mx-auto max-w-md p-8 text-center" role="alert">
        <p className="font-semibold">{tr("We could not reach the server.")}</p>
        <p className="mt-1 text-sm text-muted-foreground">{tr("Check your connection. We will keep trying, or you can try now.")}</p>
        <Button className="mt-4" onClick={retry}>{tr("Try again")}</Button>
      </div>
    );
  }

  if (!user) {
    return <div className="p-6 text-muted-foreground" role="status">{tr("Loading...")}</div>;
  }

  return (
    <CurrentUserContext.Provider value={user}>
      {children}
    </CurrentUserContext.Provider>
  );
}
