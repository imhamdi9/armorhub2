import { ComplianceFooter, LegalLink, MARK_GLYPHS } from "@/components/layout/compliance-footer";

// SAMPLE COPY. The firm, reference number, address and protection limits below are placeholders for layout only. A regulated firm must
// replace them with wording approved by its compliance team: nothing here is legal or regulatory text.
export default function ComplianceFooterPreview() {
  return (
    <div className="flex min-h-screen flex-col bg-neutral-50">
      <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-16 sm:px-6">
        <h1 className="text-2xl font-semibold tracking-tight text-neutral-900">Page content</h1>
      </main>
      <ComplianceFooter
        columns={[
          { title: "Accounts", links: [{ label: "Current account", href: "#" }, { label: "Savings", href: "#" }, { label: "Fixed-term deposits", href: "#" }, { label: "Business accounts", href: "#" }] },
          { title: "Tools", links: [{ label: "Budget planner", href: "#" }, { label: "Currency converter", href: "#" }, { label: "Interest calculator", href: "#" }, { label: "Mobile app", href: "#" }] },
          { title: "Company", links: [{ label: "About us", href: "#" }, { label: "Careers", href: "#" }, { label: "Press", href: "#" }, { label: "Sustainability", href: "#" }] },
          { title: "Support", links: [{ label: "Help centre", href: "#" }, { label: "Contact us", href: "#" }, { label: "Complaints", href: "#" }, { label: "Report fraud", href: "#" }] },
        ]}
        notice={{
          heading: "Important information",
          paragraphs: [
            <>Meridian Capital Ltd is authorised and regulated by the national financial regulator under firm reference number 000000. You can check this on the <LegalLink href="#register">regulator&rsquo;s public register</LegalLink>.</>,
            <>Capital at risk. The value of investments can fall as well as rise, and you may get back less than you invest. Past performance is not a reliable guide to future results. Only invest money you can afford to lose.</>,
            <>Eligible deposits with Meridian Capital Ltd are protected up to the applicable limit per person, per authorised firm, by the national deposit guarantee scheme. Read the <LegalLink href="#deposit-sheet">deposit protection information sheet</LegalLink> for what is covered and for exclusions.</>,
          ],
        }}
        marks={[
          { glyph: MARK_GLYPHS.registration, label: "Regulator registered · FRN 000000", href: "#register" },
          { glyph: MARK_GLYPHS.deposit, label: "Deposit guarantee scheme", href: "#deposit-sheet" },
          { glyph: MARK_GLYPHS.accessibility, label: "Accessibility statement", href: "#accessibility" },
        ]}
        company="Meridian Capital Ltd"
        registeredOffice="14 Harbour Row, Example Quay, EX1 2AB. Registered in England and Wales, company number 00000000."
      />
    </div>
  );
}
