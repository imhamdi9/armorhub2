"use client";

import { TeamView } from "@/components/team/team-view";
import { useCurrentUser } from "@/components/providers/current-user";
import { hasRole } from "@/lib/navigation";

/** Admins see "Users" here (the platform team has its own page); everyone else keeps "Team". */
export default function UsersPage() {
  const me = useCurrentUser();

  return <TeamView scope={hasRole(me, "platform-admin") ? "users" : undefined} />;
}
