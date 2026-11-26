/**
 * One key per thing the person asked for. The server does the work once per key, so a request sent again (a second tap, a retry after the
 * connection dropped) gets the first answer instead of a second order or payment. The key is kept while the outcome is unknown, and let go
 * as soon as the server has answered, so a deliberate new attempt is a new request.
 */
export async function once<T>(action: string, intent: unknown, send: (config: { headers: { "Idempotency-Key": string } }) => Promise<T>): Promise<T> {
  const slot = `idempotency:${action}`;
  const asked = JSON.stringify(intent ?? null);
  const kept = read(slot);
  const key = kept?.asked === asked ? kept.key : newKey();
  write(slot, JSON.stringify({ asked, key }));

  try {
    const answer = await send({ headers: { "Idempotency-Key": key } });
    write(slot, null);
    return answer;
  } catch (error) {
    const status = (error as { response?: { status?: number } }).response?.status;
    // No answer, a server error or "still being processed": the work may have been done, so the same key goes with the next try.
    if (status !== undefined && status < 500 && status !== 409) write(slot, null);
    throw error;
  }
}

const memory = new Map<string, string>();

function read(slot: string): { asked: string; key: string } | null {
  let raw = memory.get(slot) ?? null;
  try { raw = window.sessionStorage.getItem(slot) ?? raw; } catch { /* storage unavailable: memory only */ }
  try { return raw ? JSON.parse(raw) : null; } catch { return null; }
}

function write(slot: string, value: string | null): void {
  if (value === null) memory.delete(slot); else memory.set(slot, value);
  try { if (value === null) window.sessionStorage.removeItem(slot); else window.sessionStorage.setItem(slot, value); } catch { /* storage unavailable */ }
}

function newKey(): string {
  return Array.from(crypto.getRandomValues(new Uint8Array(16)), (b) => b.toString(16).padStart(2, "0")).join("");
}
