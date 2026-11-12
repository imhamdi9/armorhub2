"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { resetPassword } from "@/services/auth.service";
import { LanguageSwitcher, tr } from "@/lib/i18n";

/** Opened from the emailed link: /reset-password?token=...&email=... */
export default function ResetPasswordPage() {
  return (
    <Suspense>
      <ResetPasswordForm />
    </Suspense>
  );
}

function ResetPasswordForm() {
  const params = useSearchParams();
  const token = params.get("token") ?? "";
  const email = params.get("email") ?? "";
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [done, setDone] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      await resetPassword({ token, email, password, password_confirmation: confirmation });
      setDone(true);
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-gray-100">
        <div className="absolute end-4 top-4">
          <LanguageSwitcher />
        </div>

      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle aria-level={1} className="text-center text-2xl">{tr("Choose a new password")}</CardTitle>
        </CardHeader>
        <CardContent>
          {done ? (
            <div className="space-y-4 text-center">
              <p className="text-green-600">{tr("Password updated.")}</p>
              <Link href="/login" className="underline">{tr("Sign in")}</Link>
            </div>
          ) : (
            <form onSubmit={onSubmit} className="space-y-4">
              <p className="text-sm text-muted-foreground">{email}</p>
              <div>
                <Label htmlFor="password">{tr("New password")}</Label>
                <Input id="password" type="password" minLength={8} required value={password} onChange={(e) => setPassword(e.target.value)} />
              </div>
              <div>
                <Label htmlFor="confirmation">{tr("Confirm password")}</Label>
                <Input id="confirmation" type="password" required value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
              </div>
              {error && <p className="text-sm text-red-500">{error}</p>}
              <Button type="submit" className="w-full" disabled={!token}>{tr("Update password")}</Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
