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
  type: "text" | "longtext" | "bool" | "number" | "color" | "url" | "select" | "list";
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
export const publishSurface = async (key: string, locale: string, note?: string) => (await api.post(`/admin/surfaces/${key}/publish`, { locale, note })).data;
export const rollbackSurface = async (key: string, locale: string, version: number) => (await api.post(`/admin/surfaces/${key}/rollback`, { locale, version })).data;
