export type StoreCard = { name: string; slug: string; headline: string | null; primary_color: string; logo_url: string | null; market?: string | null; industry?: { key: string; name: string } | null };

export type Product = {
  id: number; name: string; brand: string | null; category: string | null; description: string | null; warranty_years: number | null;
  from_price: number; currency: string; images?: string[]; compare_at_price?: number | null; requires_shipping?: boolean;
  installation?: "none" | "optional" | "required"; seller?: string | null; in_stock?: boolean;
};

export type PageContent = {
  headline?: string; specs?: { label: string; value: string }[]; faqs?: { q: string; a: string }[]; warranty_terms?: string; lead_time?: string; quote_note?: string;
  free_delivery_from?: number;
};

export type ProductPage = {
  product: Product & { prices: { target_type: string; target_id?: number; target: string; price: number }[]; highlights?: string[]; page?: PageContent };
  customer_fee_percentage?: number;
  store: { name: string; slug: string };
};

export type Paginated<T> = { data: T[]; current_page: number; last_page: number };

export type Order = {
  id: number; status: string; product_id: number; currency: string; total_amount: string; service_fee_rate?: string; service_fee_amount?: string; payable_amount?: number;
  created_at?: string; product?: { name: string; images?: string[] };
};

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
