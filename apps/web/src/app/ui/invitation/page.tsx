"use client";

import { useState } from "react";

import { InvitationCard } from "@/components/auth/invitation-card";

/** A preview of the invitation card. The buttons only report what they would do: there is no invite backend here yet. */
export default function InvitationPreview() {
  const [said, setSaid] = useState("");

  return (
    <>
      <InvitationCard
        inviter="Jules"
        workspace="Studioform"
        domain="studioform.example"
        role="Editor"
        teammates={["Priya", "Marcus", "Lena", "Tomás"]}
        moreTeammates={2}
        invitedEmail="amira.hassan@northwind.example"
        onAccept={() => setSaid("The invitation would be accepted.")}
        onDecline={() => setSaid("The invitation would be declined.")}
      />
      <p className="sr-only" role="status" aria-live="polite">{said}</p>
    </>
  );
}
