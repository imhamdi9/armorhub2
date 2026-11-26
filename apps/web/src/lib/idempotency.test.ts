import { describe, expect, it } from "vitest";

import { once } from "./idempotency";

const keys: string[] = [];
const answer = (config: { headers: { "Idempotency-Key": string } }) => { keys.push(config.headers["Idempotency-Key"]); return Promise.resolve("ok"); };
const fail = (status?: number) => (config: { headers: { "Idempotency-Key": string } }) => {
  keys.push(config.headers["Idempotency-Key"]);
  return Promise.reject(status === undefined ? new Error("offline") : { response: { status } });
};
const used = () => keys.splice(0);

describe("once", () => {
  it("sends the same key again while the outcome is unknown", async () => {
    for (const status of [undefined, 500, 503, 409]) {
      await expect(once(`unknown-${status}`, { id: 1 }, fail(status))).rejects.toBeDefined();
      await once(`unknown-${status}`, { id: 1 }, answer);
      const [first, second] = used();
      expect(second).toBe(first);
    }
  });

  it("takes a new key after the server has answered", async () => {
    await once("done", 1, answer);
    await once("done", 1, answer);
    await expect(once("refused", 1, fail(422))).rejects.toBeDefined();
    await once("refused", 1, answer);
    expect(new Set(used()).size).toBe(4);
  });

  it("takes a new key when something else is asked for", async () => {
    await expect(once("changed", { total: 1 }, fail())).rejects.toBeDefined();
    await once("changed", { total: 2 }, answer);
    const [first, second] = used();
    expect(second).not.toBe(first);
    expect(first).toMatch(/^[0-9a-f]{32}$/);
  });
});
