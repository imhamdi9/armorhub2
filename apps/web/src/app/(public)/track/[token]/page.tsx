import { TrackOrder } from "@/components/shop/track-order";

export default async function TrackPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;

  return <TrackOrder token={token} />;
}
