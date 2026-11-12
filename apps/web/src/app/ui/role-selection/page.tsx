"use client";

import { ROLE_ICONS, RoleSelectionStep } from "@/components/layout/role-selection-step";

export default function RoleSelectionPreview() {
  return (
    <RoleSelectionStep roles={[
      { id: "design", name: "Design", description: "Interfaces, brand and prototypes.", icon: ROLE_ICONS.design },
      { id: "engineering", name: "Engineering", description: "Building and shipping software.", icon: ROLE_ICONS.engineering },
      { id: "marketing", name: "Marketing", description: "Campaigns, content and growth.", icon: ROLE_ICONS.marketing },
      { id: "product", name: "Product", description: "Roadmaps, research and priorities.", icon: ROLE_ICONS.product },
      { id: "operations", name: "Operations", description: "Process, finance and people.", icon: ROLE_ICONS.operations },
      { id: "other", name: "Something else", description: "Tell us more later.", icon: ROLE_ICONS.other },
    ]} />
  );
}
