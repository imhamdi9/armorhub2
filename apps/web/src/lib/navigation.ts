import type { User } from "@/types/auth";

export interface NavItem {
  title: string;
  href: string;
  /** Items in the "admin" section are grouped under an Administration heading. */
  section?: "admin" | "market";
  icon:
    | "dashboard"
    | "orders"
    | "catalog"
    | "products"
    | "partners"
    | "tenants"
    | "requests"
    | "commissions"
    | "items"
    | "companies"
    | "users"
    | "storefront"
    | "admin"
    | "settings"
    | "payments"
    | "shipping"
    | "platform"
    | "applications"
    | "security"
    | "audit"
    | "jobs"
    | "warranties"
    | "catalogs";
}

function pluralize(word: string): string {
  if (/[^aeiou]y$/i.test(word)) return word.slice(0, -1) + "ies";
  if (/(s|x|z|ch|sh)$/i.test(word)) return /s$/i.test(word) ? word : word + "es";
  return word + "s";
}

/** "vehicle_part" -> "Vehicle Parts", "property" -> "Properties" */
export function aliasLabel(alias: string): string {
  const words = alias.split("_").map((w) => w[0].toUpperCase() + w.slice(1));
  const last = words.length - 1;
  words[last] = pluralize(words[last]);
  return words.join(" ");
}

/** "property" -> "Property", "vehicle_model" -> "Vehicle Model" */
export function aliasSingular(alias: string): string {
  return alias
    .split("_")
    .map((w) => w[0].toUpperCase() + w.slice(1))
    .join(" ");
}

const BUILT_IN_ROLES = ["platform-admin", "company-owner", "partner-manager", "technician", "customer", "seller"];

/** Someone whose only roles are ones an admin created. */
export function isCustomRole(user: User | null): boolean {
  return !!user?.roles?.length && user.roles.every((r) => !BUILT_IN_ROLES.includes(r));
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
  const items: NavItem[] = [{ title: "Dashboard", href: "/dashboard", icon: "dashboard" }];

  // Anyone who may bring people into a team sees "Invitations" (the API refuses everyone else); heads of departments also see theirs.
  const mayInvite = hasRole(user, "platform-admin", "company-owner", "seller", "partner-manager") || !!user?.is_department_head;
  const withInvites = (list: NavItem[]) => {
    if (mayInvite) list.push({ title: "Invitations", href: "/dashboard/invitations", icon: "users" });
    if (hasRole(user, "platform-admin") || user?.is_department_head) list.push({ title: "Departments", href: "/dashboard/departments", icon: "companies" });

    return list;
  };

  // Sellers manage their own products and ship the orders for them: nothing else.
  if (hasRole(user, "seller") && !hasRole(user, "platform-admin")) {
    items.push({ title: "Sales", href: "/dashboard/seller-orders", icon: "orders" });
    items.push({ title: "Products", href: "/dashboard/products", icon: "products" });
    items.push({ title: "Warehouse", href: "/dashboard/seller-warehouse", icon: "catalog" });
    items.push({ title: "Earnings", href: "/dashboard/earnings", icon: "commissions" });

    return withInvites(items);
  }

  // A custom role sees only what its permissions allow.
  if (isCustomRole(user)) {
    const can = (p: string) => !!user?.permissions?.includes(p);
    if (can("orders.view")) items.push({ title: "Orders", href: "/dashboard/orders", icon: "orders" });
    if (can("products.view")) items.push({ title: "Products", href: "/dashboard/products", icon: "products" });
    if (can("partners.view")) items.push({ title: "Partners", href: "/dashboard/partners", icon: "partners" });
    if (can("warehouse.manage")) items.push({ title: "Warehouse", href: "/dashboard/warehouse", icon: "catalog" });
    if (can("commissions.view")) items.push({ title: "Commissions", href: "/dashboard/commissions", icon: "commissions" });

    return withInvites(items);
  }

  // Technicians only work assigned jobs; everyone else deals with orders.
  const technicianOnly = hasRole(user, "technician") && !hasRole(user, "partner-manager", "platform-admin", "company-owner");

  if (!technicianOnly) {
    items.push({ title: "Orders", href: "/dashboard/orders", icon: "orders" });
  }

  if (hasRole(user, "partner-manager")) {
    items.push({ title: "Requests", href: "/dashboard/requests", icon: "requests" });
  }

  if (hasRole(user, "partner-manager", "technician")) {
    items.push({ title: "Jobs", href: "/dashboard/jobs", icon: "jobs" });
    items.push({ title: "Installation jobs", href: "/dashboard/centre-jobs", icon: "jobs" });
  }
  if (hasRole(user, "partner-manager")) {
    items.push({ title: "Installation requests", href: "/dashboard/centre-requests", icon: "requests" });
  }
  if (hasRole(user, "partner-manager")) {
    items.push({ title: "Wallet", href: "/dashboard/wallet", icon: "commissions" });
  }

  if (technicianOnly) {
    return items;
  }

  items.push({ title: "Products", href: "/dashboard/products", icon: "products" });

  // What a customer brings (their property, device, vehicle...).
  if (hasRole(user, "customer")) {
    for (const alias of user?.industry?.owned_subjects ?? []) {
      items.push({
        title: `My ${aliasLabel(alias)}`,
        href: `/dashboard/items/${alias}`,
        icon: "items",
      });
    }
  }

  if (hasRole(user, "customer")) {
    items.push({ title: "Installation requests", href: "/dashboard/installations", icon: "jobs" });
    items.push({ title: "Warranties", href: "/dashboard/warranties", icon: "warranties" });
  }

  // Platform admins manage every industry's lists from one index page.
  if (hasRole(user, "platform-admin")) {
    items.push({ title: "Catalogs", href: "/dashboard/catalogs", icon: "catalogs" });
  }

  // Company owners manage their own industry's shared lists.
  if (hasRole(user, "company-owner") && !hasRole(user, "platform-admin")) {
    for (const alias of user?.industry?.catalogs ?? user?.industry?.targets ?? []) {
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

  // Businesses that applied to become partners: decided by admins and the store's owners.
  if (hasRole(user, "platform-admin", "company-owner")) {
    items.push({ title: "Partner applications", href: "/dashboard/partner-applications", icon: "applications" });
  }

  // Owners shape what the public sees of their store (admins reach it from the Companies page).
  if (hasRole(user, "company-owner") && !hasRole(user, "platform-admin")) {
    items.push({ title: "Storefront", href: "/dashboard/storefront", icon: "storefront" });
  }

  // A market manager changes THEIR market's payment, shipping and fee settings (never the platform-wide ones).
  if (hasRole(user, "company-owner") && !hasRole(user, "platform-admin")) {
    items.push({ title: "Market payments", href: "/dashboard/market/payments", section: "market", icon: "payments" });
    items.push({ title: "Market shipping", href: "/dashboard/market/shipping", section: "market", icon: "shipping" });
    items.push({ title: "Market fees", href: "/dashboard/market/fees", section: "market", icon: "commissions" });
  }

  if (hasRole(user, "platform-admin", "company-owner")) {
    items.push({ title: "Warehouse", href: "/dashboard/warehouse", icon: "catalog" });
  }

  if (hasRole(user, "platform-admin", "company-owner", "partner-manager")) {
    items.push({ title: "Team", href: "/dashboard/users", icon: "users" });
    items.push({ title: "Commissions", href: "/dashboard/commissions", icon: "commissions" });
  }

  if (hasRole(user, "platform-admin")) {
    items.push({ title: "Companies", href: "/dashboard/companies", icon: "companies" });

    // The control panel for everything that can be changed without a deploy.
    items.push({ title: "Overview", href: "/dashboard/admin", section: "admin", icon: "admin" });
    items.push({ title: "Markets", href: "/dashboard/admin/markets", section: "admin", icon: "tenants" });
    items.push({ title: "Categories", href: "/dashboard/admin/categories", section: "admin", icon: "products" });
    items.push({ title: "Settings", href: "/dashboard/admin/settings", section: "admin", icon: "settings" });
    items.push({ title: "Payments", href: "/dashboard/admin/payments", section: "admin", icon: "payments" });
    items.push({ title: "Payouts", href: "/dashboard/admin/payouts", section: "admin", icon: "commissions" });
    items.push({ title: "Disputes", href: "/dashboard/admin/disputes", section: "admin", icon: "security" });
    items.push({ title: "Withdrawals", href: "/dashboard/admin/withdrawals", section: "admin", icon: "commissions" });
    items.push({ title: "Shipping", href: "/dashboard/admin/shipping", section: "admin", icon: "shipping" });
    items.push({ title: "Roles", href: "/dashboard/admin/roles", section: "admin", icon: "users" });
    items.push({ title: "Theme editor", href: "/dashboard/admin/theme", section: "admin", icon: "settings" });
    items.push({ title: "Content", href: "/dashboard/admin/content", section: "admin", icon: "settings" });
    items.push({ title: "Platform", href: "/dashboard/admin/platform", section: "admin", icon: "platform" });
    items.push({ title: "Security", href: "/dashboard/admin/security", section: "admin", icon: "security" });
    items.push({ title: "Audit log", href: "/dashboard/admin/audit", section: "admin", icon: "audit" });
  }

  return withInvites(items);
}
