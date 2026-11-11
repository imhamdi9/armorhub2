"use client";

import { useCurrentUser } from "@/components/providers/current-user";

export default function DashboardPage() {
  const user = useCurrentUser();

  if (!user) return null;

  return (
    <div>
      <h2 className="text-3xl font-bold">Welcome, {user.name}</h2>
      <p className="mt-2 text-muted-foreground">{user.email}</p>

      <div className="mt-6 rounded-lg border p-4">
        <p className="text-sm text-muted-foreground">Your industry</p>
        <p className="text-xl font-semibold">
          {user.industry?.name ?? "Not assigned yet"}
        </p>
        {user.industry?.description && (
          <p className="mt-1 text-sm text-muted-foreground">
            {user.industry.description}
          </p>
        )}
      </div>
    </div>
  );
}
