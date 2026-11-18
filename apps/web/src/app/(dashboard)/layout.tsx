"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";

import { AppSidebar } from "@/components/layout/app-sidebar";
import { NotificationBell } from "@/components/layout/notification-bell";
import { CurrentUserProvider, useCurrentUser } from "@/components/providers/current-user";
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { TooltipProvider } from "@/components/ui/tooltip";
import { logout, resendVerification } from "@/services/auth.service";
import { claimGuestAccount } from "@/lib/guest";
import { errorMessage } from "@/lib/errors";
import { CookieConsentBanner } from "@/components/layout/cookie-consent-banner";
import { I18nProvider, LanguageSwitcher, tr, useLocale } from "@/lib/i18n";

/** Someone who shopped without signing up: a password turns this into a full account, so their orders are there on any device. */
function GuestBanner() {
  const user = useCurrentUser();
  const [open, setOpen] = useState(false);
  const [f, setF] = useState({ email: "", password: "", password_confirmation: "" });
  const [msg, setMsg] = useState<string | null>(null);
  if (!user?.is_guest) return null;

  return (
    <div className="border-b border-sky-300 bg-sky-50 px-6 py-2 text-sm" data-testid="guest-banner">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span>{tr("You are shopping without an account. Create a password to keep your orders on any device.")}</span>
        <button className="rounded-md border border-sky-400 px-3 py-1 hover:bg-sky-100" onClick={() => setOpen((v) => !v)}>{tr("Keep my orders")}</button>
      </div>
      {open && (
        <form className="mt-2 grid gap-2 sm:grid-cols-4" onSubmit={async (e) => {
          e.preventDefault(); setMsg(null);
          try { await claimGuestAccount(f.email, f.password, f.password_confirmation); window.location.reload(); } catch (x) { setMsg(errorMessage(x)); }
        }}>
          <input type="email" required dir="ltr" placeholder={tr("E-mail")} className="h-9 rounded-md border px-2" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />
          <input type="password" required minLength={10} placeholder={tr("Password")} className="h-9 rounded-md border px-2" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} />
          <input type="password" required minLength={10} placeholder={tr("Repeat the password")} className="h-9 rounded-md border px-2" value={f.password_confirmation} onChange={(e) => setF({ ...f, password_confirmation: e.target.value })} />
          <button type="submit" className="h-9 rounded-md bg-sky-700 px-3 text-white">{tr("Create my account")}</button>
          {msg && <p role="alert" className="text-red-600 sm:col-span-4">{msg}</p>}
        </form>
      )}
    </div>
  );
}

function VerifyEmailBanner() {
  const user = useCurrentUser();
  const [sent, setSent] = useState(false);

  if (!user?.email_verification_required) return null;

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-300 bg-amber-50 px-6 py-2 text-sm">
      <span>{tr("Please verify your email address to place orders. We sent you a link.")}</span>
      <button
        className="rounded-md border border-amber-400 px-3 py-1 hover:bg-amber-100 disabled:opacity-50"
        disabled={sent}
        onClick={() => resendVerification().then(() => setSent(true)).catch(() => undefined)}
      >
        {sent ? tr("Email sent") : tr("Resend email")}
      </button>
    </div>
  );
}

/* These two sit in the layout itself, above the screens: as components of their own they redraw when the language changes. */
function PageTitle() {
  useLocale();

  return <h1 className="sr-only">{tr("ArmorHub")}</h1>;
}

function LogoutButton() {
  const router = useRouter();
  useLocale();

  return (
    <button
      onClick={async () => {
        await logout().catch(() => undefined);
        router.replace("/login");
      }}
      className="whitespace-nowrap rounded-full border border-border bg-white px-4 py-2 text-sm font-semibold text-obsidian transition hover:bg-fog"
    >
      {tr("Logout")}
    </button>
  );
}

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <I18nProvider>
    <CurrentUserProvider>
      <TooltipProvider>
        <SidebarProvider className="bg-[radial-gradient(700px_500px_at_5%_0%,#bcd6ff,transparent_60%),radial-gradient(600px_500px_at_98%_12%,#d4f1ff,transparent_60%),#eef3ff]">
          <AppSidebar />

          <SidebarInset className="min-w-0 overflow-x-clip !bg-transparent">
            <header className="sticky top-0 z-30 flex h-[72px] items-center justify-between px-3 sm:px-8">
              <div className="flex items-center">
                <SidebarTrigger />
                <PageTitle />
              </div>

              <div className="flex items-center gap-2">
                <LanguageSwitcher />
                <NotificationBell />
                <LogoutButton />
              </div>
            </header>

            <GuestBanner />
            <VerifyEmailBanner />

            <div className="min-w-0 px-4 pb-10 pt-2 sm:px-6 [&_.rounded-lg.border]:rounded-2xl [&_.rounded-lg.border]:bg-white [&_.rounded-lg.border]:shadow-card [&_.rounded-xl.border]:rounded-2xl [&_.rounded-xl.border]:bg-white [&_.rounded-xl.border]:shadow-card [&_.border-dashed]:shadow-none">{children}</div>
          </SidebarInset>
        </SidebarProvider>
      </TooltipProvider>
    </CurrentUserProvider>
      <CookieConsentBanner />
    </I18nProvider>
  );
}
