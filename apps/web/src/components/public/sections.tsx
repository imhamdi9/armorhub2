import { Icon } from "@/components/public/art";
import { formatNumber, tr } from "@/lib/i18n";

/** A row of real numbers (counts, years, rates) shown as large figures. Zero or missing values are left out. */
export function StatsBand({ items }: { items: { value: number | string | null | undefined; label: string; suffix?: string }[] }) {
  const shown = items.filter((i) => i.value !== null && i.value !== undefined && Number(i.value) !== 0);
  if (shown.length === 0) return null;

  return (
    <section className="bg-white px-4 py-6">
      <dl className="bg-dark mx-auto grid max-w-[1200px] grid-cols-2 gap-6 rounded-[28px] px-6 py-10 text-center md:grid-cols-4">
        {shown.map((i) => (
          <div key={i.label}>
            <dt className="order-2 mt-1 text-sm text-white/75">{tr(i.label)}</dt>
            <dd className="display text-4xl md:text-6xl" style={{ color: "var(--on-dark-accent)" }}>
              {typeof i.value === "number" ? formatNumber(i.value) : i.value}{i.suffix ?? ""}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

const WHY: [string, string, string][] = [
  ["shield", "Warranty on every job", "Completed work is covered by the warranty shown on the product."],
  ["tag", "Clear prices", "See the price before you order. VAT is included where it applies."],
  ["users", "Vetted professionals", "Nearby experts send you quotes; you choose who does the work."],
  ["lock", "Secure payment", "Pay on a secure page. We never see or store your card details."],
];

/** Four reasons to trust the service, with plenty of white space and no card chrome. */
export function WhyUs() {
  return (
    <section className="bg-white py-20">
      <div className="mx-auto max-w-[1200px] px-4">
        <h2 className="display display-md text-center">{tr("Why choose us")}</h2>
        <div className="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
          {WHY.map(([icon, title, text]) => (
            <div key={title}>
              <span className="grid h-12 w-12 place-items-center rounded-full bg-brand-wash text-brand-ink"><Icon name={icon} className="h-6 w-6" /></span>
              <h3 className="mt-5 text-lg font-bold text-obsidian">{tr(title)}</h3>
              <p className="mt-2 text-slate">{tr(text)}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

/** Numbered steps; `id="how"` is the target of the header's "How it works" link. */
export function Steps({ steps, columns = 3 }: { steps: string[][]; columns?: 3 | 4 }) {
  return (
    <section id="how" className="scroll-mt-20 bg-white py-14">
      <div className="mx-auto max-w-[1200px] px-4">
        <h2 className="display display-md text-center">{tr("How it works")}</h2>
        <ol className={`mt-8 grid gap-4 sm:grid-cols-2 ${columns === 4 ? "lg:grid-cols-4" : "md:grid-cols-3"}`}>
          {steps.map(([n, title, text]) => (
            <li key={n} className="hover-lift rounded-[20px] border border-fog bg-white p-6">
              <span className="btn-brand inline-flex h-9 w-9 items-center justify-center text-sm font-black">{formatNumber(Number(n))}</span>
              <h3 className="mt-4 font-bold text-obsidian">{tr(title)}</h3>
              <p className="mt-1 text-sm text-slate">{tr(text)}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}
