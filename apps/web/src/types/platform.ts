export interface Product {
  id: number;
  industry_id: number | null;
  category: string | null;
  brand: string | null;
  name: string;
  slug: string;
  description: string | null;
  images?: string[] | null;
  compare_at_price?: string | null;
  base_price: string;
  warranty_years: number | null;
  attributes: Record<string, unknown> | null;
  is_active: boolean;
  requires_shipping?: boolean;
  installation?: "none" | "optional" | "required";
  sale_mode?: "fixed" | "quote";
  page_template?: string | null;
  seller_id?: number | null;
  sku?: string | null;
  fulfilment?: "seller" | "armorhub";
  units_per_carton?: number | null;
  carton_length_cm?: string | number | null;
  carton_width_cm?: string | number | null;
  carton_height_cm?: string | number | null;
  carton_weight_kg?: string | number | null;
  stock_cartons?: number;
  /** Set by the server on a single product: whether the signed-in person may edit it. */
  can_edit?: boolean;
  /** On the product list: how many options have a price. A product with none cannot be ordered yet. */
  price_rules_count?: number;
  /** On the product list, for people who manage products: how complete the listing is. */
  readiness?: { score: number; ready: boolean; missing: number };
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
  created_at?: string;
  quantity?: number;
  shipping_amount?: string;
  discount_amount?: string;
  refund_status?: "pending" | "refunded" | null;
  product_id: number;
  subject_type: string | null;
  subject_id: number | null;
  product_amount: string;
  service_amount: string;
  total_amount: string;
  service_fee_rate?: string;
  service_fee_amount?: string;
  payable_amount?: number;
  currency: string;
  market?: string | null;
  tax_rate?: string;
  tax_amount?: string;
  scheduled_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  cancelled_at?: string | null;
  events?: OrderEvent[];
  /** The order, the payment, the goods, the service and (for staff) the settlement, each with its own status. */
  statuses?: { order: string; payment: string; product?: string | null; service?: string | null; settlement?: string | null };
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

export interface ShippingAddress {
  name: string;
  phone: string;
  line1: string;
  line2?: string;
  city: string;
  region?: string;
  postal_code?: string;
  country: string;
}

export interface ShipmentEvent {
  id: number;
  status: string;
  description: string | null;
  location: string | null;
  occurred_at: string;
}

export interface Shipment {
  id: number;
  order_id: number;
  provider: string;
  carrier: string | null;
  service: string | null;
  tracking_number: string | null;
  tracking_url: string | null;
  label_url: string | null;
  status: string;
  cost: string | null;
  currency: string | null;
  events: ShipmentEvent[];
  /** Only the customer receives it, while the parcel is on its way. */
  delivery_code?: string;
  attempts: number;
  failed_reason: string | null;
  reschedule_for: string | null;
  reschedule_note: string | null;
}

export interface OrderShipping {
  delivery_speed?: "standard" | "express";
  preferred_day?: string | null;
  gift_note?: string | null;
  requires_shipping: boolean;
  can_manage: boolean;
  address: ShippingAddress | null;
  shipments: Shipment[];
}

export interface ShippingChoice {
  default: string;
  providers: { key: string; label: string; manual: boolean }[];
}

export interface ShippingRate {
  service: string;
  label: string;
  price: number;
  currency: string;
  eta_days: number | null;
}

export interface Warranty {
  id: number;
  order_id: number;
  starts_at: string;
  ends_at: string;
  active?: boolean;
  product?: { id: number; name: string; requires_shipping?: boolean };
  claims?: WarrantyClaim[];
  /** Goods under a running warranty with no claim in progress. */
  can_claim?: boolean;
}

export interface WarrantyClaim {
  id: number;
  warranty_id: number;
  description: string;
  status: "open" | "approved" | "rejected" | "resolved";
  resolution: "repair" | "replace" | "refund" | null;
  note: string | null;
  created_at: string | null;
  product?: { id: number; name: string };
  customer?: string;
}

export interface Partner {
  id: number;
  name: string;
  city: string | null;
  country: string | null;
  phone: string | null;
  public_profile?: boolean;
  description?: string | null;
  logo_url?: string | null;
}

export interface Tenant {
  id: number;
  name: string;
  industry_id: number | null;
  market?: string | null;
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
  /** Whether the signed-in person may change or remove this account. */
  manageable?: boolean;
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

export interface ApplicationDocument {
  id: number;
  kind: string;
  original_name: string;
  mime: string;
  size: number;
}

export interface PartnerApplication {
  id: number;
  type: "partner" | "seller";
  registration_number: string | null;
  tax_number: string | null;
  marketplace?: string | null;
  documents: ApplicationDocument[];
  missing_documents: string[];
  business_name: string;
  contact_name: string;
  email: string;
  phone: string;
  city: string;
  country: string;
  description: string | null;
  status: "pending" | "approved" | "rejected";
  rejection_reason: string | null;
  created_at: string;
  reviewed_at: string | null;
  store?: { id: number; name: string; slug: string };
}

export interface ProductReadiness {
  score: number;
  ready: boolean;
  done: number;
  total: number;
  items: { key: string; ok: boolean; label: string; hint: string }[];
}
