import { once } from "@/lib/idempotency";
import { api } from "@/services/api";

export interface GuestOrder {
  id: number; token?: string; status: string; currency: string; total: number; service_fee?: number; shipping?: number; service_fee_rate?: number; payable?: number; tax_amount: number | null; created_at: string; email: string; name: string;
  product: { name: string | null; image: string | null; seller: string | null; installation?: "none" | "optional" | "required" };
  store: { name: string | null; slug: string | null };
  shipping_address: { name: string; phone: string; line1: string; line2?: string | null; city: string; region?: string | null; postal_code?: string | null; country: string } | null;
  can_pay: boolean; can_cancel: boolean;
  shipments: { carrier: string | null; tracking_number: string | null; tracking_url: string | null; status: string }[];
  timeline: { type: string; at: string }[];
}

export const createGuestOrder = async (body: Record<string, unknown>) => {
  // The human-check answers are new on every attempt: they are not part of what is being asked for.
  const asked = Object.fromEntries(Object.entries(body).filter(([k]) => !["signup_token", "captcha_token", "website"].includes(k)));
  return once("guest-order", asked, async (key) => (await api.post<GuestOrder & { token: string }>("/guest-orders", body, key)).data);
};
export const getGuestOrder = async (token: string) => (await api.get<GuestOrder>(`/guest-orders/${encodeURIComponent(token)}`)).data;
export const payGuestOrder = async (token: string) =>
  once(`pay-guest-${token}`, null, async (key) => (await api.post<{ checkout_url?: string } & Partial<GuestOrder>>(`/guest-orders/${encodeURIComponent(token)}/pay`, undefined, key)).data);
export const cancelGuestOrder = async (token: string) => (await api.post<GuestOrder>(`/guest-orders/${encodeURIComponent(token)}/cancel`)).data;

export interface GuestMessage { id: number; side: string; mine: boolean; kind: string; body: string; created_at: string }
export const getGuestMessages = async (token: string) => (await api.get<GuestMessage[]>(`/guest-orders/${encodeURIComponent(token)}/messages`)).data;
export const sendGuestMessage = async (token: string, body: string, problem: boolean) => (await api.post<GuestMessage>(`/guest-orders/${encodeURIComponent(token)}/messages`, { body, problem })).data;
