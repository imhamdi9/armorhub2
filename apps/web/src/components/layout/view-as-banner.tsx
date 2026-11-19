"use client";

import { useState } from "react";

import { useCurrentUser } from "@/components/providers/current-user";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { leaveViewAs } from "@/services/platform.service";

/** Shown on every screen while a platform administrator is looking at the platform as someone else: whose eyes these are, and the way out. */
export function ViewAsBanner() {
  const user = useCurrentUser();
  const [error, setError] = useState<string | null>(null);
  if (!user?.impersonated_by) return null;

  async function leave() {
    setError(null);
    try {
      await leaveViewAs();
      window.location.assign("/dashboard/users");
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div className="sticky top-[64px] z-20 flex flex-wrap items-center justify-between gap-2 border-b border-red-700 bg-red-600 px-6 py-2 text-sm text-white" role="status" data-testid="view-as-banner">
      <span>
        {tr("You are viewing as {name} ({email}). It is read-only: nothing can be changed. You are signed in as {admin}.", { name: user.name, email: user.email, admin: user.impersonated_by })}
        {error && <strong className="ms-2">{error}</strong>}
      </span>
      <button type="button" onClick={leave} className="rounded-md border border-white px-3 py-1 font-semibold hover:bg-red-700">{tr("Exit")}</button>
    </div>
  );
}
