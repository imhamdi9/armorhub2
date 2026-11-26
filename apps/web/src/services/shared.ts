/**
 * Several parts of one page often ask the same thing at the same moment (the header, the footer and the buy box all want to know who is
 * signed in). They share one request and its answer for a few seconds. Anything that changes data forgets every answer (see api.ts).
 * Only in the browser: on the server one visitor's answer must never reach another.
 */
const KEEP_MS = 5000;
const answers = new Map<string, { until: number; value: Promise<unknown> }>();

export function shared<T>(key: string, ask: () => Promise<T>): Promise<T> {
  if (typeof window === "undefined") return ask();
  const kept = answers.get(key);
  if (kept && kept.until > Date.now()) return kept.value as Promise<T>;

  const value = ask();
  const entry = { until: Number.POSITIVE_INFINITY, value };   // still on its way: whoever asks now waits for the same one
  answers.set(key, entry);
  const settle = (keep: boolean) => {
    if (answers.get(key) !== entry) return;                   // forgotten meanwhile
    if (keep) entry.until = Date.now() + KEEP_MS; else answers.delete(key);
  };
  value.then(
    () => settle(true),
    // "You are not signed in" is an answer too; a dropped connection or a server error is not.
    (error) => settle([401, 403, 404].includes((error as { response?: { status?: number } }).response?.status ?? 0)),
  );

  return value;
}

export function forgetShared(): void {
  answers.clear();
}
