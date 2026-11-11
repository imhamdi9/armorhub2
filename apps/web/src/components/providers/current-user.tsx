"use client";

import { createContext, useContext, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { me } from "@/services/auth.service";
import type { User } from "@/types/auth";

const CurrentUserContext = createContext<User | null>(null);

export function useCurrentUser() {
  return useContext(CurrentUserContext);
}

/** Loads /me once; sends the visitor to /login if the token is invalid. */
export function CurrentUserProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);

  useEffect(() => {
    me()
      .then(setUser)
      .catch(() => router.replace("/login"));
  }, [router]);

  if (!user) {
    return <div className="p-6 text-muted-foreground">Loading...</div>;
  }

  return (
    <CurrentUserContext.Provider value={user}>
      {children}
    </CurrentUserContext.Provider>
  );
}
