"use client";

import { Suspense } from "react";

import { BusinessApplicationForm } from "@/components/auth/business-application-form";

export default function PartnerSignupPage() {
  return (
    <Suspense>
      <BusinessApplicationForm type="partner" />
    </Suspense>
  );
}
