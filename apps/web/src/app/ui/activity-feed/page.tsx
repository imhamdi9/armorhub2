import { ActivityFeedCard, EVENT_GLYPHS } from "@/components/layout/activity-feed-card";

// Sample people and events for previewing the layout only.
export default function ActivityFeedPreview() {
  return (
    <div className="grid min-h-screen place-items-start bg-neutral-50 px-4 py-12">
      <div className="mx-auto w-full max-w-lg">
        <ActivityFeedCard
          events={[
            { id: "1", actor: "Priya Raman", action: "approved", object: { label: "Q2 pricing proposal", href: "#proposal" }, when: "12 minutes ago", datetime: "2025-03-04T10:48:00Z", marker: { kind: "glyph", tone: "emerald", glyph: EVENT_GLYPHS.check } },
            { id: "2", actor: "Marcus Lee", action: "commented on", object: { label: "Onboarding checklist", href: "#checklist" }, when: "1 hour ago", datetime: "2025-03-04T09:60:00Z", marker: { kind: "glyph", tone: "sky", glyph: EVENT_GLYPHS.comment },
              quote: "Can we move the security review earlier? It blocked two teams last month." },
            { id: "3", actor: "Lena Fischer", action: "uploaded", object: { label: "brand-guidelines-v3.pdf", href: "#file" }, when: "3 hours ago", datetime: "2025-03-04T07:30:00Z", marker: { kind: "glyph", tone: "amber", glyph: EVENT_GLYPHS.upload } },
            { id: "4", actor: "Tomás Herrera", action: "joined the workspace", object: { label: "Studioform", href: "#workspace" }, when: "Yesterday", datetime: "2025-03-03T16:00:00Z", marker: { kind: "dot", tone: "emerald" } },
            { id: "5", actor: "Aisha Bello", action: "archived", object: { label: "Spring campaign", href: "#campaign" }, when: "2 days ago", datetime: "2025-03-02T11:15:00Z", marker: { kind: "dot", tone: "neutral" } },
          ]}
        />
      </div>
    </div>
  );
}
