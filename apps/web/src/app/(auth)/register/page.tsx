"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { register } from "@/services/auth.service";
import { LanguageSwitcher, tr } from "@/lib/i18n";

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    company_id: "",
  });
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }));

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await register({ ...form, company_id: Number(form.company_id) });
      localStorage.setItem("token", res.token);
      router.push("/dashboard");
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
              <Label htmlFor="password">{tr("Password")}</Label>
              <Input id="password" type="password" minLength={8} required value={form.password} onChange={set("password")} />
            </div>
            <div>
              <Label htmlFor="password_confirmation">{tr("Confirm password")}</Label>
              <Input id="password_confirmation" type="password" required value={form.password_confirmation} onChange={set("password_confirmation")} />
            </div>
            <div>
              <Label htmlFor="company_id">{tr("Company ID")}</Label>
              <Input id="company_id" type="number" min="1" required value={form.company_id} onChange={set("company_id")} />
              <p className="mt-1 text-xs text-muted-foreground">
                {tr("Given to you by the business you are ordering from.")}
              </p>
            </div>
            {error && <p className="text-sm text-red-500">{error}</p>}
            <Button type="submit" className="w-full" disabled={busy}>
              {tr("Sign up")}
            </Button>
            <p className="text-center text-sm">
              {tr("Already have an account?")}<Link href="/login" className="underline">{tr("Sign in")}</Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
