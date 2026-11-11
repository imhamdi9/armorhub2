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
    <section id={id} className="mx-auto max-w-3xl scroll-mt-20 px-4 py-14">
      <h2 className="text-center text-3xl font-bold">{tr("Frequently asked questions")}</h2>
      <p className="mx-auto mt-2 max-w-xl text-center text-gray-600">{tr("Everything you need to know before you start.")}</p>
      <div className="mt-8 divide-y rounded-2xl border bg-white">
        {items.map((i) => (
          <details key={i.q} className="group p-5">
            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-medium [&::-webkit-details-marker]:hidden">
              {tr(i.q)}
              <span className="grid h-6 w-6 shrink-0 place-items-center rounded-full border text-lg leading-none transition group-open:rotate-45" aria-hidden="true">+</span>
            </summary>
            <p className="mt-3 text-gray-600">{tr(i.a)}</p>
          </details>
        ))}
      </div>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld).replace(/</g, "\\u003c") }} />
    </section>
  );
}
