import { describe, expect, it } from "vitest";

import { aliasLabel, aliasSingular, buildNavigation, hasRole } from "@/lib/navigation";
import type { User } from "@/types/auth";

const industry = {
  id: 1,
  key: "home-services",
  name: "Home Services",
  subjects: ["property"],
  targets: ["room_type"],
};

const user = (roles: string[], withIndustry = true): User => ({
  id: 1,
  name: "U",
  email: "u@example.com",
  roles,
  industry: withIndustry ? industry : null,
});

const titles = (u: User | null) => buildNavigation(u).map((i) => i.title);

describe("aliasLabel", () => {
  it("turns a morph alias into a plural label", () => {
    expect(aliasLabel("vehicle_part")).toBe("Vehicle Parts");
    expect(aliasLabel("room_type")).toBe("Room Types");
    expect(aliasLabel("device_model")).toBe("Device Models");
    expect(aliasLabel("property")).toBe("Properties");
    expect(aliasLabel("treatment_area")).toBe("Treatment Areas");
    expect(aliasLabel("box")).toBe("Boxes");
  });

  it("does not double an existing plural", () => {
    expect(aliasLabel("services")).toBe("Services");
  });
});

describe("aliasSingular", () => {
  it("keeps the word singular", () => {
    expect(aliasSingular("property")).toBe("Property");
    expect(aliasSingular("vehicle_model")).toBe("Vehicle Model");
  });
});

describe("hasRole", () => {
  it("is false without a user or roles", () => {
    expect(hasRole(null, "customer")).toBe(false);
    expect(hasRole({ id: 1, name: "", email: "" }, "customer")).toBe(false);
  });

  it("matches any of the given roles", () => {
    expect(hasRole(user(["customer"]), "platform-admin", "customer")).toBe(true);
  });
});

describe("buildNavigation", () => {
  it("shows a customer their own items, not catalogs or admin screens", () => {
    const nav = titles(user(["customer"]));
    expect(nav).toContain("My Properties");
    expect(nav).not.toContain("Room Types");
    expect(nav).not.toContain("Tenants");
    expect(nav).not.toContain("Commissions");
  });

  it("gives partners a Requests inbox and commissions", () => {
    const nav = titles(user(["partner-manager"]));
    expect(nav).toContain("Requests");
    expect(nav).toContain("Partners");
    expect(nav).toContain("Commissions");
  });

  it("gives technicians only their jobs, and customers their warranties", () => {
    expect(titles(user(["technician"]))).toEqual(["Dashboard", "Jobs"]);
    expect(titles(user(["customer"]))).toContain("Warranties");
    expect(titles(user(["partner-manager"]))).toContain("Jobs");
  });

  it("gives platform admins catalogs and tenant management", () => {
    const nav = titles(user(["platform-admin"]));
    expect(nav).toContain("Catalogs");
    expect(nav).not.toContain("Room Types"); // listed on the Catalogs page instead
    expect(nav).toContain("Tenants");
    expect(nav).toContain("Companies");
  });

  it("gives company owners a Storefront editor, and admins reach it from Companies", () => {
    expect(titles(user(["company-owner"]))).toContain("Storefront");
    expect(titles(user(["platform-admin"]))).not.toContain("Storefront");
    expect(titles(user(["customer"]))).not.toContain("Storefront");
  });

  it("puts the control panel in its own Administration section, for platform admins only", () => {
    const admin = buildNavigation(user(["platform-admin"]));
    expect(admin.filter((i) => i.section === "admin").map((i) => i.title)).toEqual([
      "Overview", "Settings", "Payments", "Shipping", "Security", "Audit log",
    ]);
    expect(admin.filter((i) => i.section === "admin").every((i) => i.href.startsWith("/dashboard/admin"))).toBe(true);

    for (const role of ["company-owner", "partner-manager", "technician", "customer"]) {
      expect(buildNavigation(user([role])).some((i) => i.section === "admin"), role).toBe(false);
    }
  });

  it("lists a company owner's industry catalogs directly", () => {
    const auto = user(["company-owner"]);
    auto.industry = { ...industry, targets: ["vehicle_part"], subjects: ["vehicle_model"], catalogs: ["vehicle_part", "vehicle_make"] };
    expect(titles(auto)).toContain("Vehicle Makes");
    expect(titles(auto)).toContain("Vehicle Parts");
    expect(titles(auto)).not.toContain("Room Types");
  });

  it("still works without an industry or a user", () => {
    expect(titles(user(["customer"], false))).toEqual(["Dashboard", "Orders", "Products", "Warranties"]);
    expect(titles(null)).toEqual(["Dashboard", "Orders", "Products"]);
  });
});
