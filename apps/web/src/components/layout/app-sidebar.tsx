"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Banknote,
  BookOpen,
  Building,
  Building2,
  Hammer,
  Inbox,
  LayoutDashboard,
  Package,
  Package2,
  ShieldCheck,
  UserCog,
  ShoppingCart,
  Users,
  Wrench,
  type LucideIcon,
} from "lucide-react";

import { useCurrentUser } from "@/components/providers/current-user";
import { buildNavigation, type NavItem } from "@/lib/navigation";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";

const icons: Record<NavItem["icon"], LucideIcon> = {
  dashboard: LayoutDashboard,
  orders: ShoppingCart,
  catalog: BookOpen,
  products: Package,
  partners: Wrench,
  tenants: Building2,
  requests: Inbox,
  commissions: Banknote,
  items: Package2,
  companies: Building,
  users: UserCog,
  jobs: Hammer,
  warranties: ShieldCheck,
};

export function AppSidebar() {
  const user = useCurrentUser();
  const pathname = usePathname();
  const items = buildNavigation(user);

  return (
    <Sidebar>
      <SidebarHeader className="p-6">
        <h1 className="text-xl font-bold">ArmorHub</h1>
        <p className="text-sm text-muted-foreground">
          {user?.industry?.name ?? "Service Marketplace Platform"}
        </p>
      </SidebarHeader>

      <SidebarContent>
        <SidebarMenu>
          {items.map((item) => {
            const Icon = icons[item.icon] ?? Users;
            const active =
              item.href === "/dashboard"
                ? pathname === item.href
                : pathname.startsWith(item.href);

            return (
              <SidebarMenuItem key={item.href}>
                <SidebarMenuButton
                  isActive={active}
                  render={<Link href={item.href} />}
                >
                  <Icon className="h-4 w-4" />
                  <span>{item.title}</span>
                </SidebarMenuButton>
              </SidebarMenuItem>
            );
          })}
        </SidebarMenu>
      </SidebarContent>

      <SidebarFooter className="p-4 text-xs text-muted-foreground">
        © {new Date().getFullYear()} ArmorHub
      </SidebarFooter>
    </Sidebar>
  );
}
