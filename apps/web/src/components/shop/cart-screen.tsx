"use client";

import { takePromo } from "@/lib/marketing";
import { TrackEvent } from "@/components/marketing/marketing-scripts";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { setGuestContact } from "@/lib/guest";
import { me } from "@/services/auth.service";
import type { User } from "@/types/auth";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage, fieldErrors } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { addAddress, getAddresses, type Address } from "@/services/ops.service";
import { applyPromo, checkoutCart, getCart, removePromo, payPurchase, removeCartItem, setCartQuantity, type CartView } from "@/services/cart.service";
import { LoadingBlock } from "@/components/ui/loading-block";

const MAX = 50;

/** The cart: set quantities, give the delivery address, and pay once for everything (even from several sellers). */
/**
 * The cart and checkout for everyone: a signed-in customer, or a shopper with no account (a quiet guest session made when they added their first
 * item). Opened from the shop (/s/<store>/cart) or from the dashboard; never sends anyone to sign in.
 */
export function CartScreen({ slug }: { slug?: string }) {
  const [user, setUser] = useState<User | null>(null);
  useEffect(() => { me().then(setUser).catch(() => setUser(null)); }, []);
  const [email, setEmail] = useState("");
  const router = useRouter();
  const [cart, setCart] = useState<CartView | null>(null);
  // The last delivery address used on this device comes back ready, so a repeat buyer does not retype it.
  const [form, setForm] = useState(() => {
    const base = { name: "", phone: "", line1: "", city: "" };
    try { return { ...base, ...(JSON.parse(localStorage.getItem("last-address") ?? "{}") as Partial<typeof base>) }; } catch { return base; }
  });
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState<Address[]>([]);
  const [saveIt, setSaveIt] = useState(true);
  const [code, setCode] = useState("");
  const [payMethod, setPayMethod] = useState<"card" | "wallet" | "cod">("card");
  const [usePoints, setUsePoints] = useState(false);
  const [speed, setSpeed] = useState<"standard" | "express">("standard");
  const [day, setDay] = useState("");
  const [gift, setGift] = useState("");
  const [startedAt] = useState(() => Date.now());
  const dayAfter = (n: number) => new Date(startedAt + n * 86_400_000).toISOString().slice(0, 10);
  const [promoErr, setPromoErr] = useState<string | null>(null);
  useEffect(() => {
    getAddresses().then((list) => {
      setSaved(list);
      const d = list.find((a) => a.is_default);
      if (d) setForm({ name: d.name, phone: d.phone, line1: d.line1, city: d.city });
    }).catch(() => {});
  }, []);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => getCart().then(async (c) => {
    // A link with ?promo=CODE puts the code on the cart for the shopper (once).
    const code = c.items.length > 0 && !c.promo_code ? takePromo() : null;
    if (code) { try { c = await applyPromo(code); } catch { /* the code does not apply: the shopper can still type another */ } }
    setCart(c);
  }).catch(() => setCart({ items: [], currency: null, items_amount: 0, service_fee_amount: 0, shipping_amount: 0, discount_amount: 0, promo_code: null, promo_problem: null, eta_days: null, free_shipping_over: null, payable_amount: 0, can_checkout: false })), []);
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
      if (user?.is_guest) await setGuestContact(email, form.name);
      const purchase = await checkoutCart(form, { delivery_speed: speed, ...(day ? { preferred_day: day } : {}), ...(gift.trim() ? { gift_note: gift.trim() } : {}), ...(usePoints && cart?.points?.max_use ? { use_points: cart.points.max_use } : {}) },
        (cart?.items ?? []).map((l) => `${l.id}x${l.quantity}`).join(","));
      try { localStorage.setItem("last-address", JSON.stringify(form)); } catch { /* storage unavailable */ }
      if (saveIt && !saved.some((a) => a.line1 === form.line1 && a.city === form.city)) addAddress({ ...form, label: null }).catch(() => {});
      const paid = await payPurchase(purchase.id, cart && !cart.cod_available && payMethod === "cod" ? "card" : payMethod);
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

  if (cart === null) return <LoadingBlock />;
  const pointsOff = usePoints && cart.points ? cart.points.max_use * cart.points.value : 0;
  const extra = speed === "express" ? (cart.delivery_options ?? []).find((o) => o.key === "express")?.extra_fee ?? 0 : 0;

  return (
    <div>
      <PageHeader title={tr("Your cart")} />
      {cart.items.length > 0 && <TrackEvent name="begin_checkout" data={{ value: cart.items_amount, currency: cart.currency ?? undefined, ids: cart.items.map((l) => l.product_id) }} />}
      {cart.items.length === 0 ? (
        <EmptyState>{tr("Your cart is empty.")} <Link href={slug ? `/s/${slug}` : "/"} className="font-medium underline underline-offset-2">{tr("Keep shopping")}</Link></EmptyState>
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
            {saved.length > 0 && (
              <select className="mt-2 h-10 w-full rounded-[10px] border px-2 text-sm" aria-label={tr("Saved addresses")} data-testid="saved-address" defaultValue={saved.find((a) => a.is_default)?.id ?? ""} onChange={(e) => { const a = saved.find((x) => String(x.id) === e.target.value); if (a) setForm({ name: a.name, phone: a.phone, line1: a.line1, city: a.city }); }}>
                <option value="">{tr("New address")}</option>
                {saved.map((a) => <option key={a.id} value={a.id}>{a.label || a.name} · {a.line1}</option>)}
              </select>
            )}
            {user?.is_guest && (
              <div data-testid="guest-email"><Label htmlFor="c-email">{tr("Your e-mail (we send your receipt and updates here)")}</Label><Input id="c-email" type="email" required autoComplete="email" dir="ltr" value={email} onChange={(e) => setEmail(e.target.value)} /></div>
            )}
            <div><Label htmlFor="c-name">{tr("Full name")}</Label><Input id="c-name" required value={form.name} onChange={set("name")} />{err("name")}</div>
            <div><Label htmlFor="c-phone">{tr("Mobile number")}</Label><Input id="c-phone" required inputMode="tel" value={form.phone} onChange={set("phone")} />{err("phone")}</div>
            <div><Label htmlFor="c-line1">{tr("Address")}</Label><Input id="c-line1" required value={form.line1} onChange={set("line1")} />{err("line1")}</div>
            <div><Label htmlFor="c-city">{tr("City")}</Label><Input id="c-city" required value={form.city} onChange={set("city")} />{err("city")}</div>

            <div className="grid gap-1" data-testid="promo">
              {cart.promo_code ? (
                <p className="flex items-center justify-between text-sm"><span>{tr("Code")} <b>{cart.promo_code}</b></span><Button type="button" size="sm" variant="outline" onClick={async () => setCart(await removePromo())}>{tr("Remove")}</Button></p>
              ) : (
                <div className="flex gap-2">
                  <Input aria-label={tr("Discount code")} placeholder={tr("Discount code")} value={code} onChange={(e) => setCode(e.target.value)} />
                  <Button type="button" variant="outline" disabled={!code.trim()} onClick={async () => { setPromoErr(null); try { setCart(await applyPromo(code)); setCode(""); } catch (e) { setPromoErr(errorMessage(e)); } }}>{tr("Apply")}</Button>
                </div>
              )}
              {(promoErr || cart.promo_problem) && <p className="text-sm text-red-600" role="alert">{promoErr ?? cart.promo_problem}</p>}
            </div>

            <dl className="space-y-1 border-t pt-3 text-sm">
              <div className="flex justify-between"><dt>{tr("Items")}</dt><dd>{formatMoney(cart.items_amount, cart.currency ?? "")}</dd></div>
              {pointsOff > 0 && <div className="flex justify-between text-green-700" data-testid="points-discount"><dt>{tr("Points")}</dt><dd>−{formatMoney(pointsOff, cart.currency ?? "")}</dd></div>}
              {cart.discount_amount > 0 && <div className="flex justify-between text-green-700" data-testid="discount"><dt>{tr("Discount")}</dt><dd>−{formatMoney(cart.discount_amount, cart.currency ?? "")}</dd></div>}
              <div className="flex justify-between" data-testid="delivery"><dt>{tr("Delivery")}</dt><dd>{cart.shipping_amount + extra > 0 ? formatMoney(cart.shipping_amount + extra, cart.currency ?? "") : tr("Free")}</dd></div>
              {cart.service_fee_amount > 0 && <div className="flex justify-between" data-testid="platform-fee"><dt>{tr("Platform fee")}</dt><dd>{formatMoney(cart.service_fee_amount, cart.currency ?? "")}</dd></div>}
              <div className="flex justify-between text-base font-bold"><dt>{tr("Total to pay")}</dt><dd data-testid="payable">{formatMoney(cart.payable_amount + extra - pointsOff, cart.currency ?? "")}</dd></div>
            </dl>
            {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
            {cart.eta_days && <p className="text-sm text-muted-foreground" data-testid="eta">{tr("Usually delivered in about {days} days.", { days: cart.eta_days })}</p>}
            {cart.shipping_amount > 0 && cart.free_shipping_over && <p className="text-sm text-muted-foreground">{tr("Delivery is free for a seller's part of the cart worth {amount} or more.", { amount: formatMoney(cart.free_shipping_over, cart.currency ?? "") })}</p>}
            {(cart.delivery_options ?? []).length > 0 && (
              <fieldset className="grid gap-2 text-sm" data-testid="delivery-choices">
                <legend className="mb-1 font-semibold">{tr("How fast, and when?")}</legend>
                {cart.delivery_options!.map((o) => (
                  <label key={o.key} className="flex items-start gap-2 rounded-lg border p-2">
                    <input type="radio" name="speed" className="mt-1" checked={speed === o.key} onChange={() => setSpeed(o.key)} />
                    <span>
                      <b>{o.key === "express" ? tr("Express delivery") : tr("Standard delivery")}</b>
                      {o.eta_days ? <span className="text-muted-foreground"> · {tr("about {days} days", { days: o.eta_days })}</span> : null}
                      {o.extra_fee > 0 && <span className="block text-muted-foreground">+ {formatMoney(o.extra_fee, cart.currency ?? "")}</span>}
                    </span>
                  </label>
                ))}
                <label className="text-xs text-muted-foreground">{tr("Preferred delivery day (optional)")}
                  <input type="date" className="mt-1 h-10 w-full rounded-[10px] border bg-background px-2 text-sm text-foreground" min={dayAfter(1)} max={dayAfter(14)} value={day} onChange={(e) => setDay(e.target.value)} data-testid="preferred-day" />
                </label>
                <label className="text-xs text-muted-foreground">{tr("Gift note (optional)")}
                  <textarea className="mt-1 w-full rounded-[10px] border bg-background p-2 text-sm text-foreground" rows={2} maxLength={200} placeholder={tr("A message for the person who receives it")} value={gift} onChange={(e) => setGift(e.target.value)} data-testid="gift-note" />
                </label>
              </fieldset>
            )}
            {cart.points && cart.points.max_use > 0 && (
              <label className="flex items-start gap-2 rounded-lg border p-2 text-sm" data-testid="use-points">
                <input type="checkbox" className="mt-1" checked={usePoints} onChange={(e) => setUsePoints(e.target.checked)} />
                <span>
                  <b>{tr("Use {n} points", { n: cart.points.max_use })}</b>
                  <span className="block text-muted-foreground">{tr("Takes {amount} off. You have {balance} points.", { amount: formatMoney(cart.points.max_use * cart.points.value, cart.currency ?? ""), balance: cart.points.balance })}</span>
                </span>
              </label>
            )}
            <fieldset className="grid gap-1 text-sm" data-testid="pay-method">
              <legend className="mb-1 font-semibold">{tr("How do you want to pay?")}</legend>
              {([["card", "Card (secure payment page)"], ["wallet", "Mobile wallet"]] as const).map(([v, label]) => (
                <label key={v} className="flex items-center gap-2"><input type="radio" name="pay" checked={payMethod === v} onChange={() => setPayMethod(v)} />{tr(label)}</label>
              ))}
              {cart.cod_available && <label className="flex items-center gap-2"><input type="radio" name="pay" checked={payMethod === "cod"} onChange={() => setPayMethod("cod")} />{tr("Cash on delivery")}</label>}
            </fieldset>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={saveIt} onChange={(e) => setSaveIt(e.target.checked)} />{tr("Save this address for next time")}</label>
            <Button type="submit" className="w-full" disabled={busy || !cart.can_checkout}>{payMethod === "cod" && cart.cod_available ? tr("Place order (pay on delivery)") : tr("Place order and pay")}</Button>
            {!cart.can_checkout && <p className="text-xs text-muted-foreground">{tr("Remove the items that cannot be bought to continue.")}</p>}
          </form>
        </div>
      )}
    </div>
  );
}
