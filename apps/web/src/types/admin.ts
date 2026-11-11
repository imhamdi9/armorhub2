export interface SettingRow {
  key: string;
  group: string;
  label: string;
  type: "string" | "text" | "int" | "float" | "bool" | "select" | "secret";
  help: string;
  options: Record<string, string>;
  value: string | number | boolean | null;
  has_value: boolean | null;
  is_default: boolean;
}

export interface ProviderField {
  key: string;
  label: string;
  type: "string" | "text" | "bool" | "select" | "secret";
  required: boolean;
  help: string;
  options: Record<string, string>;
  value: string | number | boolean | null;
  has_value: boolean | null;
}

export interface PaymentProviderInfo {
  key: string;
  label: string;
  help: string;
  active: boolean;
  configured: boolean;
  webhook_url: string;
  fields: ProviderField[];
}

export interface Card {
  key: string;
  label: string;
  value: number;
  format: "number" | "money";
}

export interface Overview {
  numbers: Card[];
  orders_by_status: Record<string, number>;
  payments: { provider: string; provider_key: string; configured: boolean };
  attention: { key: string; label: string; count: number; path: string; severity: "fail" | "warn" | "info" }[];
  recent_activity: { id: number; action: string; user: string | null; subject: string | null; created_at: string }[];
}

export interface SecurityReport {
  checks: { id: string; status: "ok" | "warn" | "fail"; message: string }[];
  last_24h: { failed_logins: number; blocked_signups: number; blocked_resets: number; new_accounts: number };
  top_offenders: { ip: string; attempts: number }[];
  active_sessions: number;
}

export interface AuditEntry {
  id: number;
  action: string;
  user: { id: number; name: string; email: string } | null;
  subject: string | null;
  ip: string | null;
  meta: Record<string, unknown> | null;
  created_at: string;
}

export interface AdminPayment {
  id: number;
  order_id: number;
  amount: string;
  currency: string;
  status: string;
  provider: string | null;
  reference: string | null;
  created_at: string;
}
