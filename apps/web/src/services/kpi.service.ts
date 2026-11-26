import { api } from "@/services/api";

type ByCurrency = Record<string, number>;
type Target = { value: number | null; target: number; met: boolean };

export interface Kpis {
  period: { from: string; to: string; market: string | null };
  gmv: { total: ByCurrency; orders: ByCurrency; installation: ByCurrency };
  net_revenue: { total: ByCurrency; streams: Record<string, ByCurrency> };
  take_rate: Record<string, number | null>;
  average_order_value: Record<string, number | null>;
  active: { sellers: number; centres: number };
  rfq: { requests: number; response_rate: number | null; quote_conversion: number | null };
  orders: { paid: number; completed: number; completion_rate: number | null };
  repeat_purchase_rate: number | null;
  pilot: Record<"centres" | "sellers" | "requests_per_month" | "answered_share" | "executed_share", Target>;
  /** The same number of days just before this period, to read each figure as up or down. */
  previous?: {
    period: { from: string; to: string };
    gmv: Kpis["gmv"];
    net_revenue: Kpis["net_revenue"];
    average_order_value: Record<string, number | null>;
    orders: Kpis["orders"];
    rfq: Kpis["rfq"];
  };
  /** What customers paid each day of the period, per currency. */
  series?: { days: string[]; by_currency: Record<string, number[]> };
}

export const getKpis = async (params: { from?: string; to?: string; market?: string }) =>
  (await api.get<Kpis>("/admin/kpis", { params })).data;
