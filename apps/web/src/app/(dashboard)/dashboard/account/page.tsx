"use client";

import { useEffect, useState } from "react";

import { field, Note } from "@/components/installations/shared";
import { PageHeader } from "@/components/layout/page-header";
import { useCurrentUser } from "@/components/providers/current-user";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/errors";
import { tr } from "@/lib/i18n";
import { addAddress, changePassword, getNotificationPrefs, saveNotificationPrefs, type NotificationPrefs, deleteAccount, downloadMyData, getAddresses, removeAddress, saveProfile, updateAddress, type Address } from "@/services/ops.service";

type Msg = { tone: "ok" | "warn"; text: string } | null;

/** The customer's own details: who they are, how to sign in, where to deliver, and how to leave. */
export default function AccountPage() {
  const user = useCurrentUser();
  // Addresses and self-service deletion belong to shoppers; business and staff accounts are closed with support.
  const personal = !!user?.roles?.includes("customer") && !user.roles.some((r) => r !== "customer");
  const [profile, setProfile] = useState<Msg>(null);
  const [pw, setPw] = useState<Msg>(null);
  const [addr, setAddr] = useState<Msg>(null);
  const [danger, setDanger] = useState<Msg>(null);
  const [exportNote, setExportNote] = useState<Msg>(null);
  const [addresses, setAddresses] = useState<Address[] | null>(null);
  const [adding, setAdding] = useState(false);
  const [prefs, setPrefs] = useState<NotificationPrefs | null>(null);
  useEffect(() => { getNotificationPrefs().then(setPrefs).catch(() => {}); }, []);
  const load = () => getAddresses().then(setAddresses).catch(() => setAddresses([]));
  useEffect(() => { load(); }, []);

  const wrap = async (set: (m: Msg) => void, fn: () => Promise<unknown>, ok: string, after?: () => void) => {
    set(null);
    try { await fn(); set({ tone: "ok", text: ok }); after?.(); } catch (e) { set({ tone: "warn", text: errorMessage(e) }); }
  };

  return (
    <div className="space-y-8">
      <PageHeader title={tr("My account")} />

      <section className="rounded-xl border p-4" data-testid="profile">
        <h2 className="mb-3 text-lg font-semibold">{tr("Your details")}</h2>
        {profile && <Note tone={profile.tone}>{profile.text}</Note>}
        <form
          className="grid gap-3 md:grid-cols-2"
          onSubmit={(e) => {
            e.preventDefault();
            const f = new FormData(e.currentTarget);
            wrap(setProfile, () => saveProfile({ name: String(f.get("name")), phone: String(f.get("phone") || "") || null }), tr("Saved"));
          }}
        >
          <label className="text-sm">{tr("Full name")}<input name="name" defaultValue={user?.name ?? ""} className={field} required maxLength={120} /></label>
          <label className="text-sm">{tr("Mobile number")}<input name="phone" defaultValue={user?.phone ?? ""} className={field} dir="ltr" /></label>
          <p className="text-sm text-muted-foreground md:col-span-2">{user?.email}</p>
          <div><Button type="submit">{tr("Save")}</Button></div>
        </form>
      </section>

      <section className="rounded-xl border p-4" data-testid="password">
        <h2 className="mb-3 text-lg font-semibold">{tr("Password")}</h2>
        {pw && <Note tone={pw.tone}>{pw.text}</Note>}
        <form
          className="grid gap-3 md:grid-cols-3"
          onSubmit={(e) => {
            e.preventDefault();
            const form = e.currentTarget;
            const f = new FormData(form);
            wrap(setPw, () => changePassword({ current_password: String(f.get("current")), password: String(f.get("next")), password_confirmation: String(f.get("again")) }), tr("Password changed. Your other devices were signed out."), () => form.reset());
          }}
        >
          <input name="current" type="password" autoComplete="current-password" className={field} placeholder={tr("Current password")} required />
          <input name="next" type="password" autoComplete="new-password" className={field} placeholder={tr("New password")} required minLength={8} />
          <input name="again" type="password" autoComplete="new-password" className={field} placeholder={tr("Repeat the new password")} required minLength={8} />
          <div><Button type="submit">{tr("Change password")}</Button></div>
        </form>
      </section>

      {prefs && (
        <section className="rounded-xl border p-4" data-testid="notification-prefs">
          <h2 className="mb-1 text-lg font-semibold">{tr("How we tell you")}</h2>
          <p className="mb-3 text-sm text-muted-foreground">{tr("You always see updates inside the app. Choose what else reaches you.")}</p>
          {([["notify_email", "By e-mail"], ["notify_push", "On my phone (push)"]] as const).map(([key, label]) => (
            <label key={key} className="mb-2 flex items-center gap-2 text-sm">
              <input type="checkbox" checked={prefs[key]} onChange={async (e) => setPrefs(await saveNotificationPrefs({ [key]: e.target.checked }))} />
              {tr(label)}
            </label>
          ))}
        </section>
      )}

      {personal && <section className="rounded-xl border p-4" data-testid="addresses">
        <div className="mb-3 flex items-center justify-between"><h2 className="text-lg font-semibold">{tr("Delivery addresses")}</h2><Button size="sm" onClick={() => setAdding(true)}>{tr("Add an address")}</Button></div>
        {addr && <Note tone={addr.tone}>{addr.text}</Note>}
        {adding && (
          <form
            className="mb-4 grid gap-2 rounded-lg bg-muted/40 p-3 md:grid-cols-2"
            onSubmit={(e) => {
              e.preventDefault();
              const f = new FormData(e.currentTarget);
              const g = (k: string) => String(f.get(k) || "");
              wrap(setAddr, () => addAddress({ label: g("label") || null, name: g("name"), phone: g("phone"), line1: g("line1"), line2: g("line2") || null, city: g("city"), region: g("region") || null }), tr("Saved"), () => { setAdding(false); load(); });
            }}
          >
            <input name="label" className={field} placeholder={tr("Label, e.g. Home")} maxLength={40} />
            <input name="name" defaultValue={user?.name ?? ""} className={field} placeholder={tr("Full name")} required />
            <input name="phone" className={field} placeholder={tr("Mobile number")} required dir="ltr" />
            <input name="city" className={field} placeholder={tr("City")} required />
            <input name="line1" className={`${field} md:col-span-2`} placeholder={tr("Address")} required />
            <input name="line2" className={field} placeholder={tr("Apartment, floor, landmark (optional)")} />
            <input name="region" className={field} placeholder={tr("Governorate or area (optional)")} />
            <div className="flex gap-2 md:col-span-2"><Button type="submit">{tr("Save address")}</Button><Button type="button" variant="outline" onClick={() => setAdding(false)}>{tr("Cancel")}</Button></div>
          </form>
        )}
        {addresses === null ? <p className="text-sm text-muted-foreground">{tr("Loading...")}</p> : addresses.length === 0 ? <p className="text-sm text-muted-foreground">{tr("No saved addresses yet.")}</p> : (
          <ul className="divide-y rounded-lg border">
            {addresses.map((a) => (
              <li key={a.id} className="flex flex-wrap items-center justify-between gap-3 p-3 text-sm" data-testid="address">
                <span><b>{a.label || a.name}</b> {a.is_default && <Badge>{tr("Default")}</Badge>}<br /><span className="text-muted-foreground">{a.line1}, {a.city} · {a.phone}</span></span>
                <span className="flex gap-2">
                  {!a.is_default && <Button size="sm" variant="outline" onClick={() => updateAddress(a.id, { is_default: true }).then(load)}>{tr("Make default")}</Button>}
                  <Button size="sm" variant="outline" onClick={() => removeAddress(a.id).then(load)}>{tr("Remove")}</Button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>}

      {personal && <section className="rounded-xl border p-4" data-testid="export">
        <h2 className="mb-1 text-lg font-semibold">{tr("Download my data")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("A file with everything we hold about you: your details, addresses, orders, reviews, questions, warranty claims and points. You can download it up to 3 times an hour.")}</p>
        {exportNote && <Note tone={exportNote.tone}>{exportNote.text}</Note>}
        <Button variant="outline" onClick={async () => {
          setExportNote(null);
          try {
            const blob = await downloadMyData();
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "armorhub-my-data.json";
            a.click();
            URL.revokeObjectURL(url);
            setExportNote({ tone: "ok", text: tr("Your data was downloaded.") });
          } catch (e) { setExportNote({ tone: "warn", text: errorMessage(e) }); }
        }}>{tr("Download my data")}</Button>
      </section>}

      {personal ? <section className="rounded-xl border border-red-200 p-4" data-testid="danger">
        <h2 className="mb-1 text-lg font-semibold">{tr("Delete my account")}</h2>
        <p className="mb-3 text-sm text-muted-foreground">{tr("Your personal details are removed. Past orders stay as financial records, without your name. You cannot do this while an order is in progress.")}</p>
        {danger && <Note tone={danger.tone}>{danger.text}</Note>}
        <form
          className="flex flex-wrap gap-2"
          onSubmit={(e) => {
            e.preventDefault();
            if (!window.confirm(tr("Delete your account for good?"))) return;
            const f = new FormData(e.currentTarget);
            wrap(setDanger, () => deleteAccount({ password: String(f.get("password") || ""), confirm: String(f.get("confirm") || "") }), tr("Your account was deleted."), () => { window.location.assign("/"); });
          }}
        >
          <input name="password" type="password" className={`${field} max-w-xs`} placeholder={tr("Your password")} autoComplete="current-password" />
          <input name="confirm" className={`${field} max-w-xs`} placeholder={tr("Type DELETE if you signed in with Google, Facebook or Apple")} />
          <Button type="submit" variant="outline">{tr("Delete my account")}</Button>
        </form>
      </section> : (
        <p className="rounded-xl border p-4 text-sm text-muted-foreground">{tr("Business and staff accounts are closed through support. Please contact us.")}</p>
      )}
    </div>
  );
}
