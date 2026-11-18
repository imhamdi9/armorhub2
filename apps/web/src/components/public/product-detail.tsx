"use client";

import {
  BuilderLayout, BundleLayout, ClassicLayout, CompareLayout, ImmersiveLayout, MarketplaceLayout, QuoteLayout, StoryLayout, TiersLayout, WarrantyLayout,
} from "@/components/public/product-page/layouts";
import { useProductModel, type ProductModel } from "@/components/public/product-page/model";
import { Crumbs, ReviewsSection, SafetyBlock, StickyBar } from "@/components/public/product-page/parts";
import { QuestionsSection } from "@/components/public/product-page/questions";
import { CompareToggle } from "@/components/shop/compare";
import { RecentlyViewed, RememberView, Shelf } from "@/components/shop/memory";
import type { ShellStore } from "@/components/public/shell";
import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { DEFAULT_TEMPLATE, isTemplate, type PageTemplate } from "@/lib/product-templates";
import { tr } from "@/lib/i18n";
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
        <QuestionsSection productId={data.product.id} items={data.questions ?? []} />
      </div>
      <RememberView slug={store.slug} id={data.product.id} />
      <div className="mb-2 flex justify-end"><CompareToggle slug={store.slug} id={data.product.id} className="border border-border" /></div>
      <Shelf title={tr("Customers also bought")} products={data.recommended?.also_bought ?? []} slug={store.slug} testId="also-bought" />
      <Shelf title={tr("Similar products")} products={data.recommended?.similar ?? []} slug={store.slug} testId="similar" />
      <RecentlyViewed slug={store.slug} exclude={data.product.id} />
      {!OWN_BAR.includes(key) && <StickyBar m={m} />}
    </ShopShell>
  );
}
