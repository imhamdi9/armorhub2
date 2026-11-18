"use client";

import { useParams } from "next/navigation";
import { useEffect, useState } from "react";

import { api } from "@/services/api";
import { errorMessage } from "@/lib/errors";
import { formatMoney, tr } from "@/lib/i18n";

interface Charge { ref: string; amount: number; currency: string; status: "pending" | "paid" | "cancelled"; return_url: string; error: string | null }

/**
 * The hosted test checkout of the sandbox payment provider: it looks and behaves like a payment provider's own page (a card or wallet code, a decline,
 * a retry, a way back), with test cards instead of real ones. No real money, no real card numbers.
 */
export default function SandboxCheckoutPage() {
  const ref = useParams<{ ref: string }>().ref;
  const [charge, setCharge] = useState<Charge | null | undefined>(undefined);
  const [method, setMethod] = useState<"card" | "wallet">("card");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<string | null>(null);

  useEffect(() => { api.get<Charge>(`/public/sandbox/${ref}`).then((r) => setCharge(r.data)).catch(() => setCharge(null)); }, [ref]);

  if (charge === undefined) return <p className="p-8 text-center text-muted-foreground">{tr("Loading...")}</p>;
  if (!charge) return <p className="p-8 text-center">{tr("This payment page is not available.")}</p>;

  const back = (url: string) => { window.location.assign(url); };

  async function submit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!charge) return;
    setBusy(true);
    setError(null);
    const f = new FormData(e.currentTarget);
    try {
      const r = await api.post<{ status: string; return_url: string }>(`/public/sandbox/${ref}/pay`, method === "card"
        ? { method, name: f.get("name"), number: f.get("number"), expiry: f.get("expiry"), cvc: f.get("cvc") }
        : { method, phone: f.get("phone"), code: f.get("code") });
      setDone(r.data.status);
      setTimeout(() => back(r.data.return_url), 1200);
    } catch (x) {
      const data = (x as { response?: { data?: { status?: string; return_url?: string; message?: string } } }).response?.data;
      setError(data?.message ? tr(data.message) : errorMessage(x));
      if (data?.status === "cancelled" && data.return_url) setTimeout(() => back(data.return_url as string), 1500);
      setBusy(false);
    }
  }

  async function cancel() {
    const r = await api.post<{ return_url: string }>(`/public/sandbox/${ref}/cancel`).catch(() => null);
    back(r?.data.return_url ?? charge?.return_url ?? "/");
  }

  const input = "h-11 w-full rounded-[10px] border border-input bg-white px-3 text-sm";

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-4 p-4" data-testid="sandbox-checkout">
      <div className="rounded-2xl border bg-white p-6 shadow-sm">
        <p className="mb-1 inline-block rounded-full bg-amber-100 px-3 py-0.5 text-xs font-semibold text-amber-900">{tr("Test mode: no real money")}</p>
        <h1 className="text-xl font-bold">{tr("Pay ArmorHub")}</h1>
        <p className="mb-4 text-3xl font-extrabold" data-testid="amount">{formatMoney(charge.amount, charge.currency)}</p>

        {charge.status !== "pending" || done ? (
          <p className="rounded-lg bg-muted p-3 text-sm" role="status" data-testid="result">
            {(done ?? charge.status) === "paid" ? tr("Payment received. Taking you back...") : tr("This payment was cancelled. Taking you back...")}
          </p>
        ) : (
          <>
            <div className="mb-4 grid grid-cols-2 gap-2" role="tablist">
              {(["card", "wallet"] as const).map((m) => (
                <button key={m} type="button" role="tab" aria-selected={method === m} onClick={() => { setMethod(m); setError(null); }} className={`rounded-full border px-4 py-2 text-sm font-semibold ${method === m ? "bg-primary text-primary-foreground" : ""}`}>{m === "card" ? tr("Card") : tr("Mobile wallet")}</button>
              ))}
            </div>
            <form onSubmit={submit} className="grid gap-3">
              {method === "card" ? (
                <>
                  <input name="name" className={input} placeholder={tr("Name on card")} required defaultValue="Test Customer" />
                  <input name="number" className={input} placeholder="4242 4242 4242 4242" inputMode="numeric" dir="ltr" required defaultValue="4242 4242 4242 4242" />
                  <div className="grid grid-cols-2 gap-3">
                    <input name="expiry" className={input} placeholder="MM / YY" dir="ltr" required defaultValue="12 / 39" />
                    <input name="cvc" className={input} placeholder="CVC" inputMode="numeric" dir="ltr" required defaultValue="123" />
                  </div>
                  <p className="text-xs text-muted-foreground">{tr("Approved: 4242 4242 4242 4242. Declined: 4000 0000 0000 0002. Not enough money: 4000 0000 0000 9995.")}</p>
                </>
              ) : (
                <>
                  <input name="phone" className={input} placeholder={tr("Wallet phone number")} inputMode="tel" dir="ltr" required defaultValue="01012345678" />
                  <input name="code" className={input} placeholder={tr("Confirmation code")} inputMode="numeric" dir="ltr" required />
                  <p className="text-xs text-muted-foreground">{tr("In test mode the code is 123456.")}</p>
                </>
              )}
              {error && <p role="alert" className="text-sm text-red-600" data-testid="error">{error}</p>}
              <button type="submit" disabled={busy} className="h-12 rounded-full bg-primary font-semibold text-primary-foreground disabled:opacity-60">{busy ? tr("Please wait...") : `${tr("Pay")} ${formatMoney(charge.amount, charge.currency)}`}</button>
              <button type="button" onClick={cancel} className="text-sm text-muted-foreground underline">{tr("Cancel and go back")}</button>
            </form>
          </>
        )}
      </div>
    </main>
  );
}
