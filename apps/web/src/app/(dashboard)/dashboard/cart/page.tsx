"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage, fieldErrors } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { checkoutCart, getCart, payPurchase, removeCartItem, setCartQuantity, type CartView } from "@/services/cart.service";

const MAX = 50;

/** The cart: set quantities, give the delivery address, and pay once for everything (even from several sellers). */
export default function CartPage() {
  const user = useCurrentUser();
  const router = useRouter();
  const [cart, setCart] = useState<CartView | null>(null);
  const [form, setForm] = useState({ name: user?.name ?? "", phone: "", line1: "", city: "" });
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => getCart().then(setCart).catch(() => setCart({ items: [], currency: null, items_amount: 0, service_fee_amount: 0, payable_amount: 0, can_checkout: false })), []);
  useEffect(() => { void load(); }, [load]);

  async function change(id: number, quantity: number) {
    setError(null);
    try { setCart(await setCartQuantity(id, quantity)); } catch (e) { setError(errorMessage(e)); }
  }

  async function remove(id: number) {
    setCart(await removeCartItem(id));
  }

  async function placeOrder(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    setFields({});
    try {
      const purchase = await checkoutCart(form);
      const paid = await payPurchase(purchase.id);
      if (paid.checkout_url) {
        window.location.href = paid.checkout_url;
        return;
      }
      router.push(`/dashboard/purchases/${purchase.id}`);
    } catch (err) {
      setFields(fieldErrors(err));
      setError(errorMessage(err));
      setBusy(false);
    }
  }

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => { setForm((f) => ({ ...f, [k]: e.target.value })); setFields((f) => ({ ...f, [`shipping_address.${k}`]: "" })); };
  const err = (k: string) => fields[`shipping_address.${k}`] && <p role="alert" className="mt-1 text-sm text-red-600">{fields[`shipping_address.${k}`]}</p>;

  if (cart === null) return <p className="text-muted-foreground">{tr("Loading...")}</p>;

  return (
    <div>
      <PageHeader title={tr("Your cart")} />
      {cart.items.length === 0 ? (
        <EmptyState>{tr("Your cart is empty.")} <Link href="/" className="font-medium underline underline-offset-2">{tr("Keep shopping")}</Link></EmptyState>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
          <ul className="space-y-3" data-testid="cart-lines">
            {cart.items.map((l) => (
              <li key={l.id} className="flex items-center gap-3 rounded-2xl border p-3">
                {l.image && imageSrc(l.image)
                  // eslint-disable-next-line @next/next/no-img-element -- the seller's own product photo
                  ? <img src={imageSrc(l.image) ?? ""} alt="" className="size-16 shrink-0 rounded-xl border object-cover" />
                  : <span aria-hidden="true" className="grid size-16 shrink-0 place-items-center rounded-xl border bg-muted text-lg">{(l.name ?? "?").slice(0, 1)}</span>}
                <div className="min-w-0 flex-1">
                  <p className="truncate font-semibold">{l.name}</p>
                  {l.problem
                    ? <p className="text-sm text-red-600" role="alert">{l.problem}</p>
                    : <p className="text-sm text-muted-foreground">{formatMoney(l.unit_price ?? 0, l.currency ?? "")}</p>}
                  <div className="mt-1 flex items-center gap-2">
                    <Button type="button" size="sm" variant="outline" aria-label={tr("Fewer")} disabled={l.quantity <= 1} onClick={() => change(l.id, l.quantity - 1)}>−</Button>
                    <span className="w-6 text-center" aria-live="polite">{l.quantity}</span>
                    <Button type="button" size="sm" variant="outline" aria-label={tr("More")} disabled={l.quantity >= MAX} onClick={() => change(l.id, l.quantity + 1)}>+</Button>
                    <button type="button" className="ms-3 text-sm underline" onClick={() => remove(l.id)}>{tr("Remove")}</button>
                  </div>
                </div>
                {l.line_total !== null && <b className="shrink-0">{formatMoney(l.line_total, l.currency ?? "")}</b>}
              </li>
            ))}
          </ul>

          <form onSubmit={placeOrder} className="space-y-4 rounded-2xl border p-4 lg:sticky lg:top-4 lg:self-start" data-testid="checkout-form">
            <h2 className="text-lg font-bold">{tr("Delivery address")}</h2>
            <div><Label htmlFor="c-name">{tr("Full name")}</Label><Input id="c-name" required value={form.name} onChange={set("name")} />{err("name")}</div>
            <div><Label htmlFor="c-phone">{tr("Mobile number")}</Label><Input id="c-phone" required inputMode="tel" value={form.phone} onChange={set("phone")} />{err("phone")}</div>
            <div><Label htmlFor="c-line1">{tr("Address")}</Label><Input id="c-line1" required value={form.line1} onChange={set("line1")} />{err("line1")}</div>
            <div><Label htmlFor="c-city">{tr("City")}</Label><Input id="c-city" required value={form.city} onChange={set("city")} />{err("city")}</div>

            <dl className="space-y-1 border-t pt-3 text-sm">
              <div className="flex justify-between"><dt>{tr("Items")}</dt><dd>{formatMoney(cart.items_amount, cart.currency ?? "")}</dd></div>
              {cart.service_fee_amount > 0 && <div className="flex justify-between" data-testid="platform-fee"><dt>{tr("Platform fee")}</dt><dd>{formatMoney(cart.service_fee_amount, cart.currency ?? "")}</dd></div>}
              <div className="flex justify-between text-base font-bold"><dt>{tr("Total to pay")}</dt><dd>{formatMoney(cart.payable_amount, cart.currency ?? "")}</dd></div>
            </dl>
            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
            <Button type="submit" className="w-full" disabled={busy || !cart.can_checkout}>{tr("Place order and pay")}</Button>
            {!cart.can_checkout && <p className="text-xs text-muted-foreground">{tr("Remove the items that cannot be bought to continue.")}</p>}
          </form>
        </div>
      )}
    </div>
  );
}
