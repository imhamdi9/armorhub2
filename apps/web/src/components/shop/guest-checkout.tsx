"use client";

import Link from "next/link";
import { useEffect, useState } from "react";

import { ShopShell, SHOP_SHADOW } from "@/components/shop/shop-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { CaptchaBox, Honeypot, useHumanCheck } from "@/components/security/human-check";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";
import { imageSrc } from "@/lib/surfaces";
import { me } from "@/services/auth.service";
import { createGuestOrder, payGuestOrder } from "@/services/guest-orders.service";
import type { PublicProductPage } from "@/types/public";
import type { ShellStore } from "@/components/public/shell";

/**
 * Checkout for goods: no account is asked for. The buyer either pays as a guest (name, e-mail, mobile and address) or, if they like,
 * signs in or creates an account first. After paying they can still create an account with the same e-mail to keep their orders together.
 */
export function GuestCheckout({ store, data, phoneHint }: { store: ShellStore; data: PublicProductPage; phoneHint?: string }) {
  const p = data.product;
  const [signedIn, setSignedIn] = useState<string | null | undefined>(undefined);
  const [form, setForm] = useState({ name: "", email: "", phone: "", line1: "", line2: "", city: "", region: "", postal_code: "" });
  const [website, setWebsite] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fields, setFields] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const human = useHumanCheck();
  const image = imageSrc(p.images?.[0] ?? null);

  useEffect(() => { me().then((u) => setSignedIn(u.name)).catch(() => setSignedIn(null)); }, []);

  const set = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm((f) => ({ ...f, [k]: e.target.value }));
  const err = (k: string) => fields[k] && <p role="alert" className="mt-1 text-sm text-red-600">{fields[k]}</p>;

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null); setFields({}); setBusy(true);
    try {
      const order = await createGuestOrder({
        store: store.slug, product_id: p.id, name: form.name, email: form.email, phone: form.phone,
        address: { line1: form.line1, line2: form.line2 || undefined, city: form.city, region: form.region || undefined, postal_code: form.postal_code || undefined },
        signup_token: human.token, website, captcha_token: human.captchaToken,
      });
      const paid = await payGuestOrder(order.token);
      window.location.assign(paid.checkout_url ?? `/track/${order.token}?checkout=success`);
    } catch (x) {
      const e2 = x as { response?: { data?: { errors?: Record<string, string[]> } } };
      setFields(Object.fromEntries(Object.entries(e2.response?.data?.errors ?? {}).map(([k, v]) => [k, v[0]])));
      setError(errorMessage(x));
      human.refresh();
      setBusy(false);
    }
  }

  return (
    <ShopShell store={store} active="products">
      <div className="mx-auto mt-8 max-w-3xl">
        <h1 className="mb-6 text-center text-4xl tracking-[-0.05em]">{tr("Checkout")}</h1>

        <section className="mb-4 flex items-center gap-4 rounded-[28px] bg-white p-3" style={{ boxShadow: SHOP_SHADOW }} aria-label={tr("Your order")}>
          <div className="size-20 shrink-0 overflow-hidden rounded-[20px] bg-[#f2f4f5]">
            {/* eslint-disable-next-line @next/next/no-img-element -- the seller's own product photo */}
            {image ? <img src={image} alt="" className="size-full object-cover" /> : <div className="grid size-full place-items-center text-2xl text-[#5f5c5b]" aria-hidden="true">{p.name.slice(0, 1)}</div>}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-base">{p.name}</p>
            {p.seller && <p className="text-sm text-[#5f5c5b]">{tr("Sold by")} {p.seller}</p>}
          </div>
          <p className="text-xl tracking-[-0.04em]" data-testid="checkout-total">{formatMoney(p.from_price, p.currency, true)}</p>
        </section>

        {signedIn ? (
          <section className="rounded-[28px] bg-white p-6 text-center" style={{ boxShadow: SHOP_SHADOW }}>
            <p className="mb-4">{tr("You are signed in as")} <strong>{signedIn}</strong>.</p>
            <Link href={`/dashboard/orders/new?product=${p.id}`} className="btn-brand inline-flex h-12 items-center rounded-full px-8 text-base">{tr("Continue to payment")}</Link>
          </section>
        ) : (
          <>
            <section className="mb-4 rounded-[28px] bg-white p-5 text-sm" style={{ boxShadow: SHOP_SHADOW }} aria-label={tr("Account")}>
              <p>{tr("You do not need an account to order. Fill in your details below and pay.")} <span className="text-[#5f5c5b]">{tr("If you already have an account, you can")} <Link className="underline" href={`/login`}>{tr("sign in")}</Link> {tr("or")} <Link className="underline" href={`/register?store=${encodeURIComponent(store.slug)}&product=${p.id}`}>{tr("create one")}</Link> ({tr("optional")}).</span></p>
            </section>

            <form onSubmit={submit} className="space-y-4 rounded-[28px] bg-white p-6" style={{ boxShadow: SHOP_SHADOW }}>
              <h2 className="text-xl tracking-[-0.05em]">{tr("Your details")}</h2>
              <div><Label htmlFor="g-name">{tr("Full name")}</Label><Input id="g-name" required autoComplete="name" maxLength={120} value={form.name} onChange={set("name")} />{err("name")}</div>
              <div className="grid gap-4 md:grid-cols-2">
                <div><Label htmlFor="g-email">{tr("Email")}</Label><Input id="g-email" type="email" required autoComplete="email" value={form.email} onChange={set("email")} />{err("email")}<p className="mt-1 text-xs text-[#5f5c5b]">{tr("We send your receipt and order link here.")}</p></div>
                <div><Label htmlFor="g-phone">{tr("Mobile number")}</Label><Input id="g-phone" type="tel" required autoComplete="tel" dir="ltr" placeholder={phoneHint} value={form.phone} onChange={set("phone")} />{err("phone")}</div>
              </div>
              <h2 className="pt-2 text-xl tracking-[-0.05em]">{tr("Delivery address")}</h2>
              <div><Label htmlFor="g-line1">{tr("Street address")}</Label><Input id="g-line1" required autoComplete="address-line1" maxLength={200} value={form.line1} onChange={set("line1")} />{err("address.line1")}</div>
              <div><Label htmlFor="g-line2">{tr("Apartment, floor, landmark (optional)")}</Label><Input id="g-line2" autoComplete="address-line2" maxLength={200} value={form.line2} onChange={set("line2")} /></div>
              <div className="grid gap-4 md:grid-cols-3">
                <div><Label htmlFor="g-city">{tr("City")}</Label><Input id="g-city" required autoComplete="address-level2" maxLength={100} value={form.city} onChange={set("city")} />{err("address.city")}</div>
                <div><Label htmlFor="g-region">{tr("Governorate or region (optional)")}</Label><Input id="g-region" autoComplete="address-level1" maxLength={100} value={form.region} onChange={set("region")} /></div>
                <div><Label htmlFor="g-postal">{tr("Postal code (optional)")}</Label><Input id="g-postal" autoComplete="postal-code" maxLength={20} dir="ltr" value={form.postal_code} onChange={set("postal_code")} /></div>
              </div>

              <Honeypot value={website} onChange={setWebsite} />
              <CaptchaBox challenge={human.challenge} onToken={human.setCaptchaToken} />
              {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
              <p className="text-xs text-[#5f5c5b]">{tr("You pay securely online. The seller ships your order and you follow every step from a private link we e-mail you.")}</p>
              <Button type="submit" size="lg" disabled={busy || !human.ready} className="btn-brand h-12 w-full rounded-full text-base">{busy ? tr("Please wait...") : `${tr("Pay")} ${formatMoney(p.from_price, p.currency, true)}`}</Button>
            </form>
          </>
        )}
      </div>
    </ShopShell>
  );
}
