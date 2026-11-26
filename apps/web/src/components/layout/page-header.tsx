"use client";

import { tr } from "@/lib/i18n";

export function PageHeader({
  title,
  action,
}: {
  title: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 className="font-display text-[2rem] font-bold tracking-tight md:text-[2.2rem]">{tr(title)}</h1>
      {action}
    </div>
  );
}

export function EmptyState({ children }: { children: React.ReactNode }) {
  return (
    <p className="rounded-2xl border border-dashed bg-white/60 p-6 text-center text-muted-foreground">
      {typeof children === "string" ? tr(children) : children}
    </p>
  );
}
