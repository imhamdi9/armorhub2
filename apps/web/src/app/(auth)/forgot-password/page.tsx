"use client";

import Link from "next/link";
import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { errorMessage } from "@/lib/errors";
import { forgotPassword } from "@/services/auth.service";
import { LanguageSwitcher, tr } from "@/lib/i18n";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    try {
      setMessage((await forgotPassword(email)).message);
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
          <CardTitle className="text-center text-2xl">{tr("Reset your password")}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <Label htmlFor="email">{tr("Email")}</Label>
              <Input id="email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            {message && <p className="text-sm text-green-600">{message}</p>}
            {error && <p className="text-sm text-red-500">{error}</p>}
            <Button type="submit" className="w-full">{tr("Send reset link")}</Button>
            <p className="text-center text-sm">
              <Link href="/login" className="underline">{tr("Back to sign in")}</Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
