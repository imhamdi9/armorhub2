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
            <header className="flex h-16 items-center justify-between border-b px-6">
              <div className="flex items-center">
                <SidebarTrigger />
                <h1 className="ml-4 text-lg font-semibold">ArmorHub</h1>
              </div>

              <div className="flex items-center">
              <NotificationBell />
              <button
                onClick={() => {
                  localStorage.removeItem("token");
                  window.location.href = "/login";
                }}
                className="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700"
              >
                Logout
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
