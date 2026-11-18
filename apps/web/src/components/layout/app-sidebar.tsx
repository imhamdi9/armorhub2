"use client";

import { platformLogo } from "@/lib/platform-brand";
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

import { LogoMark } from "@/components/public/art";
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
    <Sidebar variant="floating" side={locale === "ar" ? "right" : "left"} role="complementary" aria-label={tr("Sidebar")}>
      <SidebarHeader className="p-3 pb-1">
        <Link href="/" className="block rounded-2xl px-3 py-4 transition-colors hover:bg-[#0b1b3a]/5" aria-label={tr("ArmorHub")}>
          <span className="flex items-center gap-2.5">
            {platformLogo() ? <img src={platformLogo() ?? ""} alt="" className="h-[34px] w-[34px] shrink-0 rounded-lg object-cover" /> : <LogoMark className="h-[34px] w-[34px] shrink-0" />}
            <span className="font-display text-xl font-bold tracking-tight text-[#0b1b3a]">{tr("ArmorHub")}</span>
          </span>
          <span className="mt-1 block ps-[44px] text-[12.5px] text-[#5b6784]">
            {user?.tenant && !user.roles?.includes("platform-admin") ? user.tenant.name : user?.industry ? tr(user.industry.name) : tr("Service Marketplace Platform")}
          </span>
        </Link>
      </SidebarHeader>

      <SidebarContent className="px-3">
        {[
          { heading: null, entries: items.filter((i) => !i.section) },
          { heading: "Market settings", entries: items.filter((i) => i.section === "market") },
          { heading: "Administration", entries: items.filter((i) => i.section === "admin") },
        ]
          .filter((g) => g.entries.length > 0)
          .map((group) => (
            <div key={group.heading ?? "main"}>
              {group.heading && (
                <p className="px-4 pb-1 pt-5 text-[11.5px] font-semibold tracking-wide text-[#5b6784]">
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
                      <SidebarMenuButton isActive={active} className="h-10 gap-3 rounded-xl px-3 text-[14.5px] font-medium data-active:font-semibold" render={<Link href={item.href} />}>
                        <Icon className="h-[18px] w-[18px]" />
                        <span>{renames[item.title] ?? tr(item.title)}</span>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  );
                })}
              </SidebarMenu>
            </div>
          ))}
      </SidebarContent>

      <SidebarFooter className="p-3">
        <Link href="/dashboard/help" className="block rounded-2xl bg-[#0b1b3a] p-4 text-white transition hover:brightness-110">
          <span className="block text-sm font-semibold">{tr("Need a hand?")}</span>
          <span className="block text-xs text-white/70">{tr("Ask us or read the FAQ")}</span>
        </Link>
      </SidebarFooter>
    </Sidebar>
  );
}
