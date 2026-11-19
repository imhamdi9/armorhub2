import { DashboardShell, NAV_ICONS } from "@/components/layout/dashboard-shell";

// Sample figures for previewing the layout only.
export default function DashboardShellPreview() {
  return (
    <DashboardShell
      brand="Meridian"
      nav={[
        { label: "Overview", href: "#", icon: NAV_ICONS.overview, active: true },
        { label: "Orders", href: "#", icon: NAV_ICONS.orders },
        { label: "Customers", href: "#", icon: NAV_ICONS.customers },
        { label: "Products", href: "#", icon: NAV_ICONS.products },
        { label: "Reports", href: "#", icon: NAV_ICONS.reports },
        { label: "Settings", href: "#", icon: NAV_ICONS.settings },
      ]}
      user={{ name: "Amira Hassan", email: "amira@northwind.example" }}
      title="Overview"
      action={{ label: "New order", href: "#new-order" }}
      kpis={[
        { label: "Revenue", value: "$48,260", delta: "12.4%", direction: "up", positive: true },
        { label: "Orders", value: "1,284", delta: "4.1%", direction: "up", positive: true },
        { label: "Refund rate", value: "2.3%", delta: "0.6 pts", direction: "down", positive: true },
        { label: "Avg. delivery time", value: "3.2 days", delta: "0.4 days", direction: "up", positive: false },
      ]}
    />
  );
}
