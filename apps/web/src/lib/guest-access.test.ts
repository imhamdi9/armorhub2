import { describe, expect, it } from "vitest";

import { guestAccessTarget } from "./guest-access";

describe("guestAccessTarget", () => {
  it("keeps a page of this site", () => {
    expect(guestAccessTarget("/dashboard/orders/7")).toBe("/dashboard/orders/7");
  });

  it("falls back to the orders for anything that could leave the site", () => {
    for (const bad of [null, "", "https://evil.example", "//evil.example", "/\\evil.example", "/\t/evil.example", "javascript:alert(1)"]) {
      expect(guestAccessTarget(bad)).toBe("/dashboard/orders");
    }
  });
});
