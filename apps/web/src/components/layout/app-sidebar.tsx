"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Banknote,
  Boxes,
  BookOpen,
  Building,
  Building2,
  Library,
  Store,
  Hammer,
  Inbox,
  LayoutDashboard,
  Package,
  Package2,
  ScrollText,
  Settings2,
  ShieldAlert,
  Gauge,
  CreditCard,
  ShieldCheck,
  UserCog,
  ShoppingCart,
  Users,
  Truck,
  UserCheck,
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
import { tr, useLocale } from "@/lib/i18n";

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
  catalogs: Library,
  storefront: Store,
  admin: Gauge,
  settings: Settings2,
  payments: CreditCard,
  shipping: Truck,
  platform: Boxes,
  applications: UserCheck,
  security: ShieldAlert,
  audit: ScrollText,
};

export function AppSidebar() {
  const user = useCurrentUser();
  const pathname = usePathname();
  const locale = useLocale();
  const items = buildNavigation(user);

  return (
    <Sidebar side={locale === "ar" ? "right" : "left"}>
      <SidebarHeader className="p-6">
        <h1 className="text-xl font-bold">{tr("ArmorHub")}</h1>
        <p className="text-sm text-muted-foreground">
          {user?.industry ? tr(user.industry.name) : tr("Service Marketplace Platform")}
        </p>
      </SidebarHeader>

      <SidebarContent>
        {[
          { heading: null, entries: items.filter((i) => !i.section) },
          { heading: "Administration", entries: items.filter((i) => i.section === "admin") },
        ]
          .filter((g) => g.entries.length > 0)
          .map((group) => (
            <div key={group.heading ?? "main"}>
              {group.heading && (
                <p className="px-4 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  {tr(group.heading)}
                </p>
              )}
              <SidebarMenu>
                {group.entries.map((item) => {
                  const Icon = icons[item.icon] ?? Users;
                  const active =
                    item.href === "/dashboard" || item.href === "/dashboard/admin"
                      ? pathname === item.href
                      : pathname.startsWith(item.href);

                  return (
                    <SidebarMenuItem key={item.href}>
                      <SidebarMenuButton isActive={active} render={<Link href={item.href} />}>
                        <Icon className="h-4 w-4" />
                        <span>{tr(item.title)}</span>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  );
                })}
              </SidebarMenu>
            </div>
          ))}
      </SidebarContent>

      <SidebarFooter className="p-4 text-xs text-muted-foreground">
        © {new Date().getFullYear()} ArmorHub
      </SidebarFooter>
    </Sidebar>
  );
}
