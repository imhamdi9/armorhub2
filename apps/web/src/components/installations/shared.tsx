"use client";

import { Badge } from "@/components/ui/badge";
import { STATUS, TIERS } from "@/lib/installation";
import { formatDateTime, tr } from "@/lib/i18n";
import type { InstallationStatus, Proof } from "@/services/installation.service";

export const field = "h-10 w-full rounded-[10px] border border-input bg-transparent px-2.5 text-sm";

export function StatusBadge({ status, side = "customer" }: { status: InstallationStatus; side?: "customer" | "centre" }) {
  const s = STATUS[status];
  const label = side === "centre" && status === "proof_submitted" ? "Waiting for the customer" : s.label;

  return <Badge variant={s.tone}>{tr(label)}</Badge>;
}

export function TierBadge({ tier }: { tier: string }) {
  return tier === "new" ? <Badge variant="outline">{tr(TIERS.new)}</Badge> : <Badge variant={tier === "gold" ? "default" : "secondary"}>{tr(TIERS[tier] ?? tier)}</Badge>;
}

export const Stars = ({ value }: { value: number }) => (
  <span aria-label={`${value} / 5`} className="text-amber-600">{"★".repeat(Math.round(value))}<span className="text-muted-foreground/40">{"★".repeat(5 - Math.round(value))}</span></span>
);

/** Proof photos are private: they load through the signed-in session, never from a public address. */
export function fileSrc(url: string | null): string | undefined {
  if (!url) return undefined;

  return `${(process.env.NEXT_PUBLIC_API_URL ?? "/api").replace(/\/$/, "")}${url.replace(/^\/api/, "")}`;
}

export function ProofGallery({ proofs }: { proofs: Proof[] }) {
  if (proofs.length === 0) return <p className="text-sm text-muted-foreground">{tr("Nothing added yet.")}</p>;
  const label = (p: Proof) => tr(({ before: "Before", after: "After", serial: "Serial or warranty number", evidence: "Evidence", document: "Document" } as Record<string, string>)[p.kind] ?? p.kind);

  return (
    <ul className="grid grid-cols-2 gap-3 md:grid-cols-3">
      {proofs.map((p) => (
        <li key={p.id} className="overflow-hidden rounded-xl border text-sm">
          {p.url && (
            // eslint-disable-next-line @next/next/no-img-element -- a private photo served through the signed-in session
            <a href={fileSrc(p.url)} target="_blank" rel="noreferrer"><img src={fileSrc(p.url)} alt={label(p)} loading="lazy" className="aspect-[4/3] w-full object-cover" /></a>
          )}
          <div className="space-y-0.5 p-2">
            <p className="font-medium">{label(p)} <span className="font-normal text-muted-foreground">· {tr(p.side === "centre" ? "Centre" : "Customer")}</span></p>
            {p.text && <p className="text-muted-foreground">{p.text}</p>}
            {p.taken_at && <p className="text-xs text-muted-foreground">{formatDateTime(p.taken_at)}</p>}
            {p.flagged && <p className="text-xs text-amber-700">{tr("Taken far from the job site or not recently")}</p>}
          </div>
        </li>
      ))}
    </ul>
  );
}

/** The substitute number: the platform's number plus a temporary extension. The real number is never shown. */
export function CallBox({ call }: { call: { number: string; extension: string; expires_at: string | null } | null | undefined }) {
  if (!call) return <p className="text-sm text-muted-foreground">{tr("The temporary number has expired. Use the platform messages.")}</p>;

  return (
    <div className="rounded-xl bg-muted p-3 text-sm">
      <p className="font-medium">{tr("Temporary substitute number")}</p>
      <p dir="ltr" className="mt-1 text-lg font-black tracking-tight" data-testid="proxy-number">{call.number || tr("Platform number")} · {tr("ext.")} {call.extension}</p>
      <p className="mt-1 text-xs text-muted-foreground">{tr("Real phone numbers are never shared. This number stops working when the job is finished.")}{call.expires_at ? ` ${tr("Valid until")} ${formatDateTime(call.expires_at)}.` : ""}</p>
    </div>
  );
}

export function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return <section className="mb-6 rounded-xl border p-4"><h2 className="mb-3 text-lg font-semibold">{tr(title)}</h2>{children}</section>;
}

export function Note({ children, tone = "info" }: { children: React.ReactNode; tone?: "info" | "warn" | "ok" }) {
  const cls = { info: "border-sky-200 bg-sky-50 text-sky-900", warn: "border-amber-200 bg-amber-50 text-amber-900", ok: "border-green-200 bg-green-50 text-green-900" }[tone];

  return <p role="note" className={`mb-4 rounded-lg border p-3 text-sm ${cls}`}>{children}</p>;
}
