import { Icon } from "@/components/public/art";
import { formatNumber, tr } from "@/lib/i18n";

/** A row of real numbers (counts, years, rates) shown as large figures. Zero or missing values are left out. */
export function StatsBand({ items }: { items: { value: number | string | null | undefined; label: string; suffix?: string }[] }) {
  const shown = items.filter((i) => i.value !== null && i.value !== undefined && Number(i.value) !== 0);
  if (shown.length === 0) return null;

  return (
    <section className="border-y" style={{ backgroundColor: "color-mix(in srgb, var(--brand, #111827) 6%, white)" }}>
      <dl className="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-4 py-8 text-center md:grid-cols-4">
        {shown.map((i) => (
          <div key={i.label}>
            <dt className="order-2 mt-1 text-sm text-gray-600">{tr(i.label)}</dt>
            <dd className="text-3xl font-extrabold tracking-tight md:text-4xl" style={{ color: "var(--brand, #111827)" }}>
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

/** Four reasons to trust the service, as cards with icons. */
export function WhyUs() {
  return (
    <section className="mx-auto max-w-6xl px-4 py-14">
      <h2 className="text-center text-3xl font-bold">{tr("Why choose us")}</h2>
      <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {WHY.map(([icon, title, text]) => (
          <div key={title} className="rounded-2xl border bg-white p-6 transition hover:shadow-md">
            <span className="grid h-11 w-11 place-items-center rounded-xl" style={{ backgroundColor: "color-mix(in srgb, var(--brand, #111827) 12%, white)", color: "var(--brand, #111827)" }}>
              <Icon name={icon} />
            </span>
            <h3 className="mt-4 font-semibold">{tr(title)}</h3>
            <p className="mt-1 text-sm text-gray-600">{tr(text)}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

/** Numbered steps; `id="how"` is the target of the header's "How it works" link. */
export function Steps({ steps, columns = 3 }: { steps: string[][]; columns?: 3 | 4 }) {
  return (
    <section id="how" className="scroll-mt-20 bg-gray-50">
      <div className="mx-auto max-w-6xl px-4 py-14">
        <h2 className="text-center text-3xl font-bold">{tr("How it works")}</h2>
        <ol className={`mt-8 grid gap-4 sm:grid-cols-2 ${columns === 4 ? "lg:grid-cols-4" : "md:grid-cols-3"}`}>
          {steps.map(([n, title, text]) => (
            <li key={n} className="relative rounded-2xl bg-white p-6 shadow-sm">
              <span className="btn-brand inline-flex h-9 w-9 items-center justify-center rounded-full text-sm font-bold">{formatNumber(Number(n))}</span>
              <h3 className="mt-4 font-semibold">{tr(title)}</h3>
              <p className="mt-1 text-sm text-gray-600">{tr(text)}</p>
            </li>
          ))}
        </ol>
      </div>
    </section>
  );
}

/** A closing call to action in the brand colour. */
export function CallToAction({ title, text, href, label }: { title: string; text: string; href: string; label: string }) {
  return (
    <section className="px-4 pb-14">
      <div className="btn-brand mx-auto max-w-6xl rounded-3xl px-6 py-12 text-center">
        <h2 className="text-2xl font-bold md:text-3xl">{tr(title)}</h2>
        <p className="mx-auto mt-2 max-w-xl opacity-90">{tr(text)}</p>
        <a href={href} className="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-gray-900 hover:bg-gray-100">{tr(label)}</a>
      </div>
    </section>
  );
}
