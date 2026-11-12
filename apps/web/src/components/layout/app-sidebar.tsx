"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
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
import { pickList } from "@/lib/surfaces";
import { getSurface } from "@/services/platform.service";

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
  // Menu names an admin has published (Admin → Content → Dashboard menu); anything not renamed keeps the built-in name.
  const [renames, setRenames] = useState<Record<string, string>>({});
  useEffect(() => {
    let live = true;
    // Remembered for a minute per browser tab, so moving between pages costs no extra request.
    const remembered = (() => {
      try {
        const hit = JSON.parse(sessionStorage.getItem("sidebar-surface") ?? "null") as { at: number; values: Parameters<typeof pickList>[0] } | null;
        return hit && Date.now() - hit.at < 60_000 ? hit.values : null;
      } catch {
        return null;
      }
    })();

    (remembered ? Promise.resolve(remembered) : getSurface("app.sidebar").then((values) => {
      try { sessionStorage.setItem("sidebar-surface", JSON.stringify({ at: Date.now(), values })); } catch { /* storage unavailable */ }
      return values;
    })).then((values) => {
      if (!live) return;
      const rows = pickList<{ item: string; label: string }>(values, locale, "labels");
      setRenames(Object.fromEntries(rows.map((r) => [r.item, r.label])));
    }).catch(() => undefined);

    return () => { live = false; };
  }, [locale]);

  return (
    <Sidebar side={locale === "ar" ? "right" : "left"} role="complementary" aria-label={tr("Sidebar")}>
      <SidebarHeader className="p-6">
        <p className="text-xl font-bold">{tr("ArmorHub")}</p>
        <p className="text-sm text-muted-foreground">
          {user?.tenant && !user.roles?.includes("platform-admin") ? user.tenant.name : user?.industry ? tr(user.industry.name) : tr("Service Marketplace Platform")}
        </p>
      </SidebarHeader>

      <SidebarContent>
        {[
          { heading: null, entries: items.filter((i) => !i.section) },
          { heading: "Market settings", entries: items.filter((i) => i.section === "market") },
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
                        <span>{renames[item.title] ?? tr(item.title)}</span>
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
