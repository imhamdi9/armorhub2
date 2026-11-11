import { afterEach, describe, expect, it } from "vitest";

import { applyLocale, formatNumber, tr } from "@/lib/i18n";

afterEach(() => applyLocale("en"));

describe("tr", () => {
  it("returns the English text and fills placeholders by default", () => {
    expect(tr("Dashboard")).toBe("Dashboard");
    expect(tr("Order #{id}", { id: 7 })).toBe("Order #7");
  });

  it("leaves unknown placeholders visible instead of failing", () => {
    expect(tr("Hello {name}", {})).toBe("Hello {name}");
  });

  it("translates to Arabic, with placeholders", () => {
    applyLocale("ar");
    expect(tr("Dashboard")).toBe("لوحة التحكم");
    expect(tr("Order #{id}", { id: 7 })).toBe("الطلب رقم 7");
    expect(tr("Welcome, {name}", { name: "منى" })).toBe("مرحبًا، منى");
  });

  it("falls back to English for text that has no translation", () => {
    applyLocale("ar");
    expect(tr("A sentence nobody translated")).toBe("A sentence nobody translated");
  });

  it("formats numbers for the active locale", () => {
    expect(formatNumber(1234.5, { minimumFractionDigits: 2 })).toBe("1,234.50");
    applyLocale("ar");
    expect(formatNumber(1234.5, { minimumFractionDigits: 2 })).not.toBe("1,234.50");
  });
});
