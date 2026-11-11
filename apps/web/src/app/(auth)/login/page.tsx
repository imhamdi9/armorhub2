"use client";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Button } from "@/components/ui/button";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { login } from "@/services/auth.service";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { errorMessage } from "@/lib/errors";
import Link from "next/link";

import {
  loginSchema,
  type LoginFormData,
} from "@/lib/validators/login";

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
      const response = await login(data);

      localStorage.setItem("token", response.token);

      router.push("/dashboard");
    } catch (err) {
      setError(errorMessage(err, "Could not sign in. Please try again."));
    }

  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-100">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-center text-2xl">
            Sign in to ArmorHub
          </CardTitle>
        </CardHeader>

        <CardContent>
          <form
            onSubmit={handleSubmit(onSubmit)}
            className="space-y-4"
          >
          <div>
            <Label>Email</Label>
            <Input
             type="email"
             placeholder="admin@armorhub.com"
             {...register("email")}
            />
            {errors.email && (
              <p className="mt-1 text-sm text-red-500">
               {errors.email.message}
              </p>
            )}
          </div>

          <div>
            <Label>Password</Label>
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
            Login
          </Button>
          <p className="text-center text-sm">
            <Link href="/forgot-password" className="underline">Forgot your password?</Link>
          </p>
          <p className="text-center text-sm">
            New here? <Link href="/register" className="underline">Create an account</Link>
          </p>
            </form>
        </CardContent>
      </Card>
    </div>
  );
}