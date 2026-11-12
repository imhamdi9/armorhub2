"use client";

import { Bell } from "lucide-react";
import Link from "next/link";
import { useEffect, useState } from "react";

import { getUnreadCount } from "@/services/platform.service";

const POLL_MS = 30_000;

/** Header bell: unread count, refreshed every 30 seconds. */
export function NotificationBell() {
  const [unread, setUnread] = useState(0);

  useEffect(() => {
    const load = () => getUnreadCount().then(setUnread).catch(() => undefined);
    load();
    const timer = setInterval(load, POLL_MS);
    return () => clearInterval(timer);
  }, []);

  return (
    <Link
      href="/dashboard/notifications"
      aria-label={`Notifications${unread ? ` (${unread} unread)` : ""}`}
      className="relative me-2 rounded-md p-2 hover:bg-muted"
    >
      <Bell className="h-5 w-5" />
      {unread > 0 && (
        <span aria-hidden="true" className="absolute -end-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs text-white">
          {unread > 99 ? "99+" : unread}
        </span>
      )}
    </Link>
  );
}
