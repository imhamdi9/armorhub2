import { isAxiosError } from "axios";
import { tr } from "@/lib/i18n";

/** Best human-readable message from an API error (validation or plain). */
export function errorMessage(error: unknown, fallback = "Something went wrong."): string {
  if (isAxiosError(error)) {
    // No answer at all, or the host's own error page (502/503/504): the server is starting up or unreachable, not the person's mistake.
    const status = error.response?.status;
    if (!error.response || status === 502 || status === 503 || status === 504) {
      return tr("The server is waking up or unreachable. Wait a few seconds and try again.");
    }

    const data = error.response?.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    const first = data?.errors && Object.values(data.errors)[0]?.[0];
    return tr(first ?? data?.message ?? fallback);
  }
  return tr(fallback);
}

/** Per-field validation messages from an API error (first message per field). */
export function fieldErrors(error: unknown): Record<string, string> {
  if (!isAxiosError(error)) return {};
  const errors = (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {};

  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, tr(v[0] ?? "")]));
}
