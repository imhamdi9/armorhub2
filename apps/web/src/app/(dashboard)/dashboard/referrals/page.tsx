"use client";

import { useEffect, useState } from "react";

import { PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import { LoadingBlock } from "@/components/ui/loading-block";
import { formatDate, tr } from "@/lib/i18n";
import { getLoyalty } from "@/services/installation.service";
import { getReferral, type ReferralInfo } from "@/services/ops.service";

const REASONS: Record<string, string> = { goods_order: "Order delivered", referral: "Friend referral", redeemed_goods: "Spent on a purchase", released: "Points returned", job: "Installation job", review: "Review", redeemed: "Spent on an installation", goodwill: "Goodwill" };

/** Points, and the invite link: a friend's first delivered order rewards both of you. */
export default function ReferralsPage() {
  const [info, setInfo] = useState<ReferralInfo | null>(null);
  const [points, setPoints] = useState<Awaited<ReturnType<typeof getLoyalty>> | null>(null);
  const [copied, setCopied] = useState(false);
  useEffect(() => { getReferral().then(setInfo).catch(() => setInfo(null)); getLoyalty().then(setPoints).catch(() => setPoints(null)); }, []);

  if (!info) return <LoadingBlock />;

  return (
    <div className="space-y-6">
      <PageHeader title={tr("Invite friends")} />
      <section className="rounded-xl border p-5" data-testid="referral">
        <p className="text-muted-foreground">{tr("Share your link. When a friend receives their first order, you both get {n} points.", { n: info.points_each })}</p>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <span className="rounded-lg bg-primary/5 px-4 py-2 text-xl font-bold tracking-[0.25em]" dir="ltr" data-testid="referral-code">{info.code}</span>
          <input readOnly dir="ltr" value={info.link} className="h-10 min-w-64 flex-1 rounded-[10px] border bg-background px-3 text-sm" aria-label={tr("Your invite link")} onFocus={(e) => e.currentTarget.select()} />
          <Button onClick={async () => { await navigator.clipboard?.writeText(info.link); setCopied(true); }}>{copied ? tr("Copied") : tr("Copy link")}</Button>
        </div>
        <p className="mt-4 text-sm text-muted-foreground">{tr("{n} invited", { n: info.invited })} · {tr("{n} rewarded", { n: info.rewarded })}</p>
      </section>
      {points && (
        <section className="rounded-xl border p-5" data-testid="points">
          <p className="text-sm text-muted-foreground">{tr("Your points")}</p>
          <p className="text-3xl font-bold">{points.points}</p>
          {points.history.length > 0 && (
            <ul className="mt-3 divide-y text-sm">
              {points.history.map((h, i) => (
                <li key={i} className="flex justify-between py-2"><span>{tr(REASONS[h.reason] ?? h.reason)} <span className="text-muted-foreground">· {formatDate(h.created_at)}</span></span><b className={h.points < 0 ? "text-red-600" : "text-green-700"}>{h.points > 0 ? "+" : ""}{h.points}</b></li>
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  );
}
