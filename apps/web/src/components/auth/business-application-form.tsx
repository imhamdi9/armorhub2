"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";

import { CaptchaBox, Honeypot, useHumanCheck } from "@/components/security/human-check";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage, fieldErrors } from "@/lib/errors";
import { LanguageSwitcher, tr } from "@/lib/i18n";
import { applyAsBusiness, getMarketplaces, getOpenStores, type BusinessType } from "@/services/auth.service";

const MAX_BYTES = 5 * 1024 * 1024;
const ACCEPT = ".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png";

interface DocSpec {
  kind: string;
  label: string;
  hint: string;
  required: boolean;
}

/** What each kind of business must upload so a reviewer can verify it. */
const DOCS: Record<BusinessType, DocSpec[]> = {
  partner: [
    { kind: "id", label: "Owner ID or passport", hint: "A clear photo or scan of the owner's ID.", required: true },
    { kind: "commercial_registration", label: "Commercial registration", hint: "Your commercial register extract.", required: true },
    { kind: "license", label: "Professional licence or certificate", hint: "Installer, trade or municipality licence (optional).", required: false },
  ],
  seller: [
    { kind: "id", label: "Owner ID or passport", hint: "A clear photo or scan of the owner's ID.", required: true },
    { kind: "commercial_registration", label: "Commercial registration", hint: "Your commercial register extract.", required: true },
    { kind: "tax_certificate", label: "Tax / VAT certificate", hint: "Your tax registration certificate.", required: true },
    { kind: "license", label: "Brand or trade licence", hint: "Brand authorisation, import or trade licence (optional).", required: false },
  ],
};

type Target = { value: string; label: string; market: string | null; info?: string };

/**
 * One application form for both kinds of business. A partner joins a store to do service work; a seller joins a
 * marketplace to sell its own products. Both upload documents that a reviewer checks before any access is granted.
 */
export function BusinessApplicationForm({ type }: { type: BusinessType }) {
  const params = useSearchParams();
  const [targets, setTargets] = useState<Target[]>([]);
  const [target, setTarget] = useState(params.get("store") ?? params.get("marketplace") ?? "");
  const [form, setForm] = useState({
    business_name: "", contact_name: "", email: "", phone: "", city: "", registration_number: "", tax_number: "", description: "", password: "", password_confirmation: "",
  });
  const [countryChoice, setCountryChoice] = useState<string | null>(null);
  const [files, setFiles] = useState<Record<string, File | undefined>>({});
  const [coords, setCoords] = useState<{ latitude: number; longitude: number } | null>(null);
  const [locationNote, setLocationNote] = useState<string | null>(null);
  const [website, setWebsite] = useState(""); // honeypot
  const [error, setError] = useState<string | null>(null);
  const [fieldErrs, setFieldErrs] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  const human = useHumanCheck();
  const isSeller = type === "seller";

  useEffect(() => {
    const load = isSeller
      ? getMarketplaces().then((l) => l.map((m) => ({ value: m.key, label: m.name, market: m.market, info: [m.country, m.currency, m.industries?.join(", ")].filter(Boolean).join(" · ") })))
      : getOpenStores().then((l) => l.map((s) => ({ value: s.slug, label: s.name, market: s.market })));
    load.then((list) => {
      setTargets(list);
      setTarget((current) => current || list[0]?.value || "");
    }).catch(() => undefined);
  }, [isSeller]);

  const country = countryChoice ?? targets.find((t) => t.value === target)?.market ?? "SA";
  const clearErr = (key: string) => setFieldErrs((f) => (key in f ? Object.fromEntries(Object.entries(f).filter(([k]) => k !== key)) : f));
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => { clearErr(key); setForm((f) => ({ ...f, [key]: e.target.value })); };
  /** The server's message for one field, shown right under it. */
  const fe = (key: string) => (fieldErrs[key] ? <p id={`err-${key}`} className="mt-1 text-xs text-red-600" role="alert">{fieldErrs[key]}</p> : null);

  function pick(kind: string, file: File | undefined) {
    if (file && file.size > MAX_BYTES) {
      setError(tr("Each file must be 5 MB or smaller."));
      return;
    }
    setError(null);
    setFiles((f) => ({ ...f, [kind]: file }));
  }

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
    setFieldErrs({});
    const missing = DOCS[type].find((d) => d.required && !files[d.kind]);
    if (missing) {
      setError(tr("Please upload: {name}", { name: tr(missing.label) }));
      return;
    }

    const data = new FormData();
    data.set("type", type);
    data.set(isSeller ? "marketplace" : "store", target);
    data.set("country", country);
    for (const [key, value] of Object.entries(form)) if (value !== "") data.set(key, value);
    if (coords) {
      data.set("latitude", String(coords.latitude));
      data.set("longitude", String(coords.longitude));
    }
    for (const [kind, file] of Object.entries(files)) if (file) data.set(`documents[${kind}]`, file);
    data.set("signup_token", human.token);
    data.set("website", website);
    if (human.captchaToken) data.set("captcha_token", human.captchaToken);

    setBusy(true);
    try {
      await applyAsBusiness(data);
      setDone(true);
    } catch (err) {
      const fields = fieldErrors(err);
      // The server names fields like documents.id or marketplace; map them onto what is on screen.
      const mapped: Record<string, string> = {};
      for (const [k, v] of Object.entries(fields)) mapped[k.replace(/^documents\./, "doc-").replace(/^(marketplace|store)$/, "target")] = v;
      setFieldErrs(mapped);
      setError(Object.keys(mapped).length > 0 ? tr("Please check the highlighted fields.") : errorMessage(err));
      human.refresh();
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-muted px-4 py-10">
      <div className="absolute end-4 top-4"><LanguageSwitcher /></div>

      <Card className="w-full max-w-2xl">
        <CardHeader>
          <CardTitle aria-level={1} className="text-center text-2xl font-black tracking-tight">{isSeller ? tr("Sell on ArmorHub") : tr("Become a partner")}</CardTitle>
          <p className="text-center text-sm text-muted-foreground">
            {isSeller
              ? tr("List your own products in a marketplace and sell them to its customers. We verify your business before you can sell.")
              : tr("Receive quote requests from nearby customers. Your application is reviewed before you get access.")}
          </p>
        </CardHeader>
        <CardContent>
          {done ? (
            <div className="space-y-4 text-center" role="status">
              <p className="rounded-xl bg-accent p-4 font-semibold text-accent-foreground">{tr("Application received. You can sign in as soon as it is approved.")}</p>
              <p className="text-sm text-muted-foreground">{tr("We will check your documents. Until then, signing in will tell you the application is under review.")}</p>
              <Link href="/login" className="underline">{tr("Sign in")}</Link>
            </div>
          ) : (
            <form onSubmit={onSubmit} className="space-y-4">
              <div>
                <Label htmlFor="target">{isSeller ? tr("Marketplace you want to sell in") : tr("Store you want to join")}</Label>
                <select id="target" required className="h-10 w-full rounded-[10px] border border-input bg-background px-3" value={target} onChange={(e) => setTarget(e.target.value)}>
                  {targets.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
                {fe("target")}
                {isSeller && (
                  <p className="mt-1 text-xs text-slate">
                    {targets.find((t) => t.value === target)?.info ? `${targets.find((t) => t.value === target)?.info}. ` : ""}
                    {tr("Your seller account belongs to this market only. To sell in another country, apply there separately with its own e-mail; it is a separate account under that market's terms.")}
                  </p>
                )}
              </div>

              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label htmlFor="business_name">{tr("Business name")}</Label>
                  <Input id="business_name" required value={form.business_name} onChange={set("business_name")} />
                  {fe("business_name")}
                </div>
                <div>
                  <Label htmlFor="registration_number">{tr("Commercial registration number")}</Label>
                  <Input id="registration_number" required dir="ltr" value={form.registration_number} onChange={set("registration_number")} />
                  {fe("registration_number")}
                </div>
                <div>
                  <Label htmlFor="tax_number">{isSeller ? tr("Tax registration number") : tr("Tax registration number (optional)")}</Label>
                  <Input id="tax_number" required={isSeller} dir="ltr" value={form.tax_number} onChange={set("tax_number")} />
                  {fe("tax_number")}
                </div>
                <div>
                  <Label htmlFor="contact_name">{tr("Your name")}</Label>
                  <Input id="contact_name" required autoComplete="name" value={form.contact_name} onChange={set("contact_name")} />
                  {fe("contact_name")}
                </div>
                <div>
                  <Label htmlFor="phone">{tr("Mobile number")}</Label>
                  <Input id="phone" type="tel" required dir="ltr" autoComplete="tel" value={form.phone} onChange={set("phone")} />
                  {fe("phone")}
                </div>
                <div>
                  <Label htmlFor="email">{tr("Email")}</Label>
                  <Input id="email" type="email" required autoComplete="email" value={form.email} onChange={set("email")} />
                  {fe("email")}
                </div>
                <div>
                  <Label htmlFor="city">{tr("City")}</Label>
                  <Input id="city" required value={form.city} onChange={set("city")} />
                  {fe("city")}
                </div>
                <div>
                  <Label htmlFor="country">{tr("Country code")}</Label>
                  <Input id="country" required maxLength={2} dir="ltr" value={country} onChange={(e) => setCountryChoice(e.target.value.toUpperCase())} />
                  {fe("country")}
                </div>
              </div>

              <div>
                <Label htmlFor="description">{isSeller ? tr("What do you sell? (optional)") : tr("What does your business do? (optional)")}</Label>
                <textarea id="description" rows={3} maxLength={400} className="w-full rounded-[10px] border border-input px-3 py-2" value={form.description} onChange={set("description")} />
                {fe("description")}
              </div>

              {!isSeller && (
                <div>
                  <Button type="button" variant="outline" size="sm" onClick={shareLocation}>{tr("Use my location")}</Button>
                  <p className="mt-1 text-xs text-muted-foreground">{locationNote ?? tr("Optional now, but customers can only be matched with you once your location is set.")}</p>
                </div>
              )}

              <fieldset className="space-y-3 rounded-xl border p-4">
                <legend className="px-1 text-sm font-semibold">{tr("Verification documents")}</legend>
                <p className="text-xs text-muted-foreground">{tr("PDF, JPG or PNG, up to 5 MB each. Only our reviewers can open them.")}</p>
                {DOCS[type].map((d) => (
                  <div key={d.kind}>
                    <Label htmlFor={`doc-${d.kind}`}>{tr(d.label)} {d.required && <span className="text-red-500">*</span>}</Label>
                    <input id={`doc-${d.kind}`} type="file" accept={ACCEPT} className="block w-full text-sm file:me-3 file:rounded-full file:border file:border-forest file:bg-background file:px-4 file:py-1.5 file:text-sm file:font-semibold file:text-forest"
                      onChange={(e) => { clearErr(`doc-${d.kind}`); pick(d.kind, e.target.files?.[0]); }} />
                    <p className="text-xs text-muted-foreground">{files[d.kind] ? files[d.kind]?.name : tr(d.hint)}</p>
                    {fe(`doc-${d.kind}`)}
                  </div>
                ))}
              </fieldset>

              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label htmlFor="password">{tr("Password")}</Label>
                  <Input id="password" type="password" minLength={10} required autoComplete="new-password" value={form.password} onChange={set("password")} />
                  {fe("password")}
                </div>
                <div>
                  <Label htmlFor="password_confirmation">{tr("Confirm password")}</Label>
                  <Input id="password_confirmation" type="password" required autoComplete="new-password" value={form.password_confirmation} onChange={set("password_confirmation")} />
                  {fe("password_confirmation")}
                </div>
              </div>

              <Honeypot value={website} onChange={setWebsite} />
              <CaptchaBox challenge={human.challenge} onToken={human.setCaptchaToken} />
              {error && <p className="text-sm text-red-500" role="alert">{error}</p>}
              <Button type="submit" className="w-full" size="lg" disabled={busy || !human.ready || !target}>
                {human.waiting ? tr("Please wait a moment…") : tr("Send application")}
              </Button>
              <p className="text-center text-sm"><Link href="/signup" className="underline">{tr("Choose a different account type")}</Link></p>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
