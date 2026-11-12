"use client";

import { useState } from "react";

import { GLYPHS, OAuthConsentCard } from "@/components/auth/oauth-consent-card";

/** A preview of the OAuth authorisation screen. The buttons only report what they would do: there is no OAuth server here yet. */
export default function OAuthConsentPreview() {
  const [said, setSaid] = useState("");

  return (
    <>
      <OAuthConsentCard
        app="Sunbeam"
        product="Northwind"
        account="amira.hassan@northwind.example"
        developer="Sunbeam Labs, Inc."
        redirectHost="app.sunbeam.example"
        permissions={[
          { glyph: GLYPHS.profile, title: "Your profile", description: "See your name, email address and profile photo.", readOnly: true },
          { glyph: GLYPHS.projects, title: "Projects and files", description: "Create, edit and organise projects and the files inside them." },
          { glyph: GLYPHS.comments, title: "Comments", description: "Read and post comments on your behalf." },
          { glyph: GLYPHS.members, title: "Workspace members", description: "See who belongs to the workspace and their roles.", readOnly: true },
        ]}
        onAllow={() => setSaid("Access would be granted.")}
        onCancel={() => setSaid("The request would be cancelled.")}
      />
      <p className="sr-only" role="status" aria-live="polite">{said}</p>
    </>
  );
}
