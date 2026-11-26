import { once } from "@/lib/idempotency";
import { api } from "@/services/api";
import { shared } from "@/services/shared";

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
  /** What delivery costs (0 when free), the usual days it takes, and the cart value above which it is free. */
  shipping_amount: number;
  /** A discount code applied to the cart: what it takes off, or why the code was dropped. */
  discount_amount: number;
  /** Whether this purchase may be paid in cash at the door. */
  cod_available?: boolean;
  /** How goods may be delivered: the usual way, and faster for an extra fee when the market offers it. Empty when nothing in the cart is shipped. */
  /** Loyalty points the customer may spend on this purchase: their balance, the most allowed and what one point is worth. */
  points?: { balance: number; max_use: number; value: number } | null;
  delivery_options?: { key: "standard" | "express"; extra_fee: number; eta_days: number | null }[];
  promo_code: string | null;
  promo_problem: string | null;
  eta_days: number | null;
  free_shipping_over: number | null;
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
  payable_amount?: number;
  status: string;
  statuses?: import("@/components/orders/status-strip").OrderStatuses;
}

export interface Purchase {
  id: number;
  status: "awaiting_payment" | "paid" | "cancelled";
  currency: string;
  items_amount: number;
  service_fee_amount: number;
  shipping_amount?: number;
  discount_amount?: number;
  payable_amount: number;
  market?: string | null;
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

export const getCart = () => shared("cart", async () => (await api.get<CartView>("/cart")).data);
/** The header's cart count and the "added to cart" panel listen for this. */
export const CART_CHANGED = "cart-changed";
const announceCart = <T,>(cart: T): T => { if (typeof window !== "undefined") window.dispatchEvent(new Event(CART_CHANGED)); return cart; };

export const addToCart = async (productId: number, optionId?: number | null, quantity = 1, vehicleId?: number | null) =>
  announceCart((await api.post<CartView>("/cart/items", { product_id: productId, ...(optionId ? { option_id: optionId } : {}), ...(vehicleId ? { vehicle_id: vehicleId } : {}), quantity })).data);
export const setCartQuantity = async (itemId: number, quantity: number) => announceCart((await api.put<CartView>(`/cart/items/${itemId}`, { quantity })).data);
export const removeCartItem = async (itemId: number) => announceCart((await api.delete<CartView>(`/cart/items/${itemId}`)).data);

export interface DeliveryChoices { delivery_speed?: "standard" | "express"; preferred_day?: string; gift_note?: string; use_points?: number }
/** `contents` says what is in the cart, so a changed cart is never answered with an earlier purchase. */
export const checkoutCart = async (address: DeliveryAddress, choices: DeliveryChoices = {}, contents = "") => {
  const body = { shipping_address: address, ...choices };
  return once("checkout", [body, contents], async (key) => (await api.post<Purchase>("/checkout", body, key)).data);
};
export const getPurchases = async () => (await api.get<Purchase[]>("/order-groups")).data;
export const getPurchase = async (id: number | string) => (await api.get<Purchase>(`/order-groups/${id}`)).data;
export const payPurchase = async (id: number, method: "card" | "wallet" | "cod" = "card") =>
  once(`pay-purchase-${id}`, method, async (key) => (await api.post<{ status: string; checkout_url?: string; group: Purchase }>(`/order-groups/${id}/payments`, { payment_method: method }, key)).data);
export const cancelPurchase = async (id: number) => (await api.post<Purchase>(`/order-groups/${id}/cancel`)).data;

export const applyPromo = async (code: string) => (await api.post<CartView>("/cart/promo", { code })).data;
export const removePromo = async () => (await api.delete<CartView>("/cart/promo")).data;
