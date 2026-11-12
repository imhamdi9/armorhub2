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

export interface PublicShowcasePartner {
  name: string;
  city: string | null;
  country: string | null;
  description: string | null;
  logo_url: string | null;
}

export interface PublicShowcase {
  industries: {
    industry: PublicIndustry;
    stores: { name: string; slug: string; market: string | null; primary_color: string; partners: PublicShowcasePartner[]; products: PublicProduct[] }[];
  }[];
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
  images?: string[];
  compare_at_price?: number | null;
  requires_shipping?: boolean;
  installation?: "none" | "optional" | "required";
  seller?: string | null;
  seller_id?: number | null;
}

export interface PublicSellerPage {
  seller: { id: number; name: string; city: string | null; since: string | null };
  store: { name: string; slug: string };
  products: PublicProducts;
}

export interface PublicStore {
  store: PublicBranding;
  industry: PublicIndustry | null;
  market: PublicMarket | null;
  featured: PublicProduct[];
  categories: string[];
  category_counts: Record<string, number>;
  industries?: PublicIndustry[];
  industry_counts?: Record<string, number>;
  merged_into?: string;
  sections: { category: string; count: number; products: PublicProduct[] }[];
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
    highlights?: string[];
  };
  related?: PublicProduct[];
  store: { name: string; slug: string };
}
