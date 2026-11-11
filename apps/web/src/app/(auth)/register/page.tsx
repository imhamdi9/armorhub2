"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { getPublicStoreName, register } from "@/services/auth.service";
import { CaptchaBox, Honeypot, useHumanCheck } from "@/components/security/human-check";
import { LanguageSwitcher, tr } from "@/lib/i18n";

export default function RegisterPage() {
  return (
    <Suspense>
      <RegisterForm />
    </Suspense>
  );
}

function RegisterForm() {
  const router = useRouter();
  const params = useSearchParams();
  // Coming from a store's page: the store (and maybe a product) is already known.
  const storeSlug = params.get("store");
  const productId = params.get("product");
  const [storeName, setStoreName] = useState<string | null>(null);
  const [storeMissing, setStoreMissing] = useState(false);
  const [phoneHint, setPhoneHint] = useState<string | null>(null);

  useEffect(() => {
    if (!storeSlug) return;
    getPublicStoreName(storeSlug).then((s) => { setStoreName(s.name); if (s.market) setPhoneHint(`+${s.market.phone.dial} ${s.market.phone.example}`); }).catch(() => setStoreMissing(true));
  }, [storeSlug]);
  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    password: "",
    password_confirmation: "",
    company_id: "",
  });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [website, setWebsite] = useState(""); // honeypot
  const human = useHumanCheck();

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const { company_id, ...fields } = form;
      await register({
        ...fields,
        phone: fields.phone || undefined,
        ...(storeSlug ? { store: storeSlug } : { company_id: Number(company_id) }),
        signup_token: human.token,
        website,
        captcha_token: human.captchaToken,
      });
      router.push(productId ? `/dashboard/orders/new?product=${encodeURIComponent(productId)}` : "/dashboard");
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-gray-100">
        <div className="absolute end-4 top-4">
          <LanguageSwitcher />
        </div>

      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-center text-2xl">{tr("Create your account")}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <Label htmlFor="name">{tr("Name")}</Label>
              <Input id="name" required value={form.name} onChange={set("name")} />
            </div>
            <div>
              <Label htmlFor="email">{tr("Email")}</Label>
              <Input id="email" type="email" required value={form.email} onChange={set("email")} />
            </div>
            <div>
              <Label htmlFor="phone">{tr("Mobile number (optional)")}</Label>
              <Input id="phone" type="tel" inputMode="tel" dir="ltr" autoComplete="tel" placeholder={phoneHint ?? ""} value={form.phone} onChange={set("phone")} />
            </div>
            <div>
              <Label htmlFor="password">{tr("Password")}</Label>
              <Input id="password" type="password" minLength={8} required value={form.password} onChange={set("password")} />
            </div>
            <div>
              <Label htmlFor="password_confirmation">{tr("Confirm password")}</Label>
              <Input id="password_confirmation" type="password" required value={form.password_confirmation} onChange={set("password_confirmation")} />
            </div>
            {storeSlug ? (
              <p className={`rounded-lg border p-3 text-sm ${storeMissing ? "border-red-300 bg-red-50" : "bg-muted"}`}>
                {storeMissing ? tr("This store was not found.") : storeName ? tr("You are signing up with {store}.", { store: storeName }) : tr("Loading...")}
              </p>
            ) : (
            <div>
              <Label htmlFor="company_id">{tr("Company ID")}</Label>
              <Input id="company_id" type="number" min="1" required={!storeSlug} value={form.company_id} onChange={set("company_id")} />
              <p className="mt-1 text-xs text-muted-foreground">
                {tr("Given to you by the business you are ordering from.")}
              </p>
            </div>
            )}
            <Honeypot value={website} onChange={setWebsite} />
            <CaptchaBox challenge={human.challenge} onToken={human.setCaptchaToken} />
            {error && <p className="text-sm text-red-500">{error}</p>}
            <Button type="submit" className="w-full" disabled={busy || !human.ready || storeMissing}>
              {human.waiting ? tr("Please wait a moment…") : tr("Sign up")}
            </Button>
            <p className="text-center text-sm">
              {tr("Already have an account?")}<Link href="/login" className="underline">{tr("Sign in")}</Link>
            </p>
            <p className="text-center text-sm text-muted-foreground">
              {tr("Do you run a service business?")} <Link href={storeSlug ? `/partner-signup?store=${encodeURIComponent(storeSlug)}` : "/partner-signup"} className="underline">{tr("Become a partner")}</Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
