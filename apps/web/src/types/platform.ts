export interface Product {
  id: number;
  industry_id: number | null;
  category: string | null;
  brand: string | null;
  name: string;
  slug: string;
  description: string | null;
  base_price: string;
  warranty_years: number | null;
  attributes: Record<string, unknown> | null;
  is_active: boolean;
}

export interface PriceRule {
  id?: number;
  target_type: string;
  target_id: number;
  price: number | string;
  duration_minutes?: number | null;
  is_active?: boolean;
}

export interface CatalogItem {
  id: number;
  name: string;
  slug: string;
}

export interface Order {
  id: number;
  status: string;
  product_id: number;
  subject_type: string | null;
  subject_id: number | null;
  product_amount: string;
  service_amount: string;
  total_amount: string;
  currency: string;
  scheduled_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  cancelled_at?: string | null;
  events?: OrderEvent[];
  warranty?: Warranty | null;
  customer?: { id: number; name: string; city: string | null; country: string | null };
  subject?: Record<string, unknown> | null;
  product?: Product;
}

export interface OrderEvent {
  id: number;
  type: string;
  data: Record<string, unknown> | null;
  created_at: string;
  user?: { id: number; name: string } | null;
}

export interface Warranty {
  id: number;
  order_id: number;
  starts_at: string;
  ends_at: string;
  active?: boolean;
  product?: { id: number; name: string };
}

export interface Partner {
  id: number;
  name: string;
  city: string | null;
  country: string | null;
  phone: string | null;
}

export interface Tenant {
  id: number;
  name: string;
  industry_id: number | null;
  industry?: { id: number; key: string; name: string } | null;
}

export interface PartnerQuote {
  id: number;
  order_id: number;
  partner_id: number;
  price: string | number;
  status: "pending" | "submitted" | "accepted" | "rejected";
  partner?: Partner;
  order?: Order & { product?: Product; subject?: Record<string, unknown> | null };
}

export interface CommissionDistribution {
  id: number;
  type: "platform" | "company" | "partner";
  amount: string;
  status: "pending" | "paid";
  paid_at: string | null;
  partner?: Partner | null;
}

export interface Earnings {
  platform: number | string;
  companies: number | string;
  partners: number | string;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
}

export interface PriceQuote {
  product_id: number;
  parts_count: number;
  total: number | string;
  currency: string;
}

export interface StaffUser {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  role: string | null;
  company: { id: number; name: string } | null;
  partner: { id: number; name: string } | null;
}

export interface AppNotification {
  id: string;
  title: string;
  body: string;
  path: string;
  read: boolean;
  created_at: string;
}
