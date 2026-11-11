export interface PublicIndustry {
  key: string;
  name: string;
  description?: string | null;
}

export interface PublicMarket {
  code: string;
  name: { en: string; ar: string };
  currency: string;
  locale: string;
  vat_rate: number;
  prices_include_vat: boolean;
  phone: { dial: string; example: string };
}

export interface PublicStoreCard {
  market?: string | null;
  name: string;
  slug: string;
  headline: string | null;
  primary_color: string;
  logo_url: string | null;
  industry: PublicIndustry | null;
}

export interface PublicStoreList {
  stores: PublicStoreCard[];
}

export interface PublicBranding {
  name: string;
  slug: string;
  headline: string | null;
  subheadline: string | null;
  about: string | null;
  primary_color: string;
  logo_url: string | null;
  hero_image_url: string | null;
  contact_email: string | null;
  contact_phone: string | null;
}

export interface PublicProduct {
  id: number;
  name: string;
  brand: string | null;
  category: string | null;
  description: string | null;
  warranty_years: number | null;
  from_price: number;
  currency: string;
}

export interface PublicStore {
  store: PublicBranding;
  industry: PublicIndustry | null;
  market: PublicMarket | null;
  featured: PublicProduct[];
  categories: string[];
  partners: { count: number; cities: string[] };
}

export interface PublicProducts {
  data: PublicProduct[];
  current_page: number;
  last_page: number;
}

export interface PublicProductPage {
  product: PublicProduct & {
    prices: { target_type: string; target: string; price: number; duration_minutes: number | null }[];
  };
  store: { name: string; slug: string };
}
