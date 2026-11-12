import { api } from "@/services/api";

export type InstallationStatus =
  | "open" | "offers_received" | "selected" | "awaiting_payment" | "paid" | "in_progress" | "proof_submitted" | "released" | "disputed" | "refunded" | "cancelled" | "expired";

export interface Proof { id: number; side: "centre" | "customer"; kind: string; text: string | null; taken_at: string | null; flagged: boolean; distance_km: number | null; url: string | null }
export interface DisputeInfo { id: number; side: string; type: string; description: string; status: string; decision: string | null; refund_amount: number | null; decision_note: string | null }
export interface Call { number: string; extension: string; expires_at: string | null }
export interface Offer {
  id: number; rank: number; centre: string; tier: string; selected: boolean; rating: number | null; rating_count: number; completed_jobs: number; avg_response_minutes: number | null;
  distance_km: number; same_city: boolean; price: number; eta_days: number; note: string | null; badges: string[];
}
export interface CustomerJob {
  id: number; subject: string; parts: string[]; work_type: string; notes: string | null; status: InstallationStatus; currency: string | null; created_at: string; expires_at: string | null;
  order_id?: number | null; product?: { id: number; name: string; installation: "none" | "optional" | "required" } | null;
  price: number | null; discount: number | null; pay_amount: number | null; points_used: number; offer_count: number; offers?: Offer[];
  objection_deadline: string | null; warranty_ends_at: string | null; terms: { version: string; lines: string[] }; proofs: Proof[]; dispute: DisputeInfo | null;
  refund: { amount: number; status: string; reference: string | null } | null;
  can_confirm: boolean; can_dispute: boolean; can_cancel: boolean; can_review: boolean; review: { rating: number; comment: string | null } | null;
  centre?: { code: string; tier: string; rating: number | null; rating_count: number; completed_jobs: number; name?: string; address?: string | null; city?: string | null; maps_url?: string | null; call?: Call | null };
}
export interface OpenRequest { id: number; subject: string; parts: string[]; work_type: string; notes: string | null; area: string | null; distance_km: number; created_at: string; my_offer: { price: number; eta_days: number; note: string | null; status: string } | null }
export interface CentreJob {
  id: number; subject: string; parts: string[]; work_type: string; notes: string | null; status: InstallationStatus; currency: string | null; area: string | null;
  price: number; fee_percent: number; fee_amount: number; you_earn: number; money: string | null; objection_deadline: string | null; proofs: Proof[]; dispute: DisputeInfo | null;
  can_start: boolean; can_add_proof: boolean; can_dispute: boolean; terms: { version: string; lines: string[] };
  customer?: { name: string; city: string | null; maps_url: string; call: Call | null };
}
export interface Balance { held: number; available: number; pending_withdrawal: number; withdrawn: number }
export interface Wallet {
  balances: Record<string, Balance>; min_withdrawal: number;
  withdrawals: { id: number; amount: number; currency: string; method: string; account_name: string; account_last4: string; bank_name: string | null; status: string; reference: string | null; note: string | null; created_at: string }[];
}
export interface AdminDispute {
  id: number; status: string; side: string; type: string; description: string; decision: string | null; refund_amount: number | null; decision_note: string | null; created_at: string;
  job: { id: number; subject: string; parts: string[]; status: string; price: number; pay_amount: number; currency: string; customer: string | null; centre: string | null; refund_status: string | null; refund_reference: string | null };
  evidence: { id: number; side: string; kind: string; text: string | null; flagged: boolean; distance_km: number | null; taken_at: string | null; url: string | null }[];
}

export const getInstallations = async () => (await api.get<CustomerJob[]>("/installation-requests")).data;
export const getInstallation = async (id: number | string) => (await api.get<CustomerJob>(`/installation-requests/${id}`)).data;
export const createInstallation = async (body: Record<string, unknown>) => (await api.post<CustomerJob>("/installation-requests", body)).data;
export const cancelInstallation = async (id: number) => (await api.post<CustomerJob>(`/installation-requests/${id}/cancel`)).data;
export const acceptOffer = async (id: number, body: { offer_id: number; accept_terms: boolean; use_points?: number }) => (await api.post<CustomerJob>(`/installation-requests/${id}/accept`, body)).data;
export const payInstallation = async (id: number) => (await api.post<{ status: string; checkout_url?: string; request: CustomerJob }>(`/installation-requests/${id}/pay`)).data;
export const confirmInstallation = async (id: number) => (await api.post<CustomerJob>(`/installation-requests/${id}/confirm`)).data;
export const reviewInstallation = async (id: number, body: { rating: number; comment?: string }) => (await api.post<CustomerJob>(`/installation-requests/${id}/review`, body)).data;
export const disputeInstallation = async (id: number, body: { type: string; description: string }) => (await api.post<CustomerJob>(`/installation-requests/${id}/disputes`, body)).data;
export const reportContact = async (id: number, note: string) => (await api.post(`/installation-requests/${id}/report-contact`, { note })).data;
export const getLoyalty = async () => (await api.get<{ points: number; point_value: number; history: { points: number; reason: string; created_at: string }[] }>("/loyalty")).data;

export const getOpenRequests = async () => (await api.get<OpenRequest[]>("/partner/installation-requests")).data;
export const sendOffer = async (id: number, body: { price: number; eta_days: number; note?: string }) => (await api.post(`/partner/installation-requests/${id}/offer`, body)).data;
export const getCentreJobs = async () => (await api.get<CentreJob[]>("/partner/installation-jobs")).data;
export const getCentreJob = async (id: number | string) => (await api.get<CentreJob>(`/partner/installation-jobs/${id}`)).data;
export const startCentreJob = async (id: number) => (await api.post<CentreJob>(`/partner/installation-jobs/${id}/start`)).data;
export const disputeCentreJob = async (id: number, body: { type: string; description: string }) => (await api.post<CentreJob>(`/partner/installation-jobs/${id}/disputes`, body)).data;
export const sendProof = async (id: number, form: FormData) => (await api.post<CentreJob>(`/partner/installation-jobs/${id}/proof`, form, { headers: { "Content-Type": "multipart/form-data" } })).data;
export const sendEvidence = async (path: string, form: FormData) => (await api.post(path, form, { headers: { "Content-Type": "multipart/form-data" } })).data;
export const getWallet = async () => (await api.get<Wallet>("/partner/wallet")).data;
export const withdraw = async (body: Record<string, unknown>) => (await api.post("/partner/wallet/withdrawals", body)).data;

export const getAdminDisputes = async () => (await api.get<AdminDispute[]>("/admin/installation-disputes")).data;
export const decideDispute = async (id: number, body: { decision: string; refund_amount?: number; note: string }) => (await api.post(`/admin/installation-disputes/${id}/decide`, body)).data;
export const markRefundPaid = async (jobId: number, reference: string) => (await api.post(`/admin/installation-requests/${jobId}/refund-paid`, { reference })).data;
export const getAdminWithdrawals = async () => (await api.get<{ id: number; company: string; amount: number; currency: string; method: string; account_name: string; account_number: string; bank_name: string | null; status: string; reference: string | null; note: string | null; created_at: string }[]>("/admin/centre-withdrawals")).data;
export const payWithdrawal = async (id: number, reference: string) => (await api.post(`/admin/centre-withdrawals/${id}/pay`, { reference })).data;
export const rejectWithdrawal = async (id: number, note: string) => (await api.post(`/admin/centre-withdrawals/${id}/reject`, { note })).data;
export const getViolations = async () => (await api.get<{ id: number; request_id: number; centre: string; strikes: number; note: string | null; status: string }[]>("/admin/installation-violations")).data;
export const decideViolation = async (id: number, decision: "confirmed" | "dismissed") => (await api.post(`/admin/installation-violations/${id}/decide`, { decision })).data;
