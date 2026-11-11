import { describe, expect, it } from "vitest";

import { eventLabel, statusLabel } from "@/lib/orders";

describe("statusLabel", () => {
  it("humanises known and unknown statuses", () => {
    expect(statusLabel("service_in_progress")).toBe("In progress");
    expect(statusLabel("brand_new_status")).toBe("brand new status");
  });
});

describe("eventLabel", () => {
  it("describes events, including the appointment time", () => {
    expect(eventLabel({ id: 1, type: "paid", data: null, created_at: "" })).toBe("Payment received");
    const label = eventLabel({
      id: 2,
      type: "scheduled",
      data: { scheduled_at: "2030-01-02T10:00:00Z" },
      created_at: "",
    });
    expect(label.startsWith("Appointment scheduled for ")).toBe(true);
  });
});
