"use client";

import { Suspense } from "react";

import { BusinessApplicationForm } from "@/components/auth/business-application-form";

export default function SellerSignupPage() {
  return (
    <Suspense>
      <BusinessApplicationForm type="seller" />
    </Suspense>
  );
}
