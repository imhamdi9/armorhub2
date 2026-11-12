import { TeamAdminPanel, type Member } from "@/components/layout/team-admin-panel";

// Sample people for previewing the layout only.
const MEMBERS: Member[] = [
  { id: "m1", name: "Amira Hassan", email: "amira@northwind.example", role: "Owner", lastActive: "Just now", status: "Active" },
  { id: "m2", name: "Marcus Lee", email: "marcus@northwind.example", role: "Admin", lastActive: "2 hours ago", status: "Active" },
  { id: "m3", name: "Priya Raman", email: "priya@northwind.example", role: "Editor", lastActive: "Yesterday", status: "Active" },
  { id: "m4", name: "Tomás Herrera", email: "tomas@northwind.example", role: "Editor", lastActive: "3 days ago", status: "Invited" },
  { id: "m5", name: "Lena Fischer", email: "lena@northwind.example", role: "Viewer", lastActive: "Last week", status: "Active" },
  { id: "m6", name: "Jonas Lindqvist", email: "jonas@northwind.example", role: "Viewer", lastActive: "Mar 2", status: "Suspended" },
];

export default function TeamAdminPreview() {
  return <div className="min-h-screen bg-neutral-50"><TeamAdminPanel initial={MEMBERS} licensedSeats={10} /></div>;
}
