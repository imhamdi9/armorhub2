import { NEXT_GLYPHS, SetupCompleteCard } from "@/components/layout/setup-complete-card";

// Sample values for previewing the layout only.
export default function SetupCompletePreview() {
  return (
    <SetupCompleteCard
      summary="Your workspace is ready, your data source is connected and your first teammates have been invited."
      recap={[
        { label: "Workspace name", value: "Acme Studio" },
        { label: "Region", value: "Europe (Frankfurt)" },
        { label: "Connected source", value: "Postgres · acme-prod" },
        { label: "Teammates invited", value: "3 people" },
      ]}
      next={[
        { title: "Build your first report", description: "Start from a template or a blank canvas.", href: "#report", glyph: NEXT_GLYPHS.report },
        { title: "Invite more people", description: "Add teammates and choose their roles.", href: "#invite", glyph: NEXT_GLYPHS.invite },
        { title: "Read the quickstart", description: "A five-minute tour of the essentials.", href: "#quickstart", glyph: NEXT_GLYPHS.guide },
      ]}
    />
  );
}
