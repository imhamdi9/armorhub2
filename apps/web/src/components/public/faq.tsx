import { tr } from "@/lib/i18n";

export const FAQS: { q: string; a: string }[] = [
  { q: "How does it work?", a: "Choose a service and your options and you see the price straight away. Nearby, vetted professionals then send you their quote. You compare, accept the one you like, and pay online." },
  { q: "Is my payment safe?", a: "Yes. You pay through a secure payment page and we never see or store your card details. The work is only released to the professional once your payment is confirmed." },
  { q: "Are prices final? Is VAT included?", a: "The price you see is the price you pay. Where VAT applies it is already included, and your order shows the VAT amount." },
  { q: "What if I am not happy with the work?", a: "Every completed job is covered by the warranty shown on the product. If something is wrong, you can contact the store from your order and the professional puts it right." },
  { q: "Can I cancel an order?", a: "You can cancel any order before you pay, at no cost. After payment, contact the store from your order page." },
  { q: "Which countries and languages do you support?", a: "We currently serve Saudi Arabia and Egypt, in Arabic and English, with prices in Saudi riyals and Egyptian pounds." },
  { q: "I run a business. Can I join?", a: "Yes. Businesses can open their own branded store and receive quote requests from nearby customers. Sign in or contact us to get started." },
];

/** Accordion (plain <details>, so it works without JavaScript) plus the FAQ markup search engines read. */
export function Faq({ items = FAQS, id = "faq" }: { items?: { q: string; a: string }[]; id?: string }) {
  const ld = {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: items.map((i) => ({ "@type": "Question", name: tr(i.q), acceptedAnswer: { "@type": "Answer", text: tr(i.a) } })),
  };

  return (
    <section id={id} className="scroll-mt-20 bg-white">
      <div className="mx-auto grid max-w-[1200px] gap-10 px-4 py-16 md:grid-cols-5">
        <div className="md:col-span-2">
          <div className="rounded-[28px] bg-linen p-6">
            <p className="text-xs font-bold uppercase tracking-[0.12em] text-spruce">{tr("Help Center")}</p>
            <div className="mt-4 space-y-3">
              <p className="max-w-[85%] rounded-2xl rounded-ss-sm bg-white px-4 py-3 text-sm text-forest shadow-card">{tr("Is my payment safe?")}</p>
              <p className="ms-auto max-w-[85%] rounded-2xl rounded-se-sm bg-forest px-4 py-3 text-sm text-white">{tr("Yes. You pay through a secure payment page and we never see or store your card details.")}</p>
            </div>
            <div className="mt-5 flex items-center gap-3 rounded-2xl bg-white p-4 shadow-card">
              <span className="grid h-10 w-10 place-items-center rounded-full bg-forest font-black text-lime" aria-hidden="true">?</span>
              <div>
                <p className="text-sm font-bold text-obsidian">{tr("Still need help?")}</p>
                <p className="text-xs text-slate">{tr("Use your account to message the store about an order.")}</p>
              </div>
            </div>
          </div>
        </div>

        <div className="md:col-span-3">
          <h2 className="display display-md">{tr("Frequently asked questions")}</h2>
          <p className="mt-2 text-slate">{tr("Everything you need to know before you start.")}</p>
          <div className="mt-6 divide-y divide-fog rounded-[20px] border border-fog bg-white">
            {items.map((i) => (
              <details key={i.q} className="group p-5">
                <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-obsidian [&::-webkit-details-marker]:hidden">
                  {tr(i.q)}
                  <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full border border-forest text-lg leading-none text-forest transition group-open:rotate-45" aria-hidden="true">+</span>
                </summary>
                <p className="mt-3 text-slate">{tr(i.a)}</p>
              </details>
            ))}
          </div>
        </div>
      </div>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld).replace(/</g, "\\u003c") }} />
    </section>
  );
}
