"use client";

import {
  LayoutDashboard,
  ShoppingCart,
  Car,
  Shield,
  Building2,
  Wrench,
  CreditCard,
  BarChart3,
  Settings,
} from "lucide-react";

import {
  Sidebar,
  SidebarContent,
  SidebarHeader,
  SidebarFooter,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";

const items = [
  { title: "Dashboard", icon: LayoutDashboard },
  { title: "Orders", icon: ShoppingCart },
  { title: "Catalog", icon: Car },
  { title: "Products", icon: Shield },
  { title: "Companies", icon: Building2 },
  { title: "Partners", icon: Wrench },
  { title: "Payments", icon: CreditCard },
  { title: "Reports", icon: BarChart3 },
  { title: "Settings", icon: Settings },
];

export function AppSidebar() {
  return (
    <Sidebar>
      <SidebarHeader className="p-6">
        <h1 className="text-xl font-bold">ArmorHub</h1>
        <p className="text-sm text-muted-foreground">
          Service Marketplace Platform
        </p>
      </SidebarHeader>

      <SidebarContent>
        <SidebarMenu>
          {items.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton>
                <item.icon className="h-4 w-4" />
                <span>{item.title}</span>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}
        </SidebarMenu>
      </SidebarContent>

      <SidebarFooter className="p-4 text-xs text-muted-foreground">
        ArmorHub v1.0
      </SidebarFooter>
    </Sidebar>
  );
}