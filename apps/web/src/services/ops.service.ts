import { api } from "@/services/api";

export interface Vehicle { id: number; vehicle_model_id: number; make: string | null; model: string | null; year: number; label: string; nickname: string | null; color: string | null; vin: string | null; is_default: boolean }
export interface MakeOrModel { id: number; name: string; vehicle_make_id?: number }

export const getGarage = async () => (await api.get<Vehicle[]>("/garage")).data;
export const addVehicle = async (body: { vehicle_model_id: number; year: number; nickname?: string; vin?: string; color?: string }) => (await api.post<Vehicle>("/garage", body)).data;
export const updateVehicle = async (id: number, body: Record<string, unknown>) => (await api.put<Vehicle>(`/garage/${id}`, body)).data;
export const removeVehicle = async (id: number) => (await api.delete(`/garage/${id}`)).data;
export const getMakes = async () => (await api.get<MakeOrModel[]>("/catalog/vehicle_make")).data;
export const getModels = async () => (await api.get<MakeOrModel[]>("/catalog/vehicle_model")).data;

export interface CentreSettings {
  service_radius_km: number; daily_capacity: number; bays: number; job_hours: number; min_job_price: number | null; accepts_requests: boolean; work_types: string[]; timezone: string;
  hours: { weekday: number; opens: string; closes: string }[]; time_off: { id: number; date: string; reason: string | null }[];
}
export interface CalendarItem { id: number; job_id: number | null; starts_at: string; ends_at: string; subject: string | null; parts: string[] | null; work_type: string | null; customer: string | null }

export const getCentreSettings = async () => (await api.get<CentreSettings>("/partner/settings")).data;
export const saveCentreSettings = async (body: Partial<CentreSettings>) => (await api.put<CentreSettings>("/partner/settings", body)).data;
export const saveHours = async (days: CentreSettings["hours"]) => (await api.put<CentreSettings>("/partner/hours", { days })).data;
export const addTimeOff = async (date: string, reason?: string) => (await api.post<CentreSettings>("/partner/time-off", { date, reason })).data;
export const removeTimeOff = async (id: number) => (await api.delete<CentreSettings>(`/partner/time-off/${id}`)).data;
export const getCalendar = async (from: string, to: string) => (await api.get<CalendarItem[]>("/partner/calendar", { params: { from, to } })).data;

export interface Material { id: number; name: string; quantity: number; unit: string | null; batch: string | null }
export interface WorkOrder {
  job_id: number; status: string; subject: string; work_type: string; parts: string[]; notes: string | null; technician: { id: number; name: string } | null;
  booking: { starts_at: string; ends_at: string } | null; started_at: string | null; materials: Material[]; can_edit_materials: boolean;
}
export const getWorkOrder = async (id: number) => (await api.get<WorkOrder>(`/partner/installation-jobs/${id}/work-order`)).data;
export const getTechnicians = async () => (await api.get<{ id: number; name: string; open_jobs: number }[]>("/partner/technicians")).data;
export const assignTechnician = async (id: number, technician_id: number | null) => (await api.post(`/partner/installation-jobs/${id}/assign`, { technician_id })).data;
export const addMaterial = async (id: number, body: { name: string; quantity: number; unit?: string; batch?: string }) => (await api.post(`/partner/installation-jobs/${id}/materials`, body)).data;
export const removeMaterial = async (id: number, materialId: number) => (await api.delete(`/partner/installation-jobs/${id}/materials/${materialId}`)).data;

export interface Quote { id: number; number: string; unit_price: number; total: number; lead_days: number | null; condition: string | null; warranty_days: number | null; note: string | null; valid_until: string; expired: boolean; status: string; seller: string }
export interface QuoteRequest { id: number; number: string; title: string | null; category: string | null; quantity: number; status: string; notes: string | null; expires_at: string; quotes: Quote[] }
export const getQuoteRequests = async () => (await api.get<QuoteRequest[]>("/quote-requests")).data;
export const createQuoteRequest = async (body: { title: string; quantity: number; notes?: string; vehicle_id?: number }) => (await api.post<QuoteRequest>("/quote-requests", body)).data;
export const closeQuoteRequest = async (id: number) => (await api.post(`/quote-requests/${id}/close`)).data;
export const acceptQuote = async (id: number, quote_id: number, shipping_address: Record<string, string>) => (await api.post(`/quote-requests/${id}/accept`, { quote_id, shipping_address })).data;

export interface SellerRequest { id: number; number: string; title: string | null; category: string | null; quantity: number; notes: string | null; expires_at: string; my_quote: { number: string; unit_price: number; status: string; valid_until: string } | null }
export const getSellerRequests = async () => (await api.get<SellerRequest[]>("/seller/quote-requests")).data;
export const sendSellerQuote = async (id: number, body: { unit_price: number; lead_days?: number; condition?: string; warranty_days?: number; note?: string }) => (await api.post(`/seller/quote-requests/${id}/quote`, body)).data;
export const withdrawSellerQuote = async (id: number) => (await api.delete(`/seller/quote-requests/${id}/quote`)).data;

export interface Zone { id: number; market: string; key: string; name_en: string; name_ar: string | null; city: string | null; latitude: number; longitude: number; radius_km: number; is_active: boolean; centres: number }
export const getZones = async () => (await api.get<Zone[]>("/admin/zones")).data;
export const saveZone = async (body: Partial<Zone> & { market?: string }, id?: number) => (id ? await api.put<Zone>(`/admin/zones/${id}`, body) : await api.post<Zone>("/admin/zones", body)).data;
export const deleteZone = async (id: number) => (await api.delete(`/admin/zones/${id}`)).data;
export const getDemand = async () => (await api.get<{ market: string; city: string; customers: number }[]>("/admin/zones/demand")).data;

export const refundOrder = async (id: number, reason: string) => (await api.post<{ refund_status: string; refund_amount: number; currency: string }>(`/admin/orders/${id}/refund`, { reason })).data;
export const markRefundSent = async (id: number, reference: string) => (await api.post(`/admin/orders/${id}/refund-sent`, { reference })).data;

export const importProducts = async (file: File, dryRun: boolean) => {
  const form = new FormData();
  form.append("file", file);
  form.append("dry_run", dryRun ? "1" : "0");
  return (await api.post<{ message: string; imported: number; rows?: number; errors?: { row: number; messages: string[] }[] }>("/products/import", form, { headers: { "Content-Type": "multipart/form-data" } })).data;
};
