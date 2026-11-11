"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

import { CaptchaBox, Honeypot, useHumanCheck } from "@/components/security/human-check";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { LanguageSwitcher, tr } from "@/lib/i18n";
import { applyAsPartner, getOpenStores } from "@/services/auth.service";

export default function PartnerSignupPage() {
  return (
    <Suspense>
      <PartnerSignupForm />
    </Suspense>
  );
}

type Store = { name: string; slug: string; market: string | null };

/** A business asks to join a store as a service partner. It gets access only after a reviewer approves. */
function PartnerSignupForm() {
  const params = useSearchParams();
  const [stores, setStores] = useState<Store[]>([]);
  const [store, setStore] = useState(params.get("store") ?? "");
  const [form, setForm] = useState({
    business_name: "", contact_name: "", email: "", phone: "", city: "", description: "", password: "", password_confirmation: "",
  });
  const [coords, setCoords] = useState<{ latitude: number; longitude: number } | null>(null);
  const [locationNote, setLocationNote] = useState<string | null>(null);
  const [website, setWebsite] = useState(""); // honeypot
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const human = useHumanCheck();

  useEffect(() => {
    getOpenStores().then((list) => {
      setStores(list);
      setStore((current) => current || list[0]?.slug || "");
    }).catch(() => undefined);
  }, []);

  // The store's country is the sensible default for the business's country, until the applicant types their own.
  const [countryChoice, setCountryChoice] = useState<string | null>(null);
  const country = countryChoice ?? stores.find((s) => s.slug === store)?.market ?? "SA";

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  function shareLocation() {
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setCoords({ latitude: pos.coords.latitude, longitude: pos.coords.longitude });
        setLocationNote(tr("Location saved. Customers near you will be matched with your business."));
      },
      () => setLocationNote(tr("Location permission was denied.")),
    );
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await applyAsPartner({
        ...form,
        country,
        store,
        description: form.description || undefined,
        ...(coords ?? {}),
        signup_token: human.token,
        website,
        captcha_token: human.captchaToken,
      });
      setDone(true);
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-muted px-4 py-10">
      <div className="absolute end-4 top-4">
        <LanguageSwitcher />
      </div>

      <Card className="w-full max-w-xl">
        <CardHeader>
          <CardTitle className="text-center text-2xl font-black tracking-tight">{tr("Become a partner")}</CardTitle>
          <p className="text-center text-sm text-muted-foreground">{tr("Receive quote requests from nearby customers. Your application is reviewed before you get access.")}</p>
        </CardHeader>
        <CardContent>
          {done ? (
            <div className="space-y-4 text-center" role="status">
              <p className="rounded-xl bg-accent p-4 font-semibold text-accent-foreground">{tr("Application received. You can sign in as soon as it is approved.")}</p>
              <p className="text-sm text-muted-foreground">{tr("Use the email and password you just chose. Until then, signing in will tell you the application is under review.")}</p>
              <Link href="/login" className="underline">{tr("Sign in")}</Link>
            </div>
          ) : (
            <form onSubmit={onSubmit} className="space-y-4">
              <div>
                <Label htmlFor="store">{tr("Store you want to join")}</Label>
                <select id="store" required className="h-10 w-full rounded-[10px] border border-input bg-background px-3" value={store} onChange={(e) => setStore(e.target.value)}>
                  {stores.map((s) => <option key={s.slug} value={s.slug}>{s.name}</option>)}
                </select>
              </div>
              <div>
                <Label htmlFor="business_name">{tr("Business name")}</Label>
                <Input id="business_name" required value={form.business_name} onChange={set("business_name")} />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label htmlFor="contact_name">{tr("Your name")}</Label>
                  <Input id="contact_name" required autoComplete="name" value={form.contact_name} onChange={set("contact_name")} />
                </div>
                <div>
                  <Label htmlFor="phone">{tr("Mobile number")}</Label>
                  <Input id="phone" type="tel" required dir="ltr" autoComplete="tel" value={form.phone} onChange={set("phone")} />
                </div>
              </div>
              <div>
                <Label htmlFor="email">{tr("Email")}</Label>
                <Input id="email" type="email" required autoComplete="email" value={form.email} onChange={set("email")} />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label htmlFor="city">{tr("City")}</Label>
                  <Input id="city" required value={form.city} onChange={set("city")} />
                </div>
                <div>
                  <Label htmlFor="country">{tr("Country code")}</Label>
                  <Input id="country" required maxLength={2} dir="ltr" value={country} onChange={(e) => setCountryChoice(e.target.value.toUpperCase())} />
                </div>
              </div>
              <div>
                <Label htmlFor="description">{tr("What does your business do? (optional)")}</Label>
                <textarea id="description" rows={3} maxLength={400} className="w-full rounded-[10px] border border-input px-3 py-2" value={form.description} onChange={set("description")} />
              </div>
              <div>
                <Button type="button" variant="outline" size="sm" onClick={shareLocation}>{tr("Use my location")}</Button>
                <p className="mt-1 text-xs text-muted-foreground">{locationNote ?? tr("Optional now, but customers can only be matched with you once your location is set.")}</p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label htmlFor="password">{tr("Password")}</Label>
                  <Input id="password" type="password" minLength={10} required autoComplete="new-password" value={form.password} onChange={set("password")} />
                </div>
                <div>
                  <Label htmlFor="password_confirmation">{tr("Confirm password")}</Label>
                  <Input id="password_confirmation" type="password" required autoComplete="new-password" value={form.password_confirmation} onChange={set("password_confirmation")} />
                </div>
              </div>
              <Honeypot value={website} onChange={setWebsite} />
              <CaptchaBox challenge={human.challenge} onToken={human.setCaptchaToken} />
              {error && <p className="text-sm text-red-500" role="alert">{error}</p>}
              <Button type="submit" className="w-full" size="lg" disabled={busy || !human.ready || !store}>
                {human.waiting ? tr("Please wait a moment…") : tr("Send application")}
              </Button>
              <p className="text-center text-sm">
                {tr("Just want to buy a service?")} <Link href={store ? `/register?store=${encodeURIComponent(store)}` : "/register"} className="underline">{tr("Create a customer account")}</Link>
              </p>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
