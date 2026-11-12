"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

import { Banner, Bento, Compatibility, DashboardFeature, DevRow, factsOf, Hero, IndustryTabs, Markets, PartnerStrip, QuoteFeature, UseCases, ValueProps, WhySplit, COUNTRY_NAMES } from "@/components/public/landing";
import { Faq } from "@/components/public/faq";
import { PartnersShowcase } from "@/components/public/showcase";
import { PublicShell } from "@/components/public/shell";
import { SiteProvider, useSiteFlag, useEditable, useSiteList, useSiteText, useSiteValue } from "@/components/public/site-content";
import { tr } from "@/lib/i18n";
import { imageSrc, orderSections, type SectionKey, type SiteContent } from "@/lib/surfaces";
import type { PublicShowcase, PublicStoreCard } from "@/types/public";

/** Shown when the server could not be reached (a sleeping free instance takes up to a minute), instead of claiming there are no markets. Tries again by itself. */
function WakingUp() {
  const router = useRouter();
  useEffect(() => {
    const id = setTimeout(() => router.refresh(), 6000);

    return () => clearTimeout(id);
  }, [router]);

  return (
    <div className="mt-6 rounded-[20px] border border-dashed border-fog p-8 text-center text-slate" role="status">
      <p className="font-semibold text-obsidian">{tr("The service is waking up.")}</p>
      <p className="mt-1 text-sm">{tr("This can take up to a minute the first time. This page will refresh by itself.")}</p>
      <button type="button" onClick={() => router.refresh()} className="btn-brand mt-4 px-5 py-2.5 text-sm">{tr("Try again")}</button>
    </div>
  );
}

const STEPS = [
  ["1", "Choose what you need", "Pick a service and the options you want. You see a price straight away."],
  ["2", "Get quotes", "Nearby, vetted professionals send you their price for the job."],
  ["3", "Pick and pay securely", "Compare, accept the quote you like and pay online."],
  ["4", "Get it done", "Your professional schedules the work and you follow every step. Warranty included."],
];

/** The platform's front door: what it is, who it serves, and every open store. */
export function PlatformHome({ stores, showcase, site, editing = false, unavailable = false }: { stores: PublicStoreCard[]; showcase: PublicShowcase; site?: SiteContent; editing?: boolean; unavailable?: boolean }) {
  return (
    <SiteProvider content={site ?? { home: {}, brand: {}, nav: {} }} editing={editing}>
      <PlatformHomeBody stores={stores} showcase={showcase} unavailable={unavailable} />
    </SiteProvider>
  );
}

function PlatformHomeBody({ stores, showcase, unavailable }: { stores: PublicStoreCard[]; showcase: PublicShowcase; unavailable: boolean }) {
  const facts = factsOf(stores, showcase);
  const on = {
    partnerStrip: useSiteFlag("show_partner_strip"), industries: useSiteFlag("show_industries"), quote: useSiteFlag("show_quote_feature"),
    dashboard: useSiteFlag("show_dashboard_feature"), values: useSiteFlag("show_value_props"), partners: useSiteFlag("show_partners"),
    useCases: useSiteFlag("show_use_cases"), why: useSiteFlag("show_why"), how: useSiteFlag("show_how"), bento: useSiteFlag("show_bento"),
    dev: useSiteFlag("show_developers"), compat: useSiteFlag("show_compat"), markets: useSiteFlag("show_markets"), faq: useSiteFlag("show_faq"),
  };
  const faqItems = useSiteList<{ question: string; answer: string }>("home", "faq");
  const ed = useEditable();
  const howTitle = useSiteText("home", "how_title", "How it works");
  const howCustom = useSiteList<{ title: string; text: string }>("home", "how_steps");
  const steps = howCustom.length > 0 ? howCustom.map((c, i) => [String(i + 1), c.title, c.text, true] as const) : STEPS.map(([n, t, x]) => [n, tr(t), tr(x), false] as const);
  const marketsTitle = useSiteText("home", "markets_title", "ArmorHub Markets");
  const bannerTitle = useSiteText("home", "banner_title", "Run a business? Open your own store.");
  const bannerText = useSiteText("home", "banner_text", "Get a branded storefront, receive quote requests from nearby customers and get paid securely.");
  const bannerCta = useSiteText("home", "banner_cta", "For businesses: sign in");

  const order = orderSections(useSiteList<{ section: string }>("home", "section_order").map((r) => r.section));
  const bannerImage = useSiteValue("home", "banner_image_url");
  const blocks: Record<SectionKey, React.ReactNode> = {
    partner_strip: on.partnerStrip && <PartnerStrip showcase={showcase} facts={facts} />,
    industries: on.industries && <IndustryTabs showcase={showcase} />,
    stores: (
      <section id="stores" className="mx-auto max-w-[1200px] scroll-mt-20 px-4 py-14">
        <h2 className="display display-md">{marketsTitle}</h2>
        {stores.length === 0 && unavailable ? (
          <WakingUp />
        ) : stores.length === 0 ? (
          <p className="mt-6 rounded-[20px] border border-dashed border-fog p-8 text-center text-slate">{tr("No markets are open yet. Please check back soon.")}</p>
        ) : (
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {stores.map((s) => (
              <Link key={s.slug} href={`/s/${s.slug}`} className="hover-lift rounded-[20px] border border-fog bg-white p-6">
                <div className="mb-4 h-2 w-14 rounded-full" style={{ backgroundColor: s.primary_color }} />
                <div className="flex items-center gap-2">
                  {s.logo_url && (
                    // eslint-disable-next-line @next/next/no-img-element -- an arbitrary https logo chosen by the store owner
                    <img src={imageSrc(s.logo_url) ?? ""} alt="" className="h-8 w-auto max-w-[6rem] object-contain" />
                  )}
                  <h3 className="text-xl font-bold text-obsidian">{s.name}</h3>
                </div>
                {s.industry && <p className="mt-1 text-xs font-bold uppercase tracking-wider text-spruce">{tr(s.industry.name)}{s.market ? ` · ${tr(COUNTRY_NAMES[s.market] ?? s.market)}` : ""}</p>}
                {s.headline && <p className="mt-3 text-sm text-slate">{s.headline}</p>}
              </Link>
            ))}
          </div>
        )}
      </section>
    ),
    quote_feature: on.quote && <QuoteFeature />,
    dashboard_feature: on.dashboard && <DashboardFeature />,
    value_props: on.values && <ValueProps />,
    partners: on.partners && <PartnersShowcase data={showcase} />,
    use_cases: on.useCases && <UseCases showcase={showcase} />,
    why: on.why && <WhySplit />,
    how: on.how && (
      <section id="how" className="scroll-mt-20 bg-white py-14">
        <div className="mx-auto max-w-[1200px] px-4">
          <h2 className="display display-md text-center">{howTitle}</h2>
          <ol className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {steps.map(([n, title, text]) => (
              <li key={n} className="hover-lift rounded-[20px] border border-fog bg-white p-6">
                <span className="btn-brand inline-flex h-9 w-9 items-center justify-center text-sm font-black">{n}</span>
                <h3 className="mt-4 font-bold text-obsidian">{title}</h3>
                <p className="mt-1 text-sm text-slate">{text}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>
    ),
    bento: on.bento && <Bento facts={facts} />,
    developers: on.dev && <DevRow />,
    compat: on.compat && <Compatibility />,
    markets: on.markets && <Markets />,
    faq: on.faq && (faqItems.length > 0 ? <Faq items={faqItems.map((i) => ({ q: i.question, a: i.answer }))} /> : <Faq />),
  };

  return (
    <PublicShell>
      <Hero facts={facts} firstStore={stores[0]} />
      {order.map((key) => (blocks[key] ? <div key={key} data-block={key} style={{ display: "contents" }}>{blocks[key]}</div> : null))}
      <div {...ed("banner_title")} style={{ display: "contents" }}><Banner title={bannerTitle} text={bannerText} href="/login" label={bannerCta} image={bannerImage} secondary={{ href: "/signup", label: "Create an account" }} /></div>
    </PublicShell>
  );
}
