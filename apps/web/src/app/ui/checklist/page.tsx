import { GettingStartedChecklist } from "@/components/layout/getting-started-checklist";

export default function ChecklistPreview() {
  return (
    <div className="grid min-h-screen place-items-center bg-neutral-50 p-4">
      <GettingStartedChecklist tasks={[
        { id: "profile", label: "Complete your profile", action: "Open", done: true },
        { id: "invite", label: "Invite a teammate", action: "Invite", done: true },
        { id: "project", label: "Create your first project", action: "Create" },
        { id: "connect", label: "Connect your calendar", action: "Connect" },
      ]} />
    </div>
  );
}
