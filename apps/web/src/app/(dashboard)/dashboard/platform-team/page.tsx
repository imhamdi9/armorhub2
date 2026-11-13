"use client";

import { TeamView } from "@/components/team/team-view";

/** ArmorHub's own team: admins, market managers, warehouse staff and department members. */
export default function PlatformTeamPage() {
  return <TeamView scope="platform" />;
}
