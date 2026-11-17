import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it, vi } from "vitest";

import { PageContentFields } from "@/components/products/page-content-fields";
import { TEMPLATE_FIELDS } from "@/lib/product-content";
import { PAGE_TEMPLATES } from "@/lib/product-templates";

vi.mock("@/services/platform.service", () => ({ getProducts: () => Promise.resolve([]) }));

describe("choosing a layout opens its own fields", () => {
  it("shows exactly the fields each layout asks for", () => {
    const all = ["headline", "steps", "faqs", "specs", "tiers", "addon_ids", "free_delivery_from", "warranty_terms", "lead_time", "quote_note"] as const;

    for (const template of PAGE_TEMPLATES) {
      const html = renderToStaticMarkup(<PageContentFields template={template} value={{}} onChange={() => undefined} />);
      for (const field of all) {
        const shown = html.includes(`data-testid="field-${field}"`);
        expect(shown, `${template} / ${field}`).toBe(TEMPLATE_FIELDS[template].includes(field));
      }
    }
  });

  it("starts with the rows already saved", () => {
    const html = renderToStaticMarkup(<PageContentFields template="story" value={{ headline: "Built to last", steps: [{ title: "Choose" }] }} onChange={() => undefined} />);
    expect(html).toContain("Built to last");
    expect(html).toContain("Choose");
  });
});
