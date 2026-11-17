import { describe, expect, it } from "vitest";

import { cleanContent, TEMPLATE_FIELDS } from "@/lib/product-content";
import { PAGE_TEMPLATES } from "@/lib/product-templates";

describe("product page content", () => {
  it("every layout asks for something, and only for known fields", () => {
    for (const key of PAGE_TEMPLATES) {
      expect(TEMPLATE_FIELDS[key].length, key).toBeGreaterThan(0);
    }
    expect(Object.keys(TEMPLATE_FIELDS).sort()).toEqual([...PAGE_TEMPLATES].sort());
  });

  it("drops empty rows and texts so clearing a field really clears it", () => {
    expect(cleanContent({ headline: "  ", steps: [{ title: "" }, { title: " Choose " }], faqs: [{ q: "Q", a: "" }, { q: "Q2", a: "A2" }], specs: [{ label: "", value: "x" }], tiers: [{ name: "Basic", text: "" }], free_delivery_from: "" }))
      .toEqual({ steps: [{ title: "Choose" }], faqs: [{ q: "Q2", a: "A2" }], tiers: [{ name: "Basic" }] });
  });

  it("keeps a free-delivery amount as a number", () => {
    expect(cleanContent({ free_delivery_from: "250.5", addon_ids: [3, 4] })).toEqual({ free_delivery_from: 250.5, addon_ids: [3, 4] });
  });
});
