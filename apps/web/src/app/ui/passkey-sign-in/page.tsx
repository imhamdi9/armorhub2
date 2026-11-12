"use client";

import { useState } from "react";

import { PasskeySignInCard } from "@/components/auth/passkey-sign-in-card";

/** A preview of the passkey-first sign-in card. The buttons only report what they would do: no passkey is registered here yet. */
export default function PasskeySignInPreview() {
  const [said, setSaid] = useState("");

  return (
    <>
      <PasskeySignInCard
        email="amira@northwind.example"
        onContinueWithPasskey={() => setSaid("The device passkey prompt would open here.")}
        onNotYou={() => setSaid("Choose a different account.")}
        onUseCode={() => setSaid("A one-time code would be sent.")}
        onUsePassword={() => setSaid("The password form would open.")}
      />
      <p className="sr-only" role="status" aria-live="polite">{said}</p>
    </>
  );
}
