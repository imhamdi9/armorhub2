import { SocialWall, type SocialPost } from "@/components/layout/social-wall";

// Fictional people and posts, for previewing the layout only.
const POSTS: SocialPost[] = [
  { name: "Maya Okafor", handle: "@maya_builds", platform: "chat", time: "2h", datetime: "2025-03-04T10:00:00Z", replies: 14, likes: 482, favourite: true,
    body: "Moved our whole team onto @meridian last week. Standups got shorter and nobody asked where the file was once. #MyMeridianStory" },
  { name: "Tomás Herrera", handle: "@tomas.codes", platform: "photo", time: "5h", datetime: "2025-03-04T07:00:00Z", replies: 3, likes: 96,
    body: "Desk setup, finally done." },
  { name: "Priya Raman", handle: "@priya", platform: "network", time: "Mar 3", datetime: "2025-03-03T15:30:00Z", replies: 41, likes: 1260,
    body: "Eighteen months ago our onboarding took three weeks and a spreadsheet nobody trusted.\n\nToday a new hire ships in their first week. The change wasn't a tool, it was agreeing on what \"done\" means and letting @meridian enforce it so we didn't have to.\n\nHuge thanks to the ops team. #MyMeridianStory #futureofwork" },
  { name: "Jonas Lindqvist", handle: "@jonaslind", platform: "chat", time: "Mar 3", datetime: "2025-03-03T09:12:00Z", replies: 7, likes: 211, favourite: true,
    body: "Hot take: the best feature is the one that quietly saves you ten minutes every day and never asks for credit. Looking at you, scheduled exports. #MyMeridianStory" },
  { name: "Aisha Bello", handle: "@aisha_b", platform: "photo", time: "Mar 2", datetime: "2025-03-02T18:45:00Z", replies: 22, likes: 734,
    body: "Launch day at the studio. Coffee, cake and a dashboard that finally agrees with reality.\n\nThanks @meridian for not breaking anything on the one day it mattered. #MyMeridianStory" },
  { name: "Leo Fontaine", handle: "@leof", platform: "chat", time: "Mar 1", datetime: "2025-03-01T12:00:00Z", replies: 1, likes: 38,
    body: "Smooth migration. Zero drama." },
  { name: "Noor Haddad", handle: "@noorhaddad", platform: "network", time: "Feb 28", datetime: "2025-02-28T08:20:00Z", replies: 9, likes: 305,
    body: "Three things I tell every founder about reporting: keep it weekly, keep it honest, and keep it in one place. We use @meridian for the last one and our board meetings went from two hours to forty minutes. #MyMeridianStory" },
];

export default function SocialWallPreview() {
  return (
    <div className="min-h-screen bg-neutral-50">
      <SocialWall title="Loved by teams everywhere" invite="Add your own story with #MyMeridianStory and we may feature it here." posts={POSTS} />
    </div>
  );
}
