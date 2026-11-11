"use client";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { login } from "@/services/auth.service";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { errorMessage } from "@/lib/errors";
import Link from "next/link";

import {
  loginSchema,
  type LoginFormData,
} from "@/lib/validators/login";
import { LanguageSwitcher, tr } from "@/lib/i18n";

/** Message shown after the visitor followed the link in their verification email. */
function VerifiedNotice() {
  const verified = useSearchParams().get("verified");

  if (verified === "1") return <p className="text-center text-sm text-green-600">{tr("Email verified. You can sign in now.")}</p>;
  if (verified === "0") return <p className="text-center text-sm text-red-500">{tr("We could not verify that link. Sign in and request a new one.")}</p>;

  return null;
}

export default function LoginPage() {

  const router = useRouter();
  const [error, setError] = useState<string | null>(null);


  const {
   register,
   handleSubmit,
   formState: { errors },
  } = useForm<LoginFormData>({
   resolver: zodResolver(loginSchema),
  });

  async function onSubmit(data: LoginFormData) {
    setError(null);

    try {
      await login(data);

      router.push("/dashboard");
    } catch (err) {
      setError(errorMessage(err, "Could not sign in. Please try again."));
    }

  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-gray-100">
        <div className="absolute end-4 top-4">
          <LanguageSwitcher />
        </div>

      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-center text-2xl">
            {tr("Sign in to ArmorHub")}
          </CardTitle>
        </CardHeader>

        <CardContent>
          <Suspense>
            <VerifiedNotice />
          </Suspense>
          <form
            onSubmit={handleSubmit(onSubmit)}
            className="space-y-4"
          >
          <div>
            <Label>{tr("Email")}</Label>
            <Input
             type="email"
             placeholder={tr("admin@armorhub.com")}
             {...register("email")}
            />
            {errors.email && (
              <p className="mt-1 text-sm text-red-500">
               {errors.email.message}
              </p>
            )}
          </div>

          <div>
            <Label>{tr("Password")}</Label>
            <Input
              type="password"
              placeholder="••••••••"
              {...register("password")}
            />

            {errors.password && (
              <p className="mt-1 text-sm text-red-500">
                {errors.password.message}
              </p>
            )}
          </div>

          {error && <p className="text-sm text-red-500">{error}</p>}

          <Button
            type="submit"
            className="w-full"
          >
            {tr("Login")}
          </Button>
          <p className="text-center text-sm">
            <Link href="/forgot-password" className="underline">{tr("Forgot your password?")}</Link>
          </p>
          <p className="text-center text-sm">
            {tr("New here?")}<Link href="/signup" className="underline">{tr("Create an account")}</Link>
          </p>
            </form>
        </CardContent>
      </Card>
    </div>
  );
}