export type StoreCard = { name: string; slug: string; headline: string | null; primary_color: string; logo_url: string | null; market?: string | null; industry?: { key: string; name: string } | null };

export type Product = {
  id: number; name: string; brand: string | null; category: string | null; description: string | null; warranty_years: number | null;
  from_price: number; currency: string; images?: string[]; compare_at_price?: number | null; requires_shipping?: boolean;
  installation?: "none" | "optional" | "required"; sale_mode?: "fixed" | "quote"; seller?: string | null; in_stock?: boolean; units_left?: number | null;
  rating?: { count: number; average: number | null };
};

export type PageContent = {
  headline?: string; specs?: { label: string; value: string }[]; faqs?: { q: string; a: string }[]; warranty_terms?: string; lead_time?: string; quote_note?: string;
  free_delivery_from?: number;
};

export type ProductPage = {
  product: Product & { prices: { target_type: string; target_id?: number; target: string; price: number }[]; highlights?: string[]; page?: PageContent };
  customer_fee_percentage?: number;
  shipping?: { flat: number; free_over: number | null; eta_days: number | null };
  store: { name: string; slug: string };
};

export type Paginated<T> = { data: T[]; current_page: number; last_page: number };

export type Order = {
  id: number; status: string; product_id: number; currency: string; total_amount: string; service_fee_rate?: string; service_fee_amount?: string; payable_amount?: number;
  created_at?: string; product?: { name: string; images?: string[] };
  delivery_speed?: string; preferred_day?: string | null; gift_note?: string | null;
};

export type ShipmentView = { id: number; status: string; carrier: string | null; tracking_number: string | null; delivery_code?: string; attempts: number; failed_reason: string | null; reschedule_for: string | null };

export type GuestOrder = {
  id: number; status: string; currency: string; total: number; service_fee?: number; service_fee_rate?: number; payable?: number;
  product: { name: string | null; image: string | null }; store: { name: string | null };
  can_pay: boolean; shipments: { carrier: string | null; tracking_number: string | null; status: string }[];
};

/** The status words the person sees. */
export const STATUS_TEXT: Record<string, string> = {
  awaiting_payment: "Waiting for payment", paid: "Paid", completed: "Completed", cancelled: "Cancelled",
  service_in_progress: "In progress", appointment_scheduled: "In progress", pending_quotes: "In progress", quotes_received: "In progress", partner_selected: "In progress",
};

export type Showcase = { industries: { industry: { key: string; name: string }; stores: { name: string; slug: string; primary_color: string; market: string | null; products: Product[] }[] }[] };

export type CartLine = { id: number; product_id: number; name: string | null; quantity: number; unit_price: number | null; line_total: number | null; currency: string | null; problem: string | null };
export type CartView = {
  items: CartLine[]; currency: string | null; items_amount: number; service_fee_amount: number; shipping_amount: number; discount_amount: number;
  promo_code: string | null; promo_problem: string | null; eta_days: number | null; free_shipping_over: number | null; payable_amount: number; can_checkout: boolean;
  delivery_options?: { key: "standard" | "express"; extra_fee: number; eta_days: number | null }[];
  points?: { balance: number; max_use: number; value: number } | null;
};
export type Address = { id: number; label: string | null; name: string; phone: string; line1: string; line2: string | null; city: string; is_default: boolean };
export type RefundState = { request: { kind: string; status: string; note: string | null; return_code?: string | null; pickup_day?: string | null; inspection?: string | null; inspection_note?: string | null } | null; refund_status: string | null; refund_amount: number | null; can_request: boolean; return_days: number };
export type OrderMessage = { id: number; side: string; mine: boolean; kind: string; body: string; created_at: string };

export type Vehicle = { id: number; label: string; nickname: string | null; is_default: boolean };
export type QuoteRequest = {
  id: number; number: string; title: string | null; category: string | null; quantity: number; status: string; currency: string;
  quotes: { id: number; number: string; total: number; lead_days: number | null; warranty_days: number | null; expired: boolean; status: string }[];
};
