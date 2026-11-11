import { isAxiosError } from "axios";
import { tr } from "@/lib/i18n";

/** Best human-readable message from an API error (validation or plain). */
export function errorMessage(error: unknown, fallback = "Something went wrong."): string {
  if (isAxiosError(error)) {
    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    const first = data?.errors && Object.values(data.errors)[0]?.[0];
    return tr(first ?? data?.message ?? fallback);
  }
  return tr(fallback);
}
