import { api } from "@/services/api";
import type {
  AdminPayment,
  AuditEntry,
  Overview,
  PaymentProviderInfo,
  SecurityReport,
  SettingRow,
} from "@/types/admin";
import type { Paginated } from "@/types/platform";

export const getOverview = async () => (await api.get<Overview>("/admin/overview")).data;

export const getSettings = async () =>
  (await api.get<{ settings: SettingRow[] }>("/admin/settings")).data.settings;

// ---- Marketing: ad platform connections, what campaigns brought, and what was reported to the platforms ----
export const getMarketingSettings = async () => (await api.get<{ settings: SettingRow[] }>("/admin/marketing/settings")).data.settings;
export const saveMarketingSettings = async (values: Record<string, unknown>) => (await api.put<{ settings: SettingRow[] }>("/admin/marketing/settings", { values })).data.settings;
export const resetMarketingSetting = async (key: string) => (await api.delete<{ settings: SettingRow[] }>(`/admin/marketing/settings/${key}`)).data.settings;
export const getMarketingMarkets = async () => (await api.get<{ id: number; name: string; market: string }[]>("/admin/marketing/markets")).data;
export interface AttributionRow {
  source: string; campaign: string; orders: number; revenue: number; sellers: number; centres: number; currency: string | null;
  spend: number; spend_currency: string | null; impressions: number; clicks: number; cost_per_order: number | null; cost_per_application: number | null; roas: number | null; shared_account: boolean;
}
export interface Attribution { days: number; rows: AttributionRow[]; totals: { orders: number; tracked_orders: number; applications: number; tracked_applications: number; spend: number }; spend_synced_at: string | null }
export const getAttribution = async (days: number) => (await api.get<Attribution>("/admin/marketing/attribution", { params: { days } })).data;
export interface AdLog { id: number; platform: string; event_name: string; event_id: string; status: "sent" | "failed" | "skipped"; http_status: number | null; note: string | null; created_at: string }
export const getAdLogs = async (platform?: string) => (await api.get<{ data: AdLog[] }>("/admin/marketing/logs", { params: platform ? { platform } : {} })).data.data;
export const syncAdSpend = async () => (await api.post<{ results: { platform: string; account: string; status: string; rows: number; note: string | null }[] }>("/admin/marketing/spend/sync")).data.results;
export const testAdConnections = async () => (await api.post<{ results: { platform: string; status: string; http_status: number | null; note: string | null }[] }>("/admin/marketing/test")).data.results;

export const saveSettings = async (values: Record<string, unknown>) =>
  (await api.put<{ settings: SettingRow[] }>("/admin/settings", { values })).data.settings;

export const resetSetting = async (key: string) =>
  (await api.delete<{ settings: SettingRow[] }>(`/admin/settings/${key}`)).data.settings;

export const getProviders = async () =>
  (await api.get<{ active: string; providers: PaymentProviderInfo[] }>("/admin/payments/providers")).data;

export const saveProvider = async (key: string, values: Record<string, unknown>) =>
  (await api.put<{ active: string; providers: PaymentProviderInfo[] }>(`/admin/payments/providers/${key}`, { values })).data;

export const activateProvider = async (provider: string) =>
  (await api.post<{ active: string; providers: PaymentProviderInfo[] }>("/admin/payments/active", { provider })).data;

export const testProvider = async (key: string) =>
  (await api.post<{ ok: boolean; message: string }>(`/admin/payments/providers/${key}/test`)).data;

export const getAdminPayments = async (status?: string) =>
  (await api.get<Paginated<AdminPayment>>("/admin/payments", { params: status ? { status } : {} })).data;

export const getSecurity = async () => (await api.get<SecurityReport>("/admin/security")).data;

export const revokeAllSessions = async () =>
  (await api.post<{ message: string }>("/admin/security/revoke-all-sessions")).data;

export const getAuditLogs = async (params: { action?: string; search?: string; ip?: string; page?: number }) =>
  (await api.get<Paginated<AuditEntry>>("/admin/audit-logs", { params })).data;

export const getShippingProviders = async () =>
  (await api.get<{ active: string; providers: PaymentProviderInfo[] }>("/admin/shipping/providers")).data;

export const saveShippingProvider = async (key: string, values: Record<string, unknown>) =>
  (await api.put<{ active: string; providers: PaymentProviderInfo[] }>(`/admin/shipping/providers/${key}`, { values })).data;

export const activateShippingProvider = async (provider: string) =>
  (await api.post<{ active: string; providers: PaymentProviderInfo[] }>("/admin/shipping/active", { provider })).data;

export const testShippingProvider = async (key: string) =>
  (await api.post<{ ok: boolean; message: string }>(`/admin/shipping/providers/${key}/test`)).data;

// ---- Platform core: modules and events
export interface PlatformModule {
  id: string;
  name: string;
  version: string;
  description: string;
  depends_on: string[];
  events: string[];
  subscribers: string[];
  enabled: boolean | null;
}

export interface EventDelivery {
  id: number;
  subscriber: string;
  status: string;
  attempts: number;
  last_error?: string | null;
}

export interface PlatformEvent {
  id: number;
  event_id: string;
  name: string;
  version: number;
  tenant_id: number | null;
  aggregate_type: string | null;
  aggregate_id: string | null;
  source: string | null;
  correlation_id: string;
  occurred_at: string;
  payload?: Record<string, unknown>;
  deliveries: EventDelivery[];
}

export const getPlatformModules = async (tenantId?: number) =>
  (await api.get<{ modules: PlatformModule[] }>("/admin/platform/modules", { params: tenantId ? { tenant_id: tenantId } : {} })).data.modules;

export const setTenantModule = async (tenantId: number, module: string, enabled: boolean) =>
  (await api.put(`/admin/platform/tenants/${tenantId}/modules/${module}`, { enabled })).data;

export const getEventCatalog = async () =>
  (await api.get<{ events: { name: string; owner: string; version: number; description: string; schema: Record<string, string> }[] }>("/admin/platform/events/catalog")).data.events;

export const getPlatformEvents = async (params: { name?: string; status?: string }) =>
  (await api.get<Paginated<PlatformEvent>>("/admin/platform/events", { params: Object.fromEntries(Object.entries(params).filter(([, v]) => v)) })).data;

export const getPlatformEvent = async (id: string) => (await api.get<PlatformEvent>(`/admin/platform/events/${id}`)).data;

export const replayPlatformEvent = async (id: string, subscriber?: string) =>
  (await api.post(`/admin/platform/events/${id}/replay`, subscriber ? { subscriber } : {})).data;

export interface WorkflowDef {
  key: string;
  version: number;
  initial: string;
  states: Record<string, { label?: string; final?: boolean }>;
  transitions: Record<string, { from: string[] | "*"; to: string; guards: string[]; actions: string[]; event: string | null; message: string | null }>;
}

export interface WorkflowInfo {
  key: string;
  owner: string;
  default: WorkflowDef;
  active: WorkflowDef;
  customised: boolean;
  version: number | null;
}

export const getWorkflows = async (tenantId?: number) =>
  (await api.get<{ workflows: WorkflowInfo[] }>("/admin/platform/workflows", { params: tenantId ? { tenant_id: tenantId } : {} })).data.workflows;

export const saveWorkflow = async (tenantId: number, key: string, definition: unknown) =>
  (await api.put(`/admin/platform/tenants/${tenantId}/workflows/${key}`, { definition })).data;

export const resetWorkflow = async (tenantId: number, key: string) =>
  (await api.delete(`/admin/platform/tenants/${tenantId}/workflows/${key}`)).data;

export interface BlueprintInfo {
  id: string;
  owner: string;
  name: string;
  description: string;
  version: number;
  params: Record<string, string>;
  steps: { id: string | null; type: string }[];
}

export interface BlueprintApplication {
  id: number;
  blueprint_id: string;
  tenant_id: number | null;
  store_slug: string | null;
  params: Record<string, string>;
  created_at: string;
}

export const getBlueprints = async () => (await api.get<{ blueprints: BlueprintInfo[] }>("/admin/platform/blueprints")).data.blueprints;

export const getBlueprintHistory = async () =>
  (await api.get<{ applications: BlueprintApplication[] }>("/admin/platform/blueprints/applications")).data.applications;

export const applyBlueprint = async (id: string, params: Record<string, string>) =>
  (await api.post<BlueprintApplication>(`/admin/platform/blueprints/${id}/apply`, { params })).data;

// ---- Seller payouts
export type Balances = Record<string, { held: number; available: number; in_payout: number; paid: number }>;

export interface Payout {
  account?: { method: string; holder: string; bank_name: string | null; number: string | null } | null;
  source?: string;
  id: number;
  company_id: number;
  amount: string;
  currency: string;
  status: "pending" | "paid";
  reference: string | null;
  paid_at: string | null;
  created_at: string;
  company?: { id: number; name: string };
}

export const getPayouts = async () =>
  (await api.get<{ sellers: { id: number; name: string; balances: Balances }[]; payouts: Payout[] }>("/admin/payouts")).data;

export const createPayout = async (companyId: number, currency: string) => (await api.post<Payout>("/admin/payouts", { company_id: companyId, currency })).data;

export const markPayoutPaid = async (id: number, reference: string) => (await api.post<Payout>(`/admin/payouts/${id}/paid`, { reference })).data;

export interface SurfaceField {
  type: "text" | "longtext" | "bool" | "number" | "color" | "url" | "image" | "select" | "list";
  label?: string;
  max?: number;
  maxItems?: number;
  options?: string[];
  required?: boolean;
  default?: unknown;
  items?: Record<string, SurfaceField>;
}
export interface SurfaceInfo { key: string; owner: string; label: string; description: string; public: boolean; fields: Record<string, SurfaceField>; published_versions: Record<string, number | null> }
export interface SurfaceDetail {
  draft: Record<string, unknown> | null;
  published: Record<string, unknown>;
  has_draft: boolean;
  published_version: number | null;
  surface: SurfaceInfo;
  history: { version: number; status: string; note: string | null; published_at: string | null }[];
}

export const getSurfaces = async () => (await api.get<{ surfaces: SurfaceInfo[] }>("/admin/surfaces")).data.surfaces;
export const getSurfaceDetail = async (key: string, locale: string) => (await api.get<SurfaceDetail>(`/admin/surfaces/${key}`, { params: { locale } })).data;
export const saveSurfaceDraft = async (key: string, locale: string, values: Record<string, unknown>) => (await api.put(`/admin/surfaces/${key}`, { locale, values })).data;
export interface PublishResult { status: "published" | "pending" | "scheduled"; publish_at?: string | null }
export const publishSurface = async (key: string, locale: string, note?: string, publishAt?: string) =>
  (await api.post<PublishResult>(`/admin/surfaces/${key}/publish`, { locale, note, ...(publishAt ? { publish_at: new Date(publishAt).toISOString() } : {}) })).data;
export const rollbackSurface = async (key: string, locale: string, version: number) => (await api.post(`/admin/surfaces/${key}/rollback`, { locale, version })).data;

export interface RoleInfo { id: number; name: string; built_in: boolean; editable?: boolean; permissions: string[]; users: number }

export const getRoles = async () => (await api.get<{ roles: RoleInfo[]; grantable: string[]; assignable: string[] }>("/admin/roles")).data;
export const createRole = async (name: string, permissions: string[]) => (await api.post("/admin/roles", { name, permissions })).data;
export const updateRole = async (id: number, permissions: string[]) => (await api.put(`/admin/roles/${id}`, { permissions })).data;
export const deleteRole = async (id: number) => (await api.delete(`/admin/roles/${id}`)).data;

export interface MediaImage { path: string; original_name: string; size: number }
export const getMedia = async () => (await api.get<{ images: MediaImage[] }>("/admin/media")).data.images;
export const uploadImage = async (file: File) => {
  const form = new FormData();
  form.append("image", file);

  return (await api.post<{ path: string }>("/admin/media", form, { headers: { "Content-Type": "multipart/form-data" } })).data.path;
};

export interface Publication {
  id: number; surface_key: string; surface_label: string; locale: string; status: string; note: string | null;
  publish_at: string | null; requested_by: number | null; requested_by_name: string | null;
}
export const getPublications = async () => (await api.get<{ require_approval: boolean; open: Publication[]; recent: Publication[] }>("/admin/surface-publications")).data;
export const decidePublication = async (id: number, action: "approve" | "reject" | "cancel") => (await api.post<{ status: string }>(`/admin/surface-publications/${id}/${action}`)).data;

export interface PayoutAccountRow { id: number; company: string | null; method: "bank" | "wallet"; holder: string; bank_name: string | null; number: string | null; status: "pending" | "verified" | "rejected"; note: string | null }
export const getPayoutAccounts = async () => (await api.get<PayoutAccountRow[]>("/admin/payout-accounts")).data;
export const decidePayoutAccount = async (id: number, decision: "verified" | "rejected", note?: string) => (await api.post(`/admin/payout-accounts/${id}/decide`, { decision, note })).data;

// ---- exports, search, system health ---------------------------------------------------------------------------------------

export type ExportKind = "orders" | "payments" | "payouts" | "users";

/** Downloads a CSV of records. The browser's own session authorises it; the file is built by the server and each download is audited. */
export async function downloadExport(kind: ExportKind, range: { from?: string; to?: string } = {}) {
  const res = await api.get<Blob>(`/admin/export/${kind}`, { params: { ...(range.from ? { from: range.from } : {}), ...(range.to ? { to: range.to } : {}) }, responseType: "blob" });
  const url = URL.createObjectURL(res.data);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${kind}-${new Date().toISOString().slice(0, 10)}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

export interface SearchGroup { key: string; label: string; items: { id: number; title: string; detail: string; path: string }[] }
export const searchAdmin = async (q: string) => (await api.get<{ query: string; groups: SearchGroup[] }>("/admin/search", { params: { q } })).data;

export interface SystemReport {
  checks: { key: string; label: string; status: "ok" | "warn" | "fail"; detail: string; params?: Record<string, string | number | null> }[];
  failed_jobs: { uuid: string; queue: string; job: string; error: string; failed_at: string }[];
  runtime: { php: string; environment: string; queue: string; mail: string };
}
export const getSystem = async () => (await api.get<SystemReport>("/admin/system")).data;
export const retryFailedJob = async (uuid: string) => (await api.post(`/admin/system/failed-jobs/${uuid}/retry`)).data;
export const removeFailedJob = async (uuid: string) => api.delete(`/admin/system/failed-jobs/${uuid}`);
