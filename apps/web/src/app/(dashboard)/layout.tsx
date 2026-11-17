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
import { I18nProvider, LanguageSwitcher, tr } from "@/lib/i18n";

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

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();

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
                <h1 className="sr-only">{tr("ArmorHub")}</h1>
              </div>

              <div className="flex items-center gap-2">
                <LanguageSwitcher />
                <NotificationBell />
                <button
                  onClick={async () => {
                    await logout().catch(() => undefined);
                    router.replace("/login");
                  }}
                  className="whitespace-nowrap rounded-full border border-border bg-white px-4 py-2 text-sm font-semibold text-obsidian transition hover:bg-fog"
                >
                  {tr("Logout")}
                </button>
              </div>
            </header>

            <VerifyEmailBanner />

            <div className="min-w-0 px-4 pb-10 pt-2 sm:px-6 [&_.rounded-lg.border]:rounded-2xl [&_.rounded-lg.border]:bg-white [&_.rounded-lg.border]:shadow-card [&_.rounded-xl.border]:rounded-2xl [&_.rounded-xl.border]:bg-white [&_.rounded-xl.border]:shadow-card [&_.border-dashed]:shadow-none">{children}</div>
          </SidebarInset>
        </SidebarProvider>
      </TooltipProvider>
    </CurrentUserProvider>
    </I18nProvider>
  );
}
