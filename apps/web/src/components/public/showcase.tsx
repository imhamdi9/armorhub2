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
    <section id="partners" className="scroll-mt-20 bg-white">
      <div className="mx-auto max-w-6xl px-4 py-14">
        <h2 className="display display-md text-center">{tr("Our trusted partners")}</h2>
        <p className="mx-auto mt-2 max-w-2xl text-center text-slate">{tr("Vetted professionals and what they offer, by industry.")}</p>

        <nav className="mt-6 flex flex-wrap justify-center gap-2" aria-label={tr("Industries")}>
          {data.industries.map(({ industry }) => (
            <a key={industry.key} href={`#industry-${industry.key}`} className="rounded-full bg-fog px-4 py-1.5 text-sm font-semibold text-forest hover:bg-linen">
              <span aria-hidden="true">{ICONS[industry.key] ?? "•"}</span> {tr(industry.name)}
            </a>
          ))}
        </nav>

        <div className="mt-10 space-y-12">
          {data.industries.map(({ industry, stores }) => (
            <div key={industry.key} id={`industry-${industry.key}`} className="scroll-mt-20 rounded-[28px] bg-muted p-6 md:p-8">
              <div className="flex items-start gap-3">
                <span className="text-3xl" aria-hidden="true">{ICONS[industry.key] ?? "•"}</span>
                <div>
                  <h3 className="text-2xl font-black tracking-tight text-obsidian">{tr(industry.name)}</h3>
                  {industry.description && <p className="text-sm text-slate">{tr(industry.description)}</p>}
                </div>
              </div>

              <div className="mt-6 space-y-8">
                {stores.map((s) => (
                  <div key={s.slug} className="grid gap-6 border-t border-fog pt-6 first:border-t-0 first:pt-0 lg:grid-cols-3">
                    <div>
                      <div className="mb-2 h-1.5 w-12 rounded-full" style={{ backgroundColor: s.primary_color }} />
                      <Link href={`/s/${s.slug}`} className="text-lg font-bold text-obsidian hover:underline">{s.name}</Link>
                      {s.market && <p className="text-xs text-slate">{tr(COUNTRY[s.market] ?? s.market)}</p>}
                      <Link href={`/s/${s.slug}`} className="mt-3 inline-block text-sm font-semibold text-forest underline underline-offset-4">{tr("Visit store")}</Link>
                    </div>

                    <div>
                      <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-spruce">{tr("Partners")}</h4>
                      {s.partners.length === 0 ? (
                        <p className="text-sm text-slate">{tr("Partners will be listed here soon.")}</p>
                      ) : (
                        <ul className="space-y-3">
                          {s.partners.map((p) => (
                            <li key={p.name} className="flex gap-3">
                              {p.logo_url ? (
                                // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the partner
                                <img src={p.logo_url} alt="" className="h-10 w-10 rounded-full border border-fog bg-white object-contain" />
                              ) : (
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-linen text-forest"><Icon name="shield" className="h-5 w-5" /></span>
                              )}
                              <div>
                                <p className="font-medium">{p.name}</p>
                                <p className="text-xs text-slate">{[p.city, p.country].filter(Boolean).join(" · ")}</p>
                                {p.description && <p className="mt-1 text-sm text-slate">{p.description}</p>}
                              </div>
                            </li>
                          ))}
                        </ul>
                      )}
                    </div>

                    <div>
                      <h4 className="mb-2 text-xs font-bold uppercase tracking-wider text-spruce">{tr("Products and services")}</h4>
                      {s.products.length === 0 ? (
                        <p className="text-sm text-slate">{tr("No products yet.")}</p>
                      ) : (
                        <ul className="space-y-2">
                          {s.products.map((p) => (
                            <li key={p.id}>
                              <Link href={`/s/${s.slug}/products/${p.id}`} className="flex items-baseline justify-between gap-3 rounded-2xl border border-fog bg-white px-4 py-2.5 text-sm hover:bg-linen">
                                <span className="font-medium">{p.name}{p.brand ? <span className="font-normal text-slate"> · {p.brand}</span> : null}</span>
                                <span className="shrink-0 font-semibold text-forest">{tr("From")} {price(p)}</span>
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
