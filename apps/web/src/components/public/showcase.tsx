import Link from "next/link";

import { Icon } from "@/components/public/art";
import { price } from "@/components/public/product-card";
import { tr } from "@/lib/i18n";
import type { PublicShowcase } from "@/types/public";

const COUNTRY: Record<string, string> = { SA: "Saudi Arabia", EG: "Egypt" };

const ICONS: Record<string, string> = { automotive: "🚗", "home-services": "🏠", beauty: "💆", devices: "📱" };

/**
 * Trusted partners and what they offer, one block per industry. Partners are shown only if they opted in,
 * and only by name, city, description and logo. Each industry is its own anchor so the page can be linked
 * and read without JavaScript.
 */
export function PartnersShowcase({ data }: { data: PublicShowcase }) {
  if (data.industries.length === 0) return null;

  return (
    <section id="partners" className="scroll-mt-20 bg-gray-50">
      <div className="mx-auto max-w-6xl px-4 py-14">
        <h2 className="text-center text-3xl font-bold">{tr("Our trusted partners")}</h2>
        <p className="mx-auto mt-2 max-w-2xl text-center text-gray-600">{tr("Vetted professionals and what they offer, by industry.")}</p>

        <nav className="mt-6 flex flex-wrap justify-center gap-2" aria-label={tr("Industries")}>
          {data.industries.map(({ industry }) => (
            <a key={industry.key} href={`#industry-${industry.key}`} className="rounded-full border bg-white px-4 py-1.5 text-sm font-medium hover:bg-gray-100">
              <span aria-hidden="true">{ICONS[industry.key] ?? "•"}</span> {tr(industry.name)}
            </a>
          ))}
        </nav>

        <div className="mt-10 space-y-12">
          {data.industries.map(({ industry, stores }) => (
            <div key={industry.key} id={`industry-${industry.key}`} className="scroll-mt-20 rounded-3xl border bg-white p-6 md:p-8">
              <div className="flex items-start gap-3">
                <span className="text-3xl" aria-hidden="true">{ICONS[industry.key] ?? "•"}</span>
                <div>
                  <h3 className="text-2xl font-bold">{tr(industry.name)}</h3>
                  {industry.description && <p className="text-sm text-gray-600">{tr(industry.description)}</p>}
                </div>
              </div>

              <div className="mt-6 space-y-8">
                {stores.map((s) => (
                  <div key={s.slug} className="grid gap-6 border-t pt-6 first:border-t-0 first:pt-0 lg:grid-cols-3">
                    <div>
                      <div className="mb-2 h-1.5 w-12 rounded-full" style={{ backgroundColor: s.primary_color }} />
                      <Link href={`/s/${s.slug}`} className="text-lg font-semibold hover:underline">{s.name}</Link>
                      {s.market && <p className="text-xs text-gray-500">{tr(COUNTRY[s.market] ?? s.market)}</p>}
                      <Link href={`/s/${s.slug}`} className="mt-3 inline-block text-sm underline">{tr("Visit store")}</Link>
                    </div>

                    <div>
                      <h4 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{tr("Partners")}</h4>
                      {s.partners.length === 0 ? (
                        <p className="text-sm text-gray-500">{tr("Partners will be listed here soon.")}</p>
                      ) : (
                        <ul className="space-y-3">
                          {s.partners.map((p) => (
                            <li key={p.name} className="flex gap-3">
                              {p.logo_url ? (
                                // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the partner
                                <img src={p.logo_url} alt="" className="h-10 w-10 rounded-lg border object-contain" />
                              ) : (
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg border bg-gray-50 text-gray-500"><Icon name="shield" className="h-5 w-5" /></span>
                              )}
                              <div>
                                <p className="font-medium">{p.name}</p>
                                <p className="text-xs text-gray-500">{[p.city, p.country].filter(Boolean).join(" · ")}</p>
                                {p.description && <p className="mt-1 text-sm text-gray-600">{p.description}</p>}
                              </div>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>

                    <div>
                      <h4 className="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{tr("Products and services")}</h4>
                      {s.products.length === 0 ? (
                        <p className="text-sm text-gray-500">{tr("No products yet.")}</p>
                      ) : (
                        <ul className="space-y-2">
                          {s.products.map((p) => (
                            <li key={p.id}>
                              <Link href={`/s/${s.slug}/products/${p.id}`} className="flex items-baseline justify-between gap-3 rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
                                <span className="font-medium">{p.name}{p.brand ? <span className="font-normal text-gray-500"> · {p.brand}</span> : null}</span>
                                <span className="shrink-0 text-gray-700">{tr("From")} {price(p)}</span>
                              </Link>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
