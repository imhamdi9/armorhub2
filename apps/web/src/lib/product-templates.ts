/** The product-page layouts a platform administrator can choose from. Keys match `Product::PAGE_TEMPLATES` in the API. */
export const PAGE_TEMPLATES = ["classic", "tiers", "builder", "story", "compare", "marketplace", "bundle", "warranty", "quote", "immersive"] as const;

export type PageTemplate = (typeof PAGE_TEMPLATES)[number];

export const DEFAULT_TEMPLATE: PageTemplate = "classic";

export const isTemplate = (value: unknown): value is PageTemplate => typeof value === "string" && (PAGE_TEMPLATES as readonly string[]).includes(value);

/** Name and one-line help for each layout, as shown in the picker. Translated where they are shown. */
export const TEMPLATE_INFO: Record<PageTemplate, { name: string; help: string; offers: string }> = {
  classic: { name: "Classic plus", help: "Gallery, buy box and benefits, with a bundle of items bought together.", offers: "Upsell: bought together" },
  tiers: { name: "Good, better, best", help: "The product's options as three cards, with the middle one highlighted.", offers: "Upsell and downsell by option" },
  builder: { name: "Part builder", help: "The customer picks parts and sees the price update.", offers: "Upsell: add a part · Downsell: fewer parts" },
  story: { name: "Story page", help: "A big hero, the benefits, the steps and a sticky buy bar.", offers: "Sticky call to action" },
  compare: { name: "Compare table", help: "This product next to a cheaper and a pricier one.", offers: "Upsell and downsell by comparison" },
  marketplace: { name: "Marketplace gallery", help: "A large picture grid and a buy panel with the store's other products.", offers: "Upsell: complete the set" },
  bundle: { name: "Bundle builder", help: "Complete-your-set cards and a free-delivery style progress hint.", offers: "Upsell: add-ons · Downsell: skip them" },
  warranty: { name: "Warranty first", help: "A warranty seal and clear fitting choices.", offers: "Upsell: with installer · Downsell: product only" },
  quote: { name: "Quote first", help: "A short form with a live estimate beside the product.", offers: "Upsell: add-on · Downsell: lowest option" },
  immersive: { name: "Immersive split", help: "A full-height picture, accordions and a sticky bottom bar.", offers: "Upsell: before you go" },
};
