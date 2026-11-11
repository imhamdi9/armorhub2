import { api } from "@/services/api";
import type { Industry } from "@/types/auth";
import type {
  CatalogItem,
  Order,
  Partner,
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
