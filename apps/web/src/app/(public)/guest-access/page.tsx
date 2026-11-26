"use client";

import { useState, useSyncExternalStore } from "react";

import { errorMessage } from "@/lib/errors";
import { guestAccessTarget } from "@/lib/guest-access";
import { openGuestSession } from "@/lib/guest";
import { tr } from "@/lib/i18n";

const subscribe = (changed: () => void) => {
  window.addEventListener("hashchange", changed);
  return () => window.removeEventListener("hashchange", changed);
};

/** Where the links in a guest shopper's e-mails land: one button signs this device in as them and takes them to the page the e-mail was about. */
export default function GuestAccessPage() {
  const hash = useSyncExternalStore(subscribe, () => window.location.hash, () => null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (hash === null) return <p className="p-8 text-center text-muted-foreground">{tr("Loading...")}</p>;
  const parts = new URLSearchParams(hash.slice(1));
  const token = parts.get("t");
  if (!token) return <p className="p-8 text-center">{tr("This link no longer works. Use the one in our latest e-mail.")}</p>;

  async function open() {
    setBusy(true);
    setError(null);
    try {
      await openGuestSession(token as string);
      window.location.replace(guestAccessTarget(parts.get("to")));
    } catch (e) {
      setError(errorMessage(e));
      setBusy(false);
    }
  }

  return (
    <main className="mx-auto max-w-md px-6 py-16 text-center">
      <h1 className="mb-3 text-2xl font-semibold">{tr("Your order")}</h1>
      <p className="mb-6 text-muted-foreground">{tr("Continue to see your order on this device.")}</p>
      <button type="button" onClick={open} disabled={busy} className="btn-brand px-8 py-3 disabled:opacity-50">{tr("Continue")}</button>
      {error && <p role="alert" className="mt-4 text-sm text-red-600">{error}</p>}
    </main>
  );
}
