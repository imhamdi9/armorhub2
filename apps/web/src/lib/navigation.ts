import type { User } from "@/types/auth";

export interface NavItem {
  title: string;
  href: string;
  icon: "dashboard" | "orders" | "catalog" | "products" | "partners" | "tenants";
}

/** "vehicle_part" -> "Vehicle Parts" */
export function aliasLabel(alias: string): string {
  const words = alias.split("_").map((w) => w[0].toUpperCase() + w.slice(1));
  const last = words.length - 1;
  words[last] = words[last].endsWith("s") ? words[last] : `${words[last]}s`;
  return words.join(" ");
}

/**
 * The sidebar adapts to the signed-in user: catalog entries come from the
 * tenant's industry (vehicle parts, room types, device components...), and
 * admin screens only show for platform admins.
 */
export function buildNavigation(user: User | null): NavItem[] {
  const items: NavItem[] = [
    { title: "Dashboard", href: "/dashboard", icon: "dashboard" },
    { title: "Orders", href: "/dashboard/orders", icon: "orders" },
    { title: "Products", href: "/dashboard/products", icon: "products" },
  ];

  for (const alias of user?.industry?.targets ?? []) {
    items.push({
      title: aliasLabel(alias),
      href: `/dashboard/catalog/${alias}`,
      icon: "catalog",
    });
  }

  items.push({ title: "Partners", href: "/dashboard/partners", icon: "partners" });

  if (user?.roles?.includes("platform-admin")) {
    items.push({ title: "Tenants", href: "/dashboard/tenants", icon: "tenants" });
  }

  return items;
}
