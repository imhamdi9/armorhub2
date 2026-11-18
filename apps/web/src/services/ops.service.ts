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
export interface QuoteRequest { currency: string; id: number; number: string; title: string | null; category: string | null; quantity: number; status: string; notes: string | null; expires_at: string; quotes: Quote[] }
export const getQuoteRequests = async () => (await api.get<QuoteRequest[]>("/quote-requests")).data;
export const createQuoteRequest = async (body: { title: string; quantity: number; notes?: string; vehicle_id?: number; product_id?: number; category?: string }) => (await api.post<QuoteRequest>("/quote-requests", body)).data;
export const closeQuoteRequest = async (id: number) => (await api.post(`/quote-requests/${id}/close`)).data;
export const acceptQuote = async (id: number, quote_id: number, shipping_address: Record<string, string>) => (await api.post<{ order_group_id: number; payable_amount: number; currency: string }>(`/quote-requests/${id}/accept`, { quote_id, shipping_address })).data;

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
export const partialRefundOrder = async (id: number, amount: number, reason: string) => (await api.post<{ partial_refund_status: string; partial_refund_amount: number; currency: string }>(`/admin/orders/${id}/partial-refund`, { amount, reason })).data;
export const sendPartialRefundThrough = async (id: number) => (await api.post<{ reference: string }>(`/admin/orders/${id}/partial-refund-send`)).data;
export const markPartialRefundSent = async (id: number, reference: string) => (await api.post(`/admin/orders/${id}/partial-refund-sent`, { reference })).data;
export const markRefundSent = async (id: number, reference: string) => (await api.post(`/admin/orders/${id}/refund-sent`, { reference })).data;

export const importProducts = async (file: File, dryRun: boolean) => {
  const form = new FormData();
  form.append("file", file);
  form.append("dry_run", dryRun ? "1" : "0");
  return (await api.post<{ message: string; imported: number; rows?: number; errors?: { row: number; messages: string[] }[] }>("/products/import", form, { headers: { "Content-Type": "multipart/form-data" } })).data;
};

export interface RefundState {
  request: { id: number; kind: "cancel" | "return"; status: "requested" | "awaiting_pickup" | "collected" | "approved" | "rejected"; reason: string; note: string | null; created_at: string; return_code?: string | null; pickup_day?: string | null; inspection?: "passed" | "failed" | null; inspection_note?: string | null } | null;
  refund_status: "pending" | "refunded" | null;
  refund_amount: number | null;
  can_request: boolean;
  return_days: number;
}
export const getRefundState = async (orderId: number) => (await api.get<RefundState>(`/orders/${orderId}/refund-request`)).data;
export const requestRefund = async (orderId: number, reason: string, photos: string[] = []) => (await api.post<{ id: number; kind: "cancel" | "return"; status: string }>(`/orders/${orderId}/refund-request`, { reason, photos })).data;
export const uploadRefundPhoto = async (orderId: number, file: File) => {
  const form = new FormData();
  form.append("image", file);
  return (await api.post<{ path: string }>(`/orders/${orderId}/refund-photos`, form, { headers: { "Content-Type": "multipart/form-data" } })).data.path;
};

export interface RefundQueueItem { id: number; order_id: number; product: string | null; amount: number; currency: string | null; kind: string; reason: string; status: string; customer: string | null; created_at: string; pickup_day?: string | null; pickup_note?: string | null; photos?: string[] }
export const getRefundQueue = async () => (await api.get<RefundQueueItem[]>("/admin/refund-requests")).data;
export const approveRefundRequest = async (id: number) => (await api.post(`/admin/refund-requests/${id}/approve`)).data;
export const rejectRefundRequest = async (id: number, note: string) => (await api.post(`/admin/refund-requests/${id}/reject`, { note })).data;

export interface FitmentRow { vehicle_make_id: number | null; vehicle_model_id: number | null; year_from: number | null; year_to: number | null; note: string | null; make?: string | null; model?: string | null }
export const getFitments = async (productId: number) => {
  const p = (await api.get<{ fitments?: FitmentRow[]; fitment_required?: boolean }>(`/products/${productId}`)).data;
  return { rows: p.fitments ?? [], required: !!p.fitment_required };
};
export const saveFitments = async (productId: number, rows: FitmentRow[], required: boolean) =>
  (await api.put(`/products/${productId}/fitments`, { fitment_required: required, rows: rows.map(({ vehicle_make_id, vehicle_model_id, year_from, year_to, note }) => ({ vehicle_make_id, vehicle_model_id, year_from, year_to, note })) })).data;

export interface BookingView { timezone?: string; starts_at: string; ends_at: string; status: string; reschedules: number; can_change: boolean; cancel_reason: string | null }
export interface SlotsView { timezone: string; job_hours: number; days: { date: string; times: string[] }[] }
export const getBooking = async (jobId: number) => (await api.get<{ booking: BookingView | null }>(`/installation-requests/${jobId}/booking`)).data.booking;
export const getSlots = async (jobId: number) => (await api.get<SlotsView>(`/installation-requests/${jobId}/slots`)).data;
export const bookSlot = async (jobId: number, starts_at: string) => (await api.post(`/installation-requests/${jobId}/booking`, { starts_at })).data;
export const rescheduleSlot = async (jobId: number, starts_at: string) => (await api.put(`/installation-requests/${jobId}/booking`, { starts_at })).data;

export interface Address { id: number; label: string | null; name: string; phone: string; line1: string; line2: string | null; city: string; region: string | null; postal_code: string | null; is_default: boolean }
export const getAddresses = async () => (await api.get<Address[]>("/addresses")).data;
export const addAddress = async (body: Partial<Address>) => (await api.post<Address>("/addresses", body)).data;
export const updateAddress = async (id: number, body: Partial<Address>) => (await api.put<Address>(`/addresses/${id}`, body)).data;
export const removeAddress = async (id: number) => (await api.delete(`/addresses/${id}`)).data;
export const saveProfile = async (body: { name?: string; phone?: string | null }) => (await api.put<{ name: string; phone: string | null }>("/me/profile", body)).data;
export const changePassword = async (body: { current_password: string; password: string; password_confirmation: string }) => (await api.put<{ message: string }>("/me/password", body)).data;
/** Everything held about the signed-in customer, as a JSON file the browser can save. */
export const downloadMyData = async () => (await api.get<Blob>("/me/export", { responseType: "blob" })).data;
export const deleteAccount = async (body: { password?: string; confirm?: string }) => (await api.delete("/me", { data: body })).data;
export interface VehicleFit { vehicle_id: number; label: string; is_default: boolean; status: "fits" | "does_not_fit" | "universal" | "unknown" }
export const getFit = async (productId: number) => (await api.get<VehicleFit[]>(`/garage/fit/${productId}`)).data;

export interface FavouriteProduct { id: number; name: string; images: string[]; from_price: number | null; currency: string; seller_id: number | null }
export const getFavourites = async () => (await api.get<FavouriteProduct[]>("/favourites")).data;
export const getFavouriteIds = async () => (await api.get<number[]>("/favourites/ids")).data;
export const addFavourite = async (productId: number) => (await api.post("/favourites", { product_id: productId })).data;
export const removeFavourite = async (productId: number) => (await api.delete(`/favourites/${productId}`)).data;

export interface OrderMessage { id: number; side: "customer" | "seller" | "support"; mine: boolean; kind: "message" | "problem"; body: string; created_at: string }
export const getOrderMessages = async (orderId: number) => (await api.get<OrderMessage[]>(`/orders/${orderId}/messages`)).data;
export const sendOrderMessage = async (orderId: number, body: string, problem = false) => (await api.post<OrderMessage>(`/orders/${orderId}/messages`, { body, ...(problem ? { problem: true } : {}) })).data;

export interface OrderProblem { id: number; order_id: number; product: string | null; customer: string | null; body: string; created_at: string }
export const getOrderProblems = async () => (await api.get<OrderProblem[]>("/admin/order-problems")).data;
export const markProblemHandledWith = async (id: number, note?: string) => (await api.post(`/admin/order-problems/${id}/handled`, { note })).data;
export const giveGoodwill = async (orderId: number, points: number, why: string) => (await api.post<{ points: number; balance: number }>(`/admin/orders/${orderId}/goodwill`, { points, why })).data;
export const markProblemHandled =async (id: number) => (await api.post(`/admin/order-problems/${id}/handled`)).data;

export interface PromoCode { id: number; code: string; type: "percent" | "fixed"; value: number; max_discount: number | null; min_order: number | null; market: string | null; starts_at: string | null; ends_at: string | null; usage_limit: number | null; per_customer_limit: number; is_active: boolean; used: number; given: number }
export const getPromoCodes = async () => (await api.get<PromoCode[]>("/admin/promo-codes")).data;
export const savePromoCode = async (body: Record<string, unknown>, id?: number) => (id ? await api.put<PromoCode>(`/admin/promo-codes/${id}`, body) : await api.post<PromoCode>("/admin/promo-codes", body)).data;

export interface NotificationPrefs { notify_email: boolean; notify_push: boolean }
export const getNotificationPrefs = async () => (await api.get<NotificationPrefs>("/me/notification-preferences")).data;
export const saveNotificationPrefs = async (body: Partial<NotificationPrefs>) => (await api.put<NotificationPrefs>("/me/notification-preferences", body)).data;

export interface SupportMessage { id: number; side: "customer" | "staff"; body: string; created_at: string }
export interface SupportTicket { id: number; subject: string; body: string; status: "open" | "answered" | "closed"; reply: string | null; replied_at: string | null; created_at: string; order_id?: number | null; messages?: SupportMessage[] }
export const getSupportTickets = async () => (await api.get<SupportTicket[]>("/support")).data;
export const sendSupportTicket = async (subject: string, body: string, orderId?: number) => (await api.post<SupportTicket>("/support", { subject, body, ...(orderId ? { order_id: orderId } : {}) })).data;
export const writeSupport = async (id: number, body: string) => (await api.post<SupportTicket>(`/support/${id}/messages`, { body })).data;
export const closeSupportTicket = async (id: number) => (await api.post<SupportTicket>(`/support/${id}/close`)).data;

export interface HelpRequest { id: number; subject: string; body: string; customer: string | null; created_at: string; order_id?: number | null; messages?: SupportMessage[] }
export const getHelpRequests = async () => (await api.get<HelpRequest[]>("/admin/support")).data;
export const answerHelpRequest = async (id: number, reply: string) => (await api.post(`/admin/support/${id}/reply`, { reply })).data;

export const sendRefundThrough = async (orderId: number) => (await api.post<{ refund_status: string; reference: string }>(`/admin/orders/${orderId}/refund-send`)).data;

export interface OutboxRow { id: number; to: string; subject: string; created_at: string }
export interface OutboxMail extends OutboxRow { html: string | null; text: string | null }
export const getOutbox = async () => (await api.get<OutboxRow[]>("/admin/outbox")).data;
export const getOutboxMail = async (id: number) => (await api.get<OutboxMail>(`/admin/outbox/${id}`)).data;

export const scheduleReturnPickup = async (orderId: number, day: string, note?: string) => (await api.post(`/orders/${orderId}/return-pickup`, { day, note })).data;
export const collectReturn = async (id: number, code: string) => (await api.post(`/admin/refund-requests/${id}/collect`, { code })).data;
export const inspectReturn = async (id: number, passed: boolean, note?: string) => (await api.post(`/admin/refund-requests/${id}/inspect`, { passed, note })).data;

export interface ReferralInfo { code: string; link: string; invited: number; rewarded: number; points_each: number }
export const getReferral = async () => (await api.get<ReferralInfo>("/referral")).data;
