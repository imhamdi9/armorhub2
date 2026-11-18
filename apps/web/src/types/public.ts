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
  /** The category's name in the language being browsed. */
  category_name?: string | null;
  /** Several of the seller's own products sold together at one price. */
  is_bundle?: boolean;
  /** How many are left, shown only when ArmorHub holds the stock and it is running low. */
  units_left?: number | null;
  /** A part that must be checked against the customer's car before it can be bought. */
  fitment_required?: boolean;
  /** fixed: bought at its price; quote: the customer asks sellers for a price first. */
  sale_mode?: "fixed" | "quote";
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
  fulfilled_by_armorhub?: boolean;
  in_stock?: boolean;
  rating?: { average: number | null; count: number };
}

export interface PublicSellerPage {
  seller: { id: number; name: string; city: string | null; since: string | null; about?: string | null; logo?: string | null; hours?: string | null; ships_within_days?: number | null; rating?: { count: number; average: number | null }; delivered_orders?: number };
  store: { name: string; slug: string };
  products: PublicProducts;
}

export interface PublicStore {
  store: PublicBranding;
  industry: PublicIndustry | null;
  market: PublicMarket | null;
  featured: PublicProduct[];
  categories: string[];
  /** Each category's name in the language being browsed (slug -> name). */
  category_labels?: Record<string, string>;
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
  total?: number;
  /** What was searched, and the words it was read as when the typed ones matched nothing. */
  search?: { q: string; corrected: string | null };
  /** What the filters offer: brands with their product counts and the price range. */
  facets?: { brands: { name: string; count: number }[]; price: { min: number; max: number } };
}

export interface PublicProductPage {
  product: PublicProduct & {
    prices: { target_type: string; target_id?: number; target: string; price: number; duration_minutes: number | null }[];
    highlights?: string[];
    fits_note?: string | null;
    bundle_items?: { id: number; name: string | null; quantity: number; image: string | null; description?: string | null; brand?: string | null; price?: number; warranty_years?: number | null }[];
    page?: import("@/lib/product-content").PageContent;
    addons?: PublicProduct[];
    page_template?: string;
  };
  related?: PublicProduct[];
  /** Bought together with this product, and like it. */
  recommended?: { also_bought: PublicProduct[]; similar: PublicProduct[] };
  customer_fee_percentage?: number;
  /** Delivery in this market: the flat fee, the amount above which it is free, and the usual days. */
  shipping?: { flat: number; free_over: number | null; eta_days: number | null };
  reviews?: { count: number; average: number | null; items: { id: number; rating: number; comment: string | null; author: string; date: string | null; photos?: string[] }[] };
  /** Answered questions about the product. */
  questions?: { id: number; question: string; answer: string; asker: string; answered_at: string | null }[];
  /** When a parcel ordered today should arrive; null when the market has not said. */
  delivery_estimate?: { min_days: number; max_days: number; from: string; to: string } | null;
  store: { name: string; slug: string; contact_phone?: string | null };
}
