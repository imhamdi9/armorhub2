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
import { LanguageSwitcher, tr } from "@/lib/i18n";

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
    <CurrentUserProvider>
      <TooltipProvider>
        <SidebarProvider>
          <AppSidebar />

          <SidebarInset>
            <header className="flex h-16 items-center justify-between border-b px-3 sm:px-6">
              <div className="flex items-center">
                <SidebarTrigger />
                <h1 className="ms-4 hidden text-lg font-semibold sm:block">{tr("ArmorHub")}</h1>
              </div>

              <div className="flex items-center gap-2">
              <LanguageSwitcher />
              <NotificationBell />
              <button
                onClick={async () => {
                  await logout().catch(() => undefined);
                  router.replace("/login");
                }}
                className="whitespace-nowrap rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700"
              >
                {tr("Logout")}
              </button>
              </div>
            </header>

            <VerifyEmailBanner />

            <main className="p-6">{children}</main>
          </SidebarInset>
        </SidebarProvider>
      </TooltipProvider>
    </CurrentUserProvider>
  );
}
