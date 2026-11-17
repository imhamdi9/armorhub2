import { api } from "@/services/api";

export interface CartLine {
  id: number;
  product_id: number;
  name: string | null;
  image: string | null;
  quantity: number;
  seller_id: number | null;
  unit_price: number | null;
  line_total: number | null;
  currency: string | null;
  /** Why this line can no longer be bought (price removed, out of stock...), shown instead of hiding it. */
  problem: string | null;
}

export interface CartView {
  items: CartLine[];
  currency: string | null;
  items_amount: number;
  service_fee_amount: number;
  payable_amount: number;
  can_checkout: boolean;
}

export interface PurchaseOrder {
  id: number;
  product: string | null;
  image: string | null;
  quantity: number;
  total_amount: number;
  service_fee_amount: number;
  status: string;
  statuses?: import("@/components/orders/status-strip").OrderStatuses;
}

export interface Purchase {
  id: number;
  status: "awaiting_payment" | "paid" | "cancelled";
  currency: string;
  items_amount: number;
  service_fee_amount: number;
  payable_amount: number;
  paid_at: string | null;
  created_at: string | null;
  orders: PurchaseOrder[];
}

export interface DeliveryAddress {
  name: string;
  phone: string;
  line1: string;
  city: string;
  region?: string;
}

export const getCart = async () => (await api.get<CartView>("/cart")).data;
export const addToCart = async (productId: number, optionId?: number | null, quantity = 1, vehicleId?: number | null) =>
  (await api.post<CartView>("/cart/items", { product_id: productId, ...(optionId ? { option_id: optionId } : {}), ...(vehicleId ? { vehicle_id: vehicleId } : {}), quantity })).data;
export const setCartQuantity = async (itemId: number, quantity: number) => (await api.put<CartView>(`/cart/items/${itemId}`, { quantity })).data;
export const removeCartItem = async (itemId: number) => (await api.delete<CartView>(`/cart/items/${itemId}`)).data;

export const checkoutCart = async (address: DeliveryAddress) => (await api.post<Purchase>("/checkout", { shipping_address: address })).data;
export const getPurchases = async () => (await api.get<Purchase[]>("/order-groups")).data;
export const getPurchase = async (id: number | string) => (await api.get<Purchase>(`/order-groups/${id}`)).data;
export const payPurchase = async (id: number) => (await api.post<{ status: string; checkout_url?: string; group: Purchase }>(`/order-groups/${id}/payments`, { payment_method: "card" })).data;
export const cancelPurchase = async (id: number) => (await api.post<Purchase>(`/order-groups/${id}/cancel`)).data;
