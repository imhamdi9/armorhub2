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
