import { once } from "@/lib/idempotency";
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
  once("order", data, async (key) => (await api.post<{ order: Order; partner_quotes: number }>("/orders", data, key)).data);

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
  once(`pay-order-${orderId}`, String(amount), async (key) => (await api.post<{ message: string; checkout_url?: string }>(`/orders/${orderId}/payments`, {
    amount,
    payment_method: "card",
  }, key)).data);

export const getPartnerRequests = async () =>
  (await api.get<PartnerQuote[]>("/partner/orders")).data;

export const submitQuote = async (quoteId: number, price: number) =>
  (await api.patch(`/partner-quotes/${quoteId}`, { price })).data;

export const createPartner = async (data: Partial<Partner>) =>
  (await api.post<Partner>("/partners", data)).data;

export const deletePartner = async (id: number) =>
  (await api.delete(`/partners/${id}`)).data;

/** A market admin's change is only a proposal: the answer then says so (`pending`) instead of returning the new item. */
export type Proposed = { pending: true; change_id: number; message: string };
export const isProposed = (r: unknown): r is Proposed => !!r && typeof r === "object" && (r as { pending?: boolean }).pending === true;

export const createCatalogItem = async (
  alias: string,
  data: Record<string, unknown>,
) => (await api.post<CatalogItem | Proposed>(`/catalog/${alias}`, data)).data;

export const deleteCatalogItem = async (alias: string, id: number) =>
  (await api.delete<{ message: string } | Proposed>(`/catalog/${alias}/${id}`)).data;

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

export interface UserFilters { search?: string; scope?: "platform" | "users"; role?: string; status?: "active" | "inactive"; company_id?: number; guests?: boolean; page?: number }

export const getUsers = async (search?: string, scope?: "platform" | "users", filters: Omit<UserFilters, "search" | "scope"> = {}) =>
  (await api.get<Paginated<StaffUser>>("/users", {
    params: {
      ...(search ? { search } : {}), ...(scope ? { scope } : {}),
      ...(filters.role ? { role: filters.role } : {}), ...(filters.status ? { status: filters.status } : {}),
      ...(filters.company_id ? { company_id: filters.company_id } : {}), ...(filters.guests ? { guests: 1 } : {}), ...(filters.page ? { page: filters.page } : {}),
    },
  })).data;

/** An administrator looks at the platform as another person sees it (read-only, 30 minutes, audited). The browser's session is swapped by the server. */
export const viewAsUser = async (id: number) => (await api.post(`/admin/impersonate/${id}`)).data;
export const leaveViewAs = async () => (await api.post("/impersonation/exit")).data;

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
export const fileWarrantyClaim = async (warrantyId: number, description: string) => (await api.post(`/warranties/${warrantyId}/claims`, { description })).data;
export const getWarrantyClaimInbox = async () => (await api.get<import("@/types/platform").WarrantyClaim[]>("/warranty-claims")).data;
export const decideWarrantyClaim = async (id: number, body: { status: "approved" | "rejected" | "resolved"; resolution?: string; note?: string }) => (await api.put(`/warranty-claims/${id}`, body)).data;
/** The invoice PDF of a paid order, as a file the browser can save. */
export const downloadInvoice = async (orderId: number) => (await api.get<Blob>(`/orders/${orderId}/invoice.pdf`, { responseType: "blob" })).data;

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
  cards: { key: string; label: string; value: number; format: "number" | "money"; currency?: string | null }[];
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
  headline_ar?: string | null;
  subheadline_ar?: string | null;
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

export const setShipmentStatus = async (id: number, status: string, extra: { code?: string; description?: string } = {}) =>
  (await api.post<Shipment>(`/shipments/${id}/status`, { status, ...extra })).data;

export const rescheduleShipment = async (id: number, date: string, note?: string) =>
  (await api.post<Shipment>(`/shipments/${id}/reschedule`, { date, note })).data;

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
  release_at?: string | null;
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
export interface CategoryGroup { key: string; name: string; name_ar?: string | null; children: CategoryGroup[] }
export const getProductCategories = async () => (await api.get<CategoryGroup[]>("/products/categories")).data;

export interface CategoryRow { id: number; key: string; name_en: string; name_ar: string | null; parent_id: number | null; position: number; is_active: boolean; products: number; children?: CategoryRow[] }
export const getAdminCategories = async () => (await api.get<CategoryRow[]>("/admin/categories")).data;
export const createCategory = async (body: { key?: string; name_en: string; name_ar: string; parent_id?: number | null }) => (await api.post<CategoryRow | Proposed>("/admin/categories", body)).data;
export const updateCategory = async (id: number, body: Partial<Pick<CategoryRow, "name_en" | "name_ar" | "is_active" | "parent_id">>) => (await api.put<CategoryRow | Proposed>(`/admin/categories/${id}`, body)).data;

export interface CatalogChangeRow { id: number; target: string; action: string; summary: string | null; status: "pending" | "approved" | "rejected"; note: string | null; market: string | null; requested_by: string | null; created_at: string }
export const getCatalogChanges = async (status?: string) => (await api.get<CatalogChangeRow[]>("/admin/catalog-changes", { params: status ? { status } : {} })).data;
export const approveCatalogChange = async (id: number) => (await api.post(`/admin/catalog-changes/${id}/approve`)).data;
export const rejectCatalogChange = async (id: number, note: string) => (await api.post(`/admin/catalog-changes/${id}/reject`, { note })).data;

/** Sets the product-page layout for many products at once (platform administrators only). */
export const bulkSetPageTemplate = async (ids: number[], page_template: string) =>
  (await api.post<{ updated: number; skipped: number }>("/products/bulk-page-template", { ids, page_template })).data;

/** Sets installation (none / optional / required) for many products at once. */
export const bulkSetInstallation = async (ids: number[], installation: "none" | "optional" | "required") =>
  (await api.post<{ updated: number; skipped: number }>("/products/bulk-installation", { ids, installation })).data;

// ---- Markets: one per country, running several industries (platform owner)
export interface MarketRow {
  id: number; name: string; name_ar?: string | null; country: string; country_name: string | null; country_name_ar: string | null; currency: string | null; status: string; store_slug: string | null; selling_enabled: boolean; installation_enabled: boolean;
  industries: { id: number; key: string; name: string }[];
  stats: { sellers: number; centres: number; products: number; orders: number };
}
export const getMarkets = async () => (await api.get<{ markets: MarketRow[]; industries: { id: number; key: string; name: string }[] }>("/admin/markets")).data;
export const getCountries = async () => (await api.get<{ code: string; name: string; name_ar: string; currency: string; open: boolean }[]>("/admin/countries")).data;
export const consolidateMarkets = async () => (await api.post<{ folded: number }>("/admin/markets/consolidate")).data;
export const openMarket = async (body: { country: string; name?: string; name_ar?: string; industries: number[]; slug?: string; selling_enabled?: boolean; installation_enabled?: boolean }) => (await api.post<MarketRow>("/admin/markets", body)).data;
export const updateMarket = async (id: number, body: { name?: string; name_ar?: string; industries?: number[]; status?: "active" | "paused"; selling_enabled?: boolean; installation_enabled?: boolean }) => (await api.put<MarketRow>(`/admin/markets/${id}`, body)).data;

// ---- Warehouse and fulfilment -------------------------------------------------------------------
export interface WarehouseRow { id: number; name: string; city: string | null; address: string | null; is_active: boolean }
export interface InboundLine { id: number; product_id: number; cartons_expected: number; cartons_received: number; note?: string | null; product?: { id: number; name: string; sku: string | null; units_per_carton: number | null } }
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
export const receiveInbound = async (id: number, items: { id: number; cartons_received: number; note?: string }[], market?: number | null) => (await api.post<InboundRow>(`/warehouse/inbound/${id}/receive`, { items }, { params: wp(market) })).data;
export const getPackOrders = async (market?: number | null) => (await api.get<PackOrder[]>("/warehouse/orders", { params: wp(market) })).data;
export const shipPackOrder = async (id: number, body: { carrier?: string; tracking_number?: string }, market?: number | null) => (await api.post(`/warehouse/orders/${id}/ship`, body, { params: wp(market) })).data;

export const getSellerStock = async () => (await api.get<SellerStock>("/seller/stock")).data;
export const getSellerInbound = async () => (await api.get<InboundRow[]>("/seller/inbound-shipments")).data;
export const announceInbound = async (body: { items: { product_id: number; cartons: number }[]; carrier?: string; tracking_number?: string; note?: string }) => (await api.post<InboundRow>("/seller/inbound-shipments", body)).data;
export const cancelInbound = async (id: number) => (await api.post<InboundRow>(`/seller/inbound-shipments/${id}/cancel`)).data;

// ---- Invitations and departments ----------------------------------------------------------------
export interface InviteOptions { roles: string[]; departments: { id: number; key: string; name: string; name_ar: string | null; roles: string[] }[] }
export interface InvitationRow { id: number; email: string; role: string; department_id: number | null; status: "pending" | "accepted" | "expired"; expires_at: string }
export interface DepartmentRow { can_edit_scope?: boolean; ceiling?: string[]; id: number; tenant_id?: number | null; market?: string | null; key: string; name_en: string; name_ar: string | null; scope: string[]; head: { id: number; name: string; email: string } | null; members: number }
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

// ---- Market admins ("Egypt Market Admin": several people per market, each limited to it) ---------
export interface MarketAdmin { id: number; name: string; email: string; is_active: boolean; market: string }
export const getMarketAdmins = async (marketId: number) => (await api.get<MarketAdmin[]>(`/admin/markets/${marketId}/admins`)).data;
export const addMarketAdmin = async (marketId: number, email: string) => (await api.post<{ invited: boolean }>(`/admin/markets/${marketId}/admins`, { email })).data;
export const removeMarketAdmin = async (marketId: number, userId: number) => { await api.delete(`/admin/markets/${marketId}/admins/${userId}`); };
export const openMarketDepartments = async (market?: number) => (await api.post<DepartmentRow[]>("/departments/open", null, { params: market ? { market } : {} })).data;
export const updateDepartmentScope = async (id: number, scope: string[]) => (await api.put<DepartmentRow>(`/departments/${id}/scope`, { scope })).data;
export const updateDepartmentRole = async (id: number, roleId: number, permissions: string[]) => (await api.put(`/departments/${id}/roles/${roleId}`, { permissions })).data;
export const deleteDepartmentRole = async (id: number, roleId: number) => { await api.delete(`/departments/${id}/roles/${roleId}`); };

/** What the signed-in customer may do on a product's reviews: leave one (if they received it) or edit theirs. */
export const getMyReview = async (productId: number) =>
  (await api.get<{ eligible: boolean; review: { rating: number; comment: string | null; status: string; photos?: string[] } | null }>(`/products/${productId}/review`)).data;

export const saveReview = async (productId: number, rating: number, comment: string, photos: string[] = []) =>
  (await api.post<{ rating: number; comment: string | null; photos: string[] }>(`/products/${productId}/review`, { rating, comment, photos })).data;

/** One picture for the review; returns the path to send with the review. */
export const uploadReviewPhoto = async (productId: number, file: File) => {
  const form = new FormData();
  form.append("image", file);
  return (await api.post<{ path: string }>(`/products/${productId}/review-photos`, form, { headers: { "Content-Type": "multipart/form-data" } })).data.path;
};

// ---- Questions about a product
export interface ProductQuestionRow { id: number; question: string; answer: string | null; answered_at: string | null; asked_at: string | null; status: string; product?: { id: number; name: string }; asker?: string }
export const askQuestion = async (productId: number, question: string) => (await api.post<ProductQuestionRow>(`/products/${productId}/questions`, { question })).data;
export const getMyQuestions = async (productId: number) => (await api.get<ProductQuestionRow[]>(`/products/${productId}/questions`)).data;
export const getQuestionInbox = async () => (await api.get<ProductQuestionRow[]>("/product-questions")).data;
export const answerQuestion = async (id: number, answer: string) => (await api.put<ProductQuestionRow>(`/product-questions/${id}/answer`, { answer })).data;

// ---- Subscription plans for partners and sellers
export interface SubscriptionPlan { id?: number; key: string; name: string; price_monthly: number; currency: string; customer_limit: number | null; commission_percentage: number; team_seats: number; is_active?: boolean }
export interface SubscriptionView {
  plan: SubscriptionPlan;
  usage: { customers: number; limit: number | null; reached: boolean };
  period_end: string | null;
  auto_renew: boolean;
  plans: SubscriptionPlan[];
  invoices: { id: number; plan_key: string; amount: number; currency: string; status: string; period_start: string; period_end: string; reference: string | null }[];
}

export const getSubscription = async () => (await api.get<SubscriptionView>("/subscription")).data;
export const chooseSubscription = async (plan: string) => (await api.post<SubscriptionView>("/subscription", { plan })).data;
export const cancelSubscription = async () => (await api.post<SubscriptionView>("/subscription/cancel")).data;
export const getSubscriptionPlans = async () => (await api.get<SubscriptionPlan[]>("/admin/subscription-plans")).data;
export const updateSubscriptionPlan = async (id: number, data: Partial<SubscriptionPlan>) => (await api.put<SubscriptionPlan>(`/admin/subscription-plans/${id}`, data)).data;

export const getProductReadiness = async (id: number) => (await api.get<import("@/types/platform").ProductReadiness>(`/products/${id}/readiness`)).data;

export interface SellerPayoutAccount { method: "bank" | "wallet"; holder: string; bank_name: string | null; number: string | null; status: "pending" | "verified" | "rejected"; note: string | null }
export const getPayoutAccount = async () => (await api.get<{ account: SellerPayoutAccount | null; min_amount: number }>("/seller/payout-account")).data;
export const savePayoutAccount = async (body: { method: "bank" | "wallet"; holder: string; bank_name?: string; account_number?: string; wallet_phone?: string }) => (await api.put<{ account: SellerPayoutAccount }>("/seller/payout-account", body)).data;
export const requestPayout = async (currency: string) => (await api.post("/seller/payouts/request", { currency })).data;

export interface SellerProfile { about: string | null; city: string | null; hours: string | null; logo: string | null; ships_within_days: number | null; return_address: { line1: string | null; city: string | null; notes: string | null } }
export const getSellerProfile = async () => (await api.get<{ name: string; profile: SellerProfile }>("/seller/profile")).data;
export const saveSellerProfile = async (profile: Partial<SellerProfile>) => (await api.put<{ name: string; profile: SellerProfile }>("/seller/profile", profile)).data;

export interface SellerPromo { id: number; code: string; type: "percent" | "fixed"; value: number; max_discount: number | null; min_order: number | null; starts_at: string | null; ends_at: string | null; usage_limit: number | null; per_customer_limit: number; is_active: boolean; used: number; given: number; product_ids?: number[]; products?: Record<string, string> }
export const getSellerPromos = async () => (await api.get<SellerPromo[]>("/seller/promo-codes")).data;
export const createSellerPromo = async (body: Partial<SellerPromo>) => (await api.post<SellerPromo>("/seller/promo-codes", body)).data;
export const updateSellerPromo = async (id: number, body: Partial<SellerPromo>) => (await api.put<SellerPromo>(`/seller/promo-codes/${id}`, body)).data;

export interface SellerBundle { id: number; name: string; name_ar: string | null; description: string | null; base_price: number; worth: number; is_active: boolean; items: { product_id: number; name: string | null; quantity: number; price: number }[] }
export const getSellerBundles = async () => (await api.get<SellerBundle[]>("/seller/bundles")).data;
export const createSellerBundle = async (body: { name: string; name_ar?: string; description?: string; base_price: number; items: { product_id: number; quantity: number }[] }) => (await api.post<SellerBundle>("/seller/bundles", body)).data;
export const updateSellerBundle = async (id: number, body: { base_price?: number; is_active?: boolean }) => (await api.put<SellerBundle>(`/seller/bundles/${id}`, body)).data;

export const submitProductReview = async (id: number) => (await api.post<{ id: number; review_status: string }>(`/products/${id}/submit-review`)).data;
export interface ReviewRow { id: number; seller: string | null; status: string; note: string | null; submitted_at: string | null; name: string; name_ar: string | null; description: string | null; description_ar: string | null; category: string | null; brand: string | null; price: number; images: string[]; fits_note: string | null; bundle: { name: string | null; quantity: number }[] }
export const getProductReviews = async (status = "pending") => (await api.get<ReviewRow[]>("/admin/product-reviews", { params: { status } })).data;
export const approveProduct = async (id: number) => (await api.post(`/admin/product-reviews/${id}/approve`)).data;
export const rejectProduct = async (id: number, note: string) => (await api.post(`/admin/product-reviews/${id}/reject`, { note })).data;
