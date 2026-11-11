"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { EmptyState, PageHeader } from "@/components/layout/page-header";
import { Button } from "@/components/ui/button";
import {
  getNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "@/services/platform.service";
import type { AppNotification } from "@/types/platform";
import { formatDateTime, tr } from "@/lib/i18n";

export default function NotificationsPage() {
  const router = useRouter();
  const [items, setItems] = useState<AppNotification[] | null>(null);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    getNotifications().then((r) => setItems(r.items)).catch(() => setItems([]));
  }, [tick]);

  async function open(n: AppNotification) {
    if (!n.read) await markNotificationRead(n.id).catch(() => undefined);
    router.push(n.path);
  }

  const hasUnread = items?.some((n) => !n.read);

  return (
    <div className="max-w-2xl">
      <PageHeader
        title={tr("Notifications")}
        action={
          hasUnread && (
            <Button variant="outline" onClick={() => markAllNotificationsRead().then(() => setTick((t) => t + 1))}>
              {tr("Mark all read")}
            </Button>
          )
        }
      />

      {items === null ? (
        <p className="text-muted-foreground">{tr("Loading...")}</p>
      ) : items.length === 0 ? (
        <EmptyState>Nothing yet. You will be told here (and by email) when something needs you.</EmptyState>
      ) : (
        <div className="space-y-2">
          {items.map((n) => (
            <button
              key={n.id}
              onClick={() => open(n)}
              className={`block w-full rounded-lg border p-4 text-start hover:bg-muted ${n.read ? "" : "border-blue-300 bg-blue-50"}`}
            >
              <p className="font-semibold">{tr(n.title)}</p>
              <p className="text-sm">{n.body}</p>
              <p className="mt-1 text-xs text-muted-foreground">{formatDateTime(n.created_at)}</p>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
