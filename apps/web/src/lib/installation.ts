import type { InstallationStatus } from "@/services/installation.service";

/** One plain sentence per status, for each side, and a badge tone. */
export const STATUS: Record<InstallationStatus, { label: string; tone: "default" | "secondary" | "destructive" | "outline" }> = {
  open: { label: "Waiting for offers", tone: "secondary" },
  offers_received: { label: "Offers received", tone: "default" },
  selected: { label: "Offer chosen", tone: "default" },
  awaiting_payment: { label: "Waiting for payment", tone: "secondary" },
  paid: { label: "Paid and held safely", tone: "default" },
  in_progress: { label: "Work in progress", tone: "default" },
  proof_submitted: { label: "Waiting for your confirmation", tone: "secondary" },
  released: { label: "Completed", tone: "default" },
  disputed: { label: "Dispute under review", tone: "destructive" },
  refunded: { label: "Refunded", tone: "outline" },
  cancelled: { label: "Cancelled", tone: "outline" },
  expired: { label: "Expired", tone: "outline" },
};

export const WORK_TYPES = [["install", "Installation"], ["repair", "Repair"], ["maintenance", "Maintenance"]] as const;
export const DISPUTE_TYPES = [["not_done", "The work was not done"], ["defect", "The work has a defect"], ["leak", "There is a leak"], ["maintenance", "It needs maintenance or repair"], ["other", "Something else"]] as const;
export const TIERS: Record<string, string> = { new: "New centre", bronze: "Bronze", silver: "Silver", gold: "Gold" };

/** The phone's own position, after the person allows it. Never typed in. */
export function askLocation(): Promise<{ latitude: number; longitude: number }> {
  return new Promise((resolve, reject) => {
    if (typeof navigator === "undefined" || !navigator.geolocation) return reject(new Error("unsupported"));
    navigator.geolocation.getCurrentPosition((p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude }), (e) => reject(e), { enableHighAccuracy: true, timeout: 15000 });
  });
}
