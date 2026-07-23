"use client";

import { AppSidebar } from "@/components/layout/app-sidebar";
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
    <TooltipProvider>
      <SidebarProvider>
        <>
          <AppSidebar />

          <SidebarInset>
            <header className="flex h-16 items-center justify-between border-b px-6">
              <div className="flex items-center">
                <SidebarTrigger />
                <h1 className="ml-4 text-lg font-semibold">ArmorHub</h1>
              </div>

              <button
                onClick={() => {
                  localStorage.removeItem("token");
                  window.location.href = "/login";
                }}
                className="rounded-md rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700"
              >
                Logout
              </button>
            </header>

            <main className="p-6">{children}</main>
          </SidebarInset>
        </>
      </SidebarProvider>
    </TooltipProvider>
  );
}