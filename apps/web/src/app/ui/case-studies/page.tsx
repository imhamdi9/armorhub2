import { CaseStudyProof } from "@/components/layout/case-study-proof";

// Fictional customers and figures, for previewing the layout only.
export default function CaseStudiesPreview() {
  return (
    <div className="min-h-screen bg-neutral-50">
      <CaseStudyProof
        studies={[
          {
            customer: "Harborline Freight",
            quote: { before: "We ", key: "cut dispatch time in half within a month", after: ". Our planners finally trust the schedule instead of rebuilding it every morning." },
            name: "Dana Whitfield", role: "VP Operations, Harborline Freight",
            metrics: [
              { figure: "52%", label: "Faster dispatch", direction: "down" },
              { figure: "3.1×", label: "Loads per planner", direction: "up" },
              { figure: "18 pts", label: "On-time delivery", direction: "up" },
            ],
            href: "#harborline",
          },
          {
            customer: "Cedar Health",
            quote: { before: "", key: "No-shows fell faster than we dared forecast", after: ". Front-desk staff now spend their day with patients, not on the phone." },
            name: "Dr. Imran Qureshi", role: "Medical Director, Cedar Health Network",
            metrics: [
              { figure: "34%", label: "Fewer no-shows", direction: "down" },
              { figure: "21%", label: "Same-week bookings", direction: "up" },
              { figure: "9 hrs", label: "Admin saved weekly", direction: "up" },
            ],
            href: "#cedar",
          },
        ]}
      />
    </div>
  );
}
