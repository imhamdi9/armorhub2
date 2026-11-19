import { Pricing } from "@/components/public/pricing";

export const metadata = { title: "Pricing — ArmorHub" };

export default async function PricingPage({ searchParams }: { searchParams: Promise<{ for?: string; store?: string }> }) {
  const { for: who, store } = await searchParams;

  return <Pricing initial={who === "seller" || who === "partner" ? who : undefined} store={store} />;
}
