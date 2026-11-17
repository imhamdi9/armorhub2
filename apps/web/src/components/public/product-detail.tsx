"use client";

import {
  BuilderLayout, BundleLayout, ClassicLayout, CompareLayout, ImmersiveLayout, MarketplaceLayout, QuoteLayout, StoryLayout, TiersLayout, WarrantyLayout,
} from "@/components/public/product-page/layouts";
import { useProductModel, type ProductModel } from "@/components/public/product-page/model";
import { Crumbs, ReviewsSection, SafetyBlock, StickyBar } from "@/components/public/product-page/parts";
import type { ShellStore } from "@/components/public/shell";
import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { DEFAULT_TEMPLATE, isTemplate, type PageTemplate } from "@/lib/product-templates";
import type { PublicProductPage } from "@/types/public";

const LAYOUTS: Record<PageTemplate, (props: { m: ProductModel }) => React.ReactElement> = {
  classic: ClassicLayout,
  tiers: TiersLayout,
  builder: BuilderLayout,
  story: StoryLayout,
  compare: CompareLayout,
  marketplace: MarketplaceLayout,
  bundle: BundleLayout,
  warranty: WarrantyLayout,
  quote: QuoteLayout,
  immersive: ImmersiveLayout,
};

// These layouts draw their own breadcrumb and sticky bar.
const OWN_CRUMBS: PageTemplate[] = ["tiers", "builder", "compare", "marketplace", "bundle", "warranty", "quote", "immersive"];
const OWN_BAR: PageTemplate[] = ["story", "immersive"];

/** The product page. Which layout it uses is chosen per product by a platform administrator; unknown or missing values use the default one. */
export function ProductDetail({ store, data }: { store: ShellStore; data: PublicProductPage }) {
  const m = useProductModel(store, data);
  const key: PageTemplate = isTemplate(data.product.page_template) ? data.product.page_template : DEFAULT_TEMPLATE;
  const Layout = LAYOUTS[key];

  return (
    <ShopShell store={store} active="products">
      <div className="my-6 rounded-[28px] bg-white p-4 md:p-8" style={{ boxShadow: SHOP_SHADOW }} data-page-template={key}>
        {!OWN_CRUMBS.includes(key) && key !== "story" && <Crumbs m={m} />}
        <Layout m={m} />
        <SafetyBlock m={m} />
        <ReviewsSection m={m} />
      </div>
      {!OWN_BAR.includes(key) && <StickyBar m={m} />}
    </ShopShell>
  );
}
