import { redirect } from "next/navigation";

/** Markets moved to the Administration section. */
export default function TenantsPage() {
  redirect("/dashboard/admin/markets");
}
