import { api } from "@/services/api";

export interface GuestOrder {
  id: number; token?: string; status: string; currency: string; total: number; tax_amount: number | null; created_at: string; email: string; name: string;
  product: { name: string | null; image: string | null; seller: string | null; installation?: "none" | "optional" | "required" };
  store: { name: string | null; slug: string | null };
  shipping_address: { name: string; phone: string; line1: string; line2?: string | null; city: string; region?: string | null; postal_code?: string | null; country: string } | null;
  can_pay: boolean; can_cancel: boolean;
  shipments: { carrier: string | null; tracking_number: string | null; tracking_url: string | null; status: string }[];
  timeline: { type: string; at: string }[];
}

export const createGuestOrder = async (body: Record<string, unknown>) => (await api.post<GuestOrder & { token: string }>("/guest-orders", body)).data;
export const getGuestOrder = async (token: string) => (await api.get<GuestOrder>(`/guest-orders/${encodeURIComponent(token)}`)).data;
export const payGuestOrder = async (token: string) => (await api.post<{ checkout_url?: string } & Partial<GuestOrder>>(`/guest-orders/${encodeURIComponent(token)}/pay`)).data;
export const cancelGuestOrder = async (token: string) => (await api.post<GuestOrder>(`/guest-orders/${encodeURIComponent(token)}/cancel`)).data;
