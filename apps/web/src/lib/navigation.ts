import type { User } from "@/types/auth";

export interface NavItem {
  title: string;
  href: string;
  icon:
    | "dashboard"
    | "orders"
    | "catalog"
    | "products"
    | "partners"
    | "tenants"
    | "requests"
    | "commissions"
    | "items";
}

/** "vehicle_part" -> "Vehicle Parts" */
export function aliasLabel(alias: string): string {
  const words = alias.split("_").map((w) => w[0].toUpperCase() + w.slice(1));
  const last = words.length - 1;
  words[last] = words[last].endsWith("s") ? words[last] : `${words[last]}s`;
  return words.join(" ");
}

export function hasRole(user: User | null, ...roles: string[]): boolean {
  return !!user?.roles?.some((r) => roles.includes(r));
}

/**
 * The sidebar adapts to the signed-in user: catalog entries come from the
 * tenant's industry (vehicle parts, room types, device components...), and
 * screens only show for the roles that can use them.
 */
export function buildNavigation(user: User | null): NavItem[] {
  const items: NavItem[] = [
    { title: "Dashboard", href: "/dashboard", icon: "dashboard" },
    { title: "Orders", href: "/dashboard/orders", icon: "orders" },
  ];

  if (hasRole(user, "partner-manager")) {
    items.push({ title: "Requests", href: "/dashboard/requests", icon: "requests" });
  }

  items.push({ title: "Products", href: "/dashboard/products", icon: "products" });

  // What a customer brings (their property, device, vehicle...).
  if (hasRole(user, "customer")) {
    for (const alias of user?.industry?.subjects ?? []) {
      items.push({
        title: `My ${aliasLabel(alias)}`,
        href: `/dashboard/items/${alias}`,
        icon: "items",
      });
    }
  }

  // Shared catalogs are managed by admins and company owners.
  if (hasRole(user, "platform-admin", "company-owner")) {
    for (const alias of user?.industry?.targets ?? []) {
      items.push({
        title: aliasLabel(alias),
        href: `/dashboard/catalog/${alias}`,
        icon: "catalog",
      });
    }
  }

  if (hasRole(user, "platform-admin", "partner-manager")) {
    items.push({ title: "Partners", href: "/dashboard/partners", icon: "partners" });
  }

  if (hasRole(user, "platform-admin", "company-owner", "partner-manager")) {
    items.push({ title: "Commissions", href: "/dashboard/commissions", icon: "commissions" });
  }

  if (hasRole(user, "platform-admin")) {
    items.push({ title: "Tenants", href: "/dashboard/tenants", icon: "tenants" });
  }

  return items;
}
