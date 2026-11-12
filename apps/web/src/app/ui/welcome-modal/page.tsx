"use client";

import { WELCOME_GLYPHS, WelcomeModal } from "@/components/layout/welcome-modal";

/** The modal remembers "do not show this again" in localStorage under `welcome.dismissed`. Clear that key to see it again. */
export default function WelcomeModalPreview() {
  return (
    <div className="min-h-screen bg-neutral-50 p-10">
      <h1 className="text-2xl font-semibold text-neutral-900">Dashboard</h1>
      <p className="mt-2 text-sm text-neutral-500">The page behind the welcome dialog.</p>
      <WelcomeModal
        name="Amira"
        version="2.4.0"
        actions={[
          { title: "Create your first project", description: "Start from a template or from scratch.", href: "#project", glyph: WELCOME_GLYPHS.project },
          { title: "Import your data", description: "Bring in a CSV or connect a source.", href: "#import", glyph: WELCOME_GLYPHS.import },
          { title: "Invite your team", description: "Work together from day one.", href: "#team", glyph: WELCOME_GLYPHS.team },
        ]}
      />
    </div>
  );
}
