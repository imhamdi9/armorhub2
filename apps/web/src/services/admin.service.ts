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
