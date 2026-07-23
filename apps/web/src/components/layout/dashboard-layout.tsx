import { AppSidebar } from "@/components/layout/app-sidebar";
import { SidebarInset, SidebarTrigger } from "@/components/ui/sidebar";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <AppSidebar />

      <SidebarInset>
        <header className="flex h-16 items-center border-b px-6">
          <SidebarTrigger />
          <h1 className="ml-4 text-lg font-semibold">ArmorHub</h1>
        </header>

        <main className="p-6">{children}</main>
      </SidebarInset>
    </>
  );
}