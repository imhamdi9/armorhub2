import { api } from "@/services/api";
import type { Industry } from "@/types/auth";
import type {
  AppNotification,
  CatalogItem,
  CommissionDistribution,
  Earnings,
  Order,
  StaffUser,
  Paginated,
  Partner,
  PartnerQuote,
  PriceQuote,
  PriceRule,
  Product,
  Tenant,
  Warranty,
} from "@/types/platform";

export const getIndustries = async () =>
  (await api.get<Industry[]>("/industries")).data;

export const getProducts = async (params?: Record<string, string | number>) =>
  (await api.get<Product[]>("/products", { params })).data;

export const getProduct = async (id: number | string) =>
  (await api.get<Product & { price_rules: PriceRule[] }>(`/products/${id}`)).data;

export const createProduct = async (data: Partial<Product>) =>
  (await api.post<Product>("/products", data)).data;

export const updateProduct = async (id: number, data: Partial<Product>) =>
  (await api.put<Product>(`/products/${id}`, data)).data;

export const deleteProduct = async (id: number) =>
  (await api.delete(`/products/${id}`)).data;

export const getPriceRules = async (productId: number | string) =>
  (await api.get<PriceRule[]>(`/products/${productId}/price-rules`)).data;

export const savePriceRules = async (
  productId: number | string,
  rules: PriceRule[],
) =>
  (await api.put<PriceRule[]>(`/products/${productId}/price-rules`, { rules }))
    .data;

export const getCatalog = async (alias: string) =>
  (await api.get<CatalogItem[]>(`/catalog/${alias}`)).data;

export const getOrders = async (params?: { status?: string; page?: number; per_page?: number }) =>
  (await api.get<Paginated<Order>>("/orders", { params })).data;

export const getPartners = async (params?: { search?: string; include_inactive?: number }) =>
  (await api.get<Partner[]>("/partners", { params })).data;

export const getTenants = async () =>
  (await api.get<Tenant[]>("/tenants")).data;

export const updateTenant = async (
  id: number,
  data: Partial<Pick<Tenant, "name" | "industry_id">>,
) => (await api.put<Tenant>(`/tenants/${id}`, data)).data;

export const getOrder = async (id: number | string) =>
  (await api.get<Order & { product?: Product }>(`/orders/${id}`)).data;

export interface NewOrder {
  product_id: number;
  subject_type?: string | null;
  subject_id?: number | null;
  target_type: string;
  targets: number[];
}

export const createOrder = async (data: NewOrder) =>
  (await api.post<{ order: Order; partner_quotes: number }>("/orders", data)).data;

export const calculatePrice = async (data: {
  product_id: number;
  targets: number[];
  target_type: string;
}) => (await api.post<PriceQuote>("/pricing/calculate", data)).data;

export const getOrderQuotes = async (orderId: number | string) =>
  (await api.get<PartnerQuote[]>(`/orders/${orderId}/partner-quotes`)).data;

export const acceptQuote = async (quoteId: number) =>
  (await api.patch<{ order: Order }>(`/partner-quotes/${quoteId}/accept`)).data;

export const payOrder = async (orderId: number, amount: number | string) =>
  (await api.post(`/orders/${orderId}/payments`, { amount, payment_method: "card" }))
    .data;

export const getPartnerRequests = async () =>
  (await api.get<PartnerQuote[]>("/partner/orders")).data;

export const submitQuote = async (quoteId: number, price: number) =>
  (await api.patch(`/partner-quotes/${quoteId}`, { price })).data;

export const createPartner = async (data: Partial<Partner>) =>
  (await api.post<Partner>("/partners", data)).data;

export const deletePartner = async (id: number) =>
  (await api.delete(`/partners/${id}`)).data;

export const createCatalogItem = async (
  alias: string,
  data: Record<string, unknown>,
) => (await api.post<CatalogItem>(`/catalog/${alias}`, data)).data;

export const deleteCatalogItem = async (alias: string, id: number) =>
  (await api.delete(`/catalog/${alias}/${id}`)).data;

export const getMyItems = async (alias: string) =>
  (await api.get<Record<string, unknown>[]>(`/my-items/${alias}`)).data;

export const createMyItem = async (
  alias: string,
  data: Record<string, unknown>,
) => (await api.post<Record<string, unknown>>(`/my-items/${alias}`, data)).data;

export const getDistributions = async () =>
  (await api.get<Paginated<CommissionDistribution>>("/commission-distributions")).data;

export const getEarnings = async () =>
  (await api.get<Earnings>("/dashboard/earnings")).data;

export const markDistributionPaid = async (id: number) =>
  (await api.patch(`/commission-distributions/${id}/pay`)).data;

export const updateLocation = async (data: {
  latitude: number;
  longitude: number;
  city?: string;
  country?: string;
}) => (await api.put("/me/location", data)).data;

export const createTenant = async (data: { name: string; industry_id: number }) =>
  (await api.post<Tenant>("/tenants", data)).data;

export interface Company {
  id: number;
  tenant_id: number;
  name: string;
  type: string;
  status: string;
}

export const getCompanies = async () =>
  (await api.get<Company[]>("/companies")).data;

export const createCompany = async (data: {
  tenant_id: number;
  name: string;
  type?: string;
}) => (await api.post<Company>("/companies", data)).data;

export const getUsers = async (search?: string) =>
  (await api.get<Paginated<StaffUser>>("/users", { params: search ? { search } : {} })).data;

export const createUser = async (data: {
  name: string;
  email: string;
  password: string;
  role: string;
  company_id?: number;
  partner_id?: number;
}) => (await api.post<StaffUser>("/users", data)).data;

export const updateUser = async (
  id: number,
  data: Partial<{ name: string; password: string; role: string; is_active: boolean }>,
) => (await api.put<StaffUser>(`/users/${id}`, data)).data;

export const deleteUser = async (id: number) => (await api.delete(`/users/${id}`)).data;

export const cancelOrder = async (id: number, reason?: string) =>
  (await api.post<Order>(`/orders/${id}/cancel`, { reason })).data;

export const getJobs = async () => (await api.get<Order[]>("/partner/jobs")).data;

export const scheduleJob = async (id: number, scheduledAt: string) =>
  (await api.post<Order>(`/orders/${id}/schedule`, { scheduled_at: scheduledAt })).data;

export const startJob = async (id: number) => (await api.post<Order>(`/orders/${id}/start`)).data;

export const completeJob = async (id: number) =>
  (await api.post<Order>(`/orders/${id}/complete`)).data;

export const getWarranties = async () => (await api.get<Warranty[]>("/warranties")).data;

export const getNotifications = async () =>
  (await api.get<{ unread: number; items: AppNotification[] }>("/notifications")).data;

export const getUnreadCount = async () =>
  (await api.get<{ unread: number }>("/notifications/unread-count")).data.unread;

export const markNotificationRead = async (id: string) =>
  (await api.post(`/notifications/${id}/read`)).data;

export const markAllNotificationsRead = async () =>
  (await api.post("/notifications/read-all")).data;

export const updatePartner = async (id: number, data: Partial<Partner> & { is_active?: boolean }) =>
  (await api.put<Partner>(`/partners/${id}`, data)).data;

export const updateCompany = async (id: number, data: Partial<Company>) =>
  (await api.put<Company>(`/companies/${id}`, data)).data;

export const deleteCompany = async (id: number) => (await api.delete(`/companies/${id}`)).data;

export const getCatalogSchema = async (alias: string) =>
  (await api.get<{ alias: string; parents: { field: string; alias: string }[] }>(`/catalog/${alias}/schema`)).data;

export interface DashboardStats {
  role: string;
  cards: { key: string; label: string; value: number; format: "number" | "money" }[];
  orders_by_status: Record<string, number>;
}

export const getDashboardStats = async () =>
  (await api.get<DashboardStats>("/dashboard/stats")).data;
