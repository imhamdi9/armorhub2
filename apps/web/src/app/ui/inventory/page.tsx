import { InventoryCockpit, type Sku } from "@/components/layout/inventory-cockpit";

// Sample stock figures for previewing the layout only.
const SKUS: Sku[] = [
  { sku: "BRK-2210", name: "Brake pad set, front", supplier: "Nordic Auto Parts", onHand: 120, forecastPerWeek: 210, inbound: 0, leadDays: 21, unitCost: 18, demand: [180, 195, 200, 190, 205, 215, 208, 210] },
  { sku: "OIL-0540", name: "Synthetic oil 5W-30, 5L", supplier: "Delta Lubricants", onHand: 340, forecastPerWeek: 260, inbound: 200, leadDays: 14, unitCost: 22, demand: [240, 250, 255, 262, 258, 270, 265, 260] },
  { sku: "FLT-0072", name: "Cabin air filter", supplier: "Cairo Filters", onHand: 900, forecastPerWeek: 150, inbound: 0, leadDays: 10, unitCost: 4, demand: [140, 150, 145, 160, 155, 148, 152, 150] },
  { sku: "WPR-3301", name: "Wiper blade pair, 24in", supplier: "Nordic Auto Parts", onHand: 210, forecastPerWeek: 120, inbound: 100, leadDays: 28, unitCost: 7, demand: [90, 100, 105, 110, 118, 122, 119, 120] },
  { sku: "BAT-1200", name: "Car battery 70Ah", supplier: "Delta Lubricants", onHand: 40, forecastPerWeek: 55, inbound: 60, leadDays: 18, unitCost: 62, demand: [40, 44, 48, 50, 52, 58, 54, 55] },
  { sku: "SPK-0090", name: "Spark plug, iridium", supplier: "Cairo Filters", onHand: 1500, forecastPerWeek: 300, inbound: 0, leadDays: 12, unitCost: 5, demand: [280, 290, 300, 295, 310, 305, 298, 300] },
];

export default function InventoryPreview() {
  return <div className="min-h-screen bg-neutral-50"><InventoryCockpit skus={SKUS} suppliers={[{ name: "Nordic Auto Parts", onTime: 91 }, { name: "Delta Lubricants", onTime: 84 }, { name: "Cairo Filters", onTime: 96 }]} /></div>;
}
