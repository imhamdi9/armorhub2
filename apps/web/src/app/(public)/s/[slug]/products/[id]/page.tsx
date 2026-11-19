import type { Metadata } from "next";
import { notFound, redirect } from "next/navigation";

import { ProductDetail } from "@/components/public/product-detail";
import { getStore, getStoreProduct } from "@/lib/public-api";
import { absoluteImage, plain, siteUrl } from "@/lib/seo";

type Props = { params: Promise<{ slug: string; id: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug, id } = await params;
  const data = await getStoreProduct(slug, id);

  if (!data) return { title: "Product not found" };

  const title = `${data.product.name} — ${data.store.name}`;
  const description = plain(data.product.description) ?? `${data.product.name} from ${data.store.name}`;
  const image = absoluteImage(data.product.images?.[0]);
  const url = `/s/${slug}/products/${id}`;

  return {
    title,
    description,
    alternates: { canonical: url },
    openGraph: { title, description, type: "website", url, ...(image ? { images: [image] } : {}) },
    twitter: { card: image ? "summary_large_image" : "summary", title, description, ...(image ? { images: [image] } : {}) },
    // Read by Meta and TikTok when a product link is shared or used in an ad.
    other: { "product:price:amount": String(data.product.from_price), "product:price:currency": data.product.currency, "product:availability": "in stock", ...(data.product.brand ? { "product:brand": data.product.brand } : {}) },
  };
}

export default async function ProductPage({ params }: Props) {
  const { slug, id } = await params;
  const store = await getStore(slug, true);
  if (store?.merged_into) redirect(`/s/${store.merged_into}/products/${id}`);
  const data = await getStoreProduct(slug, id, true);

  if (!store || !data) notFound();

  // Structured data for search engines, built only from what the page shows: no ratings or reviews are invented.
  const p = data.product;
  const images = (p.images ?? []).map((i) => absoluteImage(i)).filter(Boolean);
  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: p.name,
    ...(plain(p.description, 500) ? { description: plain(p.description, 500) } : {}),
    ...(images.length ? { image: images } : {}),
    ...(p.brand ? { brand: { "@type": "Brand", name: p.brand } } : {}),
    ...(p.category ? { category: p.category } : {}),
    offers: {
      "@type": "Offer",
      price: p.from_price,
      priceCurrency: p.currency,
      availability: "https://schema.org/InStock",
      ...(siteUrl() ? { url: `${siteUrl()}/s/${slug}/products/${id}` } : {}),
      seller: { "@type": "Organization", name: p.seller ?? store.store.name },
    },
  };

  return (
    <>
      {/* "<" is escaped so nothing in a product name can close the script tag. */}
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <ProductDetail store={store.store} data={data} />
    </>
  );
}
