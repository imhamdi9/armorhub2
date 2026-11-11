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
