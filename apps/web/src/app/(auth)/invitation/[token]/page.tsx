"use client";

import Link from "next/link";
import { use, useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { LanguageSwitcher, tr } from "@/lib/i18n";
import { acceptInvitation, getInvitation } from "@/services/platform.service";

/** Where an invited person lands from the e-mail: they choose a name and a password and the account is ready. */
export default function InvitationPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = use(params);
  const [info, setInfo] = useState<{ email: string; role: string; inviter: string | null } | null | undefined>(undefined);
  const [form, setForm] = useState({ name: "", password: "" });
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  useEffect(() => { getInvitation(token).then(setInfo).catch(() => setInfo(null)); }, [token]);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try { await acceptInvitation(token, form); setDone(true); } catch (err) { setError(errorMessage(err)); }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-gray-100">
      <div className="absolute end-4 top-4"><LanguageSwitcher /></div>
      <Card className="w-full max-w-md">
        <CardHeader><CardTitle aria-level={1} className="text-center text-2xl">{tr("Join the team")}</CardTitle></CardHeader>
        <CardContent>
          {info === undefined && <div className="h-16 animate-pulse rounded-md bg-muted" role="status" aria-label={tr("Loading...")} />}
          {info === null && <p role="alert">{tr("This invitation is no longer valid. Ask for a new one.")}</p>}
          {done && <p role="status">{tr("Your account is ready.")} <Link className="underline" href="/login">{tr("Sign in")}</Link></p>}
          {info && !done && (
            <form onSubmit={onSubmit} className="space-y-4">
              <p className="text-sm text-muted-foreground">{info.inviter ? tr("{name} invited you.", { name: info.inviter }) : ""} {info.email}</p>
              <div><Label htmlFor="inv-name">{tr("Your name")}</Label><Input id="inv-name" required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></div>
              <div><Label htmlFor="inv-pass">{tr("Password")}</Label><Input id="inv-pass" type="password" required autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></div>
              {error && <p className="text-sm text-red-600" role="alert">{error}</p>}
              <Button type="submit" className="w-full">{tr("Create my account")}</Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
