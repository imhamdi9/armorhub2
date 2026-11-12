import { describe, expect, it } from "vitest";

import { nextSteps, statusGuide } from "@/lib/next-steps";
import type { DashboardStats } from "@/services/platform.service";
import type { User } from "@/types/auth";

const user = (roles: string[], extra: Partial<User> = {}): User => ({ id: 1, name: "T", email: "t@example.com", roles, ...extra });
const stats = (cards: Record<string, number>, byStatus: Record<string, number> = {}): DashboardStats => ({
  role: "x", cards: Object.entries(cards).map(([key, value]) => ({ key, label: key, value, format: "number" as const })), orders_by_status: byStatus,
});
const ids = (u: User, s: DashboardStats) => nextSteps(u, s).map((x) => x.id);

describe("nextSteps", () => {
  it("sends a new customer to their first order, and a customer with quotes to compare them", () => {
    expect(ids(user(["customer"]), stats({ orders: 0 }))).toEqual(["first-order"]);
    const withQuotes = nextSteps(user(["customer"]), stats({ orders: 2 }, { quotes_received: 2 }));
    expect(withQuotes.map((s) => s.id)).toEqual(["quotes"]);
    expect(withQuotes[0].href).toBe("/dashboard/orders?status=quotes_received");
  });

  it("is empty for a customer with nothing waiting, so the screen can say they are caught up", () => {
    expect(nextSteps(user(["customer"]), stats({ orders: 3 }, { completed: 3 }))).toEqual([]);
  });

  it("asks a seller to add a product, then to ship what is paid", () => {
    expect(ids(user(["seller"]), stats({ products: 0, to_ship: 0 }))).toEqual(["first-product"]);
    expect(ids(user(["seller"]), stats({ products: 4, to_ship: 2 }))).toEqual(["ship"]);
  });

  it("shows a partner manager waiting quotes, jobs to schedule and a missing location", () => {
    expect(ids(user(["partner-manager"]), stats({ open_requests: 3, to_schedule: 1 }))).toEqual(["quotes", "schedule", "location"]);
    expect(ids(user(["partner-manager"], { latitude: 30.1 }), stats({ open_requests: 0, to_schedule: 0 }))).toEqual([]);
  });

  it("does not show technicians quote requests or location chores", () => {
    expect(ids(user(["technician"]), stats({ open_requests: 5, to_schedule: 2 }))).toEqual(["schedule"]);
  });

  it("gives an owner a team and storefront nudge, and an admin one place to look", () => {
    expect(ids(user(["company-owner"]), stats({ open: 0, team: 1 }))).toEqual(["team", "storefront"]);
    expect(ids(user(["platform-admin"]), stats({}))).toEqual(["admin"]);
  });
});

describe("statusGuide", () => {
  it("explains every order status in plain words, differently for customers and staff", () => {
    for (const s of ["pending_quotes", "quotes_received", "partner_selected", "awaiting_payment", "paid", "appointment_scheduled", "service_in_progress", "completed", "cancelled"]) {
      expect(statusGuide(s, true)).toBeTruthy();
      expect(statusGuide(s, false)).toBeTruthy();
      expect(statusGuide(s, true)).not.toBe(statusGuide(s, false));
    }
    expect(statusGuide("something_new", true)).toBeNull();
  });
});
