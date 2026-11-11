"use client";

import { AppSidebar } from "@/components/layout/app-sidebar";
import { NotificationBell } from "@/components/layout/notification-bell";
import { CurrentUserProvider } from "@/components/providers/current-user";
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from "@/components/ui/sidebar";
import { TooltipProvider } from "@/components/ui/tooltip";
import { LanguageSwitcher, tr } from "@/lib/i18n";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
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
                onClick={() => {
                  localStorage.removeItem("token");
                  window.location.href = "/login";
                }}
                className="whitespace-nowrap rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700"
              >
                {tr("Logout")}
              </button>
              </div>
            </header>

            <main className="p-6">{children}</main>
          </SidebarInset>
        </SidebarProvider>
      </TooltipProvider>
    </CurrentUserProvider>
  );
}
