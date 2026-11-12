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
  PartnerApplication,
  OrderShipping,
  Shipment,
  ShippingAddress,
  ShippingChoice,
  ShippingRate,
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
  data: Partial<Pick<Tenant, "name" | "industry_id" | "market">>,
) => (await api.put<Tenant>(`/tenants/${id}`, data)).data;

export const getOrder = async (id: number | string) =>
  (await api.get<Order & { product?: Product }>(`/orders/${id}`)).data;

export interface NewOrder {
  product_id: number;
  subject_type?: string | null;
  subject_id?: number | null;
  target_type: string;
  targets: number[];
  shipping_address?: ShippingAddress;
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

/** Instant gateways answer "Payment completed"; hosted ones return a `checkout_url` to send the customer to. */
export const payOrder = async (orderId: number, amount: number | string) =>
  (await api.post<{ message: string; checkout_url?: string }>(`/orders/${orderId}/payments`, {
    amount,
    payment_method: "card",
  })).data;

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

export const createTenant = async (data: { name: string; industry_id: number; market?: string | null }) =>
  (await api.post<Tenant>("/tenants", data)).data;

export interface Company {
  id: number;
  tenant_id: number;
  name: string;
  slug?: string | null;
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

export interface StorefrontSettings {
  slug: string;
  path: string;
  enabled: boolean;
  headline: string | null;
  subheadline: string | null;
  about: string | null;
  primary_color: string;
  logo_url: string | null;
  hero_image_url: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  featured_product_ids: number[];
}

export const getStorefront = async (companyId: number) =>
  (await api.get<StorefrontSettings>(`/companies/${companyId}/storefront`)).data;

export const saveStorefront = async (companyId: number, data: Partial<StorefrontSettings>) =>
  (await api.put<StorefrontSettings>(`/companies/${companyId}/storefront`, data)).data;

// ---- Shipping
export const getOrderShipping = async (orderId: number | string) =>
  (await api.get<OrderShipping>(`/orders/${orderId}/shipments`)).data;

export const getShippingChoices = async () => (await api.get<ShippingChoice>("/shipping-providers")).data;

export const getShippingRates = async (orderId: number, provider: string) =>
  (await api.post<{ rates: ShippingRate[] }>(`/orders/${orderId}/shipping-rates`, { provider })).data.rates;

export const bookShipment = async (
  orderId: number,
  data: { provider: string; service?: string; carrier?: string; tracking_number?: string },
) => (await api.post<Shipment>(`/orders/${orderId}/shipments`, data)).data;

export const refreshShipment = async (id: number) =>
  (await api.post<{ updates: number; shipment: Shipment }>(`/shipments/${id}/refresh`)).data;

export const setShipmentStatus = async (id: number, status: string) =>
  (await api.post<Shipment>(`/shipments/${id}/status`, { status })).data;

// ---- Partner applications (reviewers)
export const getPartnerApplications = async (status: string = "pending", type?: string) =>
  (await api.get<Paginated<PartnerApplication>>("/partner-applications", { params: { status, ...(type ? { type } : {}) } })).data;

/** Where a reviewer opens one verification document (the browser sends the session cookie). */
export const applicationDocumentUrl = (applicationId: number, documentId: number) =>
  `${api.defaults.baseURL ?? "/api"}/partner-applications/${applicationId}/documents/${documentId}`;

export const approvePartnerApplication = async (id: number) =>
  (await api.post<PartnerApplication>(`/partner-applications/${id}/approve`, { documents_verified: true })).data;

export const rejectPartnerApplication = async (id: number, reason: string) =>
  (await api.post<PartnerApplication>(`/partner-applications/${id}/reject`, { reason: reason || null })).data;

// ---- Seller sales
export interface SellerOrder {
  id: number;
  status: string;
  total_amount: string;
  currency: string;
  customer_name: string | null;
  created_at: string;
  product?: { id: number; name: string; brand: string | null };
  shipping_address?: import("@/types/platform").ShippingAddress | null;
  events?: import("@/types/platform").OrderEvent[];
}

export const getSellerOrders = async () => (await api.get<Paginated<SellerOrder>>("/seller/orders")).data;
export const getSellerOrder = async (id: number | string) => (await api.get<SellerOrder>(`/seller/orders/${id}`)).data;

export interface LedgerEntry {
  id: number;
  order_id: number;
  type: "sale" | "commission";
  amount: string;
  currency: string;
  status: "held" | "available" | "paid";
  created_at: string;
}

export const getSellerEarnings = async () =>
  (await api.get<{ balances: import("@/services/admin.service").Balances; entries: LedgerEntry[]; payouts: import("@/services/admin.service").Payout[] }>("/seller/earnings")).data;

/** Published admin-edited content of a surface, per language (never drafts). */
export const getSurface = async (key: string) =>
  (await api.get<{ values: Record<string, Record<string, unknown>> }>(`/surfaces/${key}`)).data.values;

/** Upload a product photo; returns the path to store in the product's images. */
export const uploadProductImage = async (file: File) => {
  const form = new FormData();
  form.append("image", file);

  return (await api.post<{ path: string }>("/product-images", form, { headers: { "Content-Type": "multipart/form-data" } })).data.path;
};

/** The categories already in use, for the product forms' pick list. */
export interface CategoryGroup { key: string; name: string; name_ar?: string | null; children: { key: string; name: string; name_ar?: string | null }[] }
export const getProductCategories = async () => (await api.get<CategoryGroup[]>("/products/categories")).data;

export interface CategoryRow { id: number; key: string; name_en: string; name_ar: string | null; parent_id: number | null; position: number; is_active: boolean; products: number; children?: CategoryRow[] }
export const getAdminCategories = async () => (await api.get<CategoryRow[]>("/admin/categories")).data;
export const createCategory = async (body: { key: string; name_en: string; name_ar: string; parent_id?: number | null }) => (await api.post<CategoryRow>("/admin/categories", body)).data;
export const updateCategory = async (id: number, body: Partial<Pick<CategoryRow, "name_en" | "name_ar" | "is_active">>) => (await api.put<CategoryRow>(`/admin/categories/${id}`, body)).data;

/** Sets installation (none / optional / required) for many products at once. */
export const bulkSetInstallation = async (ids: number[], installation: "none" | "optional" | "required") =>
  (await api.post<{ updated: number; skipped: number }>("/products/bulk-installation", { ids, installation })).data;

// ---- Markets: one per country, running several industries (platform owner)
export interface MarketRow {
  id: number; name: string; country: string; country_name: string | null; country_name_ar: string | null; currency: string | null; status: string; store_slug: string | null;
  industries: { id: number; key: string; name: string }[];
  stats: { sellers: number; centres: number; products: number; orders: number };
}
export const getMarkets = async () => (await api.get<{ markets: MarketRow[]; industries: { id: number; key: string; name: string }[] }>("/admin/markets")).data;
export const getCountries = async () => (await api.get<{ code: string; name: string; name_ar: string; currency: string; open: boolean }[]>("/admin/countries")).data;
export const openMarket = async (body: { country: string; name?: string; industries: number[]; slug?: string }) => (await api.post<MarketRow>("/admin/markets", body)).data;
export const updateMarket = async (id: number, body: { name?: string; industries?: number[]; status?: "active" | "paused" }) => (await api.put<MarketRow>(`/admin/markets/${id}`, body)).data;

// ---- Warehouse and fulfilment -------------------------------------------------------------------
export interface WarehouseRow { id: number; name: string; city: string | null; address: string | null; is_active: boolean }
export interface InboundLine { id: number; product_id: number; cartons_expected: number; cartons_received: number; product?: { id: number; name: string; sku: string | null; units_per_carton: number | null } }
export interface InboundRow { id: number; warehouse_id: number; seller_id: number; status: "announced" | "received" | "cancelled"; carrier: string | null; tracking_number: string | null; note: string | null; created_at: string; items: InboundLine[] }
export interface StockRow { warehouse_id: number; warehouse: string; product_id: number; name: string; sku: string | null; units_per_carton: number | null; units: number }
export interface PackOrder { id: number; status: string; total_amount: string; currency: string; shipping_address: { name: string; line1: string; city: string; phone: string } | null; product?: { name: string; sku: string | null } }
export interface SellerStock { warehouse: { id: number; name: string; city: string | null } | null; products: { id: number; name: string; sku: string | null; fulfilment: "seller" | "armorhub"; units_per_carton: number | null; stock_cartons: number; units: number }[] }

const wp = (market?: number | null) => (market ? { market } : {});
export const getWarehouses = async (market?: number | null) => (await api.get<WarehouseRow[]>("/warehouse/warehouses", { params: wp(market) })).data;
export const addWarehouse = async (body: { name: string; city?: string }, market?: number | null) => (await api.post<WarehouseRow>("/warehouse/warehouses", body, { params: wp(market) })).data;
export const getWarehouseStock = async (market?: number | null) => (await api.get<StockRow[]>("/warehouse/stock", { params: wp(market) })).data;
export const adjustStock = async (body: { warehouse_id: number; product_id: number; units: number; note: string }, market?: number | null) => (await api.post<{ units: number }>("/warehouse/stock/adjust", body, { params: wp(market) })).data;
export const getWarehouseInbound = async (market?: number | null) => (await api.get<InboundRow[]>("/warehouse/inbound", { params: wp(market) })).data;
export const receiveInbound = async (id: number, items: { id: number; cartons_received: number }[], market?: number | null) => (await api.post<InboundRow>(`/warehouse/inbound/${id}/receive`, { items }, { params: wp(market) })).data;
export const getPackOrders = async (market?: number | null) => (await api.get<PackOrder[]>("/warehouse/orders", { params: wp(market) })).data;
export const shipPackOrder = async (id: number, body: { carrier?: string; tracking_number?: string }, market?: number | null) => (await api.post(`/warehouse/orders/${id}/ship`, body, { params: wp(market) })).data;

export const getSellerStock = async () => (await api.get<SellerStock>("/seller/stock")).data;
export const getSellerInbound = async () => (await api.get<InboundRow[]>("/seller/inbound-shipments")).data;
export const announceInbound = async (body: { items: { product_id: number; cartons: number }[]; carrier?: string; tracking_number?: string; note?: string }) => (await api.post<InboundRow>("/seller/inbound-shipments", body)).data;
export const cancelInbound = async (id: number) => (await api.post<InboundRow>(`/seller/inbound-shipments/${id}/cancel`)).data;

// ---- Invitations and departments ----------------------------------------------------------------
export interface InviteOptions { roles: string[]; departments: { id: number; key: string; name: string; name_ar: string | null; roles: string[] }[] }
export interface InvitationRow { id: number; email: string; role: string; department_id: number | null; status: "pending" | "accepted" | "expired"; expires_at: string }
export interface DepartmentRow { id: number; key: string; name_en: string; name_ar: string | null; scope: string[]; head: { id: number; name: string; email: string } | null; members: number }
export interface DepartmentMember { id: number; name: string; email: string; is_head: boolean; is_active: boolean; roles: string[] }
export interface DepartmentRole { id: number; name: string; label: string; permissions: string[] }

export const getInviteOptions = async () => (await api.get<InviteOptions>("/invitations/options")).data;
export const getInvitations = async () => (await api.get<InvitationRow[]>("/invitations")).data;
export const sendInvitation = async (body: { email: string; role: string; department_id?: number | null; as_head?: boolean }) => (await api.post("/invitations", body)).data;
export const cancelInvitation = async (id: number) => { await api.delete(`/invitations/${id}`); };
export const getInvitation = async (token: string) => (await api.get<{ email: string; role: string; inviter: string | null }>(`/public/invitations/${encodeURIComponent(token)}`)).data;
export const acceptInvitation = async (token: string, body: { name: string; password: string }) => (await api.post<{ message: string }>(`/public/invitations/${encodeURIComponent(token)}/accept`, body)).data;

export const getDepartments = async () => (await api.get<DepartmentRow[]>("/departments")).data;
export const getDepartmentMembers = async (id: number) => (await api.get<DepartmentMember[]>(`/departments/${id}/members`)).data;
export const updateDepartmentMember = async (id: number, userId: number, body: { role?: string; is_active?: boolean }) => (await api.put(`/departments/${id}/members/${userId}`, body)).data;
export const getDepartmentRoles = async (id: number) => (await api.get<DepartmentRole[]>(`/departments/${id}/roles`)).data;
export const createDepartmentRole = async (id: number, body: { name: string; permissions: string[] }) => (await api.post(`/departments/${id}/roles`, body)).data;
export const setDepartmentHead = async (id: number, headUserId: number | null) => (await api.put<DepartmentRow>(`/departments/${id}`, { head_user_id: headUserId })).data;
