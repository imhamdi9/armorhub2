export interface PublicIndustry {
  key: string;
  name: string;
  description?: string | null;
}

export interface PublicStoreCard {
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
