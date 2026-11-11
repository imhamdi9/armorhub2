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
    <div className="mb-6 flex items-center justify-between">
      <h1 className="text-3xl font-bold">{tr(title)}</h1>
      {action}
    </div>
  );
}

export function EmptyState({ children }: { children: React.ReactNode }) {
  return (
    <p className="rounded-lg border border-dashed p-6 text-center text-muted-foreground">
      {typeof children === "string" ? tr(children) : children}
    </p>
  );
}
