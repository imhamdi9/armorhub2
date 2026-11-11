import { api } from "@/services/api";
import type { Industry } from "@/types/auth";
import type {
  CatalogItem,
  CommissionDistribution,
  Earnings,
  Order,
  Paginated,
  Partner,
  PartnerQuote,
  PriceQuote,
  PriceRule,
  Product,
  Tenant,
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

export const getOrders = async () => (await api.get<Order[]>("/orders")).data;

export const getPartners = async () =>
  (await api.get<Partner[]>("/partners")).data;

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
