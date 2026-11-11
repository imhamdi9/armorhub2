"use client";

import Link from "next/link";
import { useEffect, useState, type CSSProperties } from "react";

import { LanguageSwitcher, tr } from "@/lib/i18n";
import { textOn } from "@/lib/public-api";
import { me } from "@/services/auth.service";
import type { User } from "@/types/auth";

export interface ShellStore {
  name: string;
  slug: string;
  logo_url?: string | null;
  primary_color?: string;
}

const DEFAULT_COLOR = "#111827";

/** Whether the visitor is signed in, without ever redirecting them. */
function useSession() {
  const [user, setUser] = useState<User | null | undefined>(undefined);

  useEffect(() => {
    me().then(setUser).catch(() => setUser(null));
  }, []);

  return user;
}

/** Sign in / sign up, or a Dashboard link for people who are already signed in. */
export function AuthLinks({ store }: { store?: ShellStore }) {
  const user = useSession();
  const signup = store ? `/register?store=${encodeURIComponent(store.slug)}` : "/register";

  if (user) {
    return (
      <Link href="/dashboard" className="btn-brand rounded-lg px-4 py-2 text-sm font-medium">
        {tr("Dashboard")}
      </Link>
    );
  }

  return (
    <div className="flex items-center gap-2">
      <Link href="/login" className="rounded-lg px-3 py-2 text-sm hover:bg-black/5">{tr("Sign in")}</Link>
      <Link href={signup} className="btn-brand rounded-lg px-4 py-2 text-sm font-medium">{tr("Sign up")}</Link>
    </div>
  );
}

/** Header, branded colours and footer shared by every public page. */
export function PublicShell({ store, children }: { store?: ShellStore; children: React.ReactNode }) {
  const color = store?.primary_color ?? DEFAULT_COLOR;
  const home = store ? `/s/${store.slug}` : "/";
  // Both values are validated hex colours (see the editor API), never free text.
  const vars = { "--brand": color, "--on-brand": textOn(color) } as CSSProperties;

  return (
    <div style={vars} className="flex min-h-screen flex-col bg-white text-gray-900">
      <header className="border-b">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
          <Link href={home} className="flex items-center gap-2 text-lg font-bold">
            {store?.logo_url && (
              // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
              <img src={store.logo_url} alt="" className="h-8 w-auto max-w-[8rem] object-contain" />
            )}
            <span>{store?.name ?? tr("ArmorHub")}</span>
          </Link>

          <nav className="flex items-center gap-1 text-sm">
            {store && (
              <Link href={`/s/${store.slug}/products`} className="hidden rounded-lg px-3 py-2 hover:bg-black/5 sm:block">
                {tr("Products")}
              </Link>
            )}
            <LanguageSwitcher className="mx-1" />
            <AuthLinks store={store} />
          </nav>
        </div>
      </header>

      <main className="flex-1">{children}</main>

      <footer className="border-t bg-gray-50">
        <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-6 text-sm text-gray-600">
          <span>© {new Date().getFullYear()} {store?.name ?? tr("ArmorHub")}</span>
          <span>
            {store ? (
              <>
                {tr("Powered by")} <Link href="/" className="underline">{tr("ArmorHub")}</Link>
              </>
            ) : (
              <Link href="/login" className="underline">{tr("For businesses: sign in")}</Link>
            )}
          </span>
        </div>
      </footer>
    </div>
  );
}
