"use client";

import { useState } from "react";

import { CheckoutIdentityStep } from "@/components/auth/checkout-identity-step";

/** A preview of the checkout identity step. The buttons only report what they would do: there is no checkout behind it. */
export default function CheckoutIdentityPreview() {
  const [said, setSaid] = useState("");

  return (
    <>
      <CheckoutIdentityStep
        onGuest={(email) => setSaid(`Continue as a guest with ${email}.`)}
        onSignIn={(email) => setSaid(`Sign in as ${email}.`)}
        onSocial={(provider) => setSaid(`Continue with ${provider}.`)}
      />
      <p className="sr-only" role="status" aria-live="polite">{said}</p>
    </>
  );
}
