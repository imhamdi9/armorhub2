import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { forgetShared, shared } from "./shared";

const counter = (result: () => Promise<number>) => {
  const ask = vi.fn(result);
  return { ask, get: () => shared("k", ask) };
};

describe("shared", () => {
  beforeEach(() => { vi.useFakeTimers(); vi.stubGlobal("window", {}); forgetShared(); });
  afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); });

  it("asks once for everyone who wants it at about the same time", async () => {
    const { ask, get } = counter(() => Promise.resolve(1));
    expect(await Promise.all([get(), get(), get()])).toEqual([1, 1, 1]);
    await get();
    expect(ask).toHaveBeenCalledTimes(1);
  });

  it("asks again after a few seconds, or once something changed", async () => {
    const { ask, get } = counter(() => Promise.resolve(1));
    await get();
    vi.advanceTimersByTime(6000);
    await get();
    forgetShared();
    await get();
    expect(ask).toHaveBeenCalledTimes(3);
  });

  it("keeps a 'not signed in' answer but not a failure", async () => {
    const refused = counter(() => Promise.reject({ response: { status: 401 } }));
    await expect(refused.get()).rejects.toBeDefined();
    await expect(refused.get()).rejects.toBeDefined();
    expect(refused.ask).toHaveBeenCalledTimes(1);

    forgetShared();
    const broken = counter(() => Promise.reject(new Error("offline")));
    await expect(broken.get()).rejects.toBeDefined();
    await expect(broken.get()).rejects.toBeDefined();
    expect(broken.ask).toHaveBeenCalledTimes(2);
  });

  it("shares nothing on the server", async () => {
    vi.unstubAllGlobals();
    const { ask, get } = counter(() => Promise.resolve(1));
    await get();
    await get();
    expect(ask).toHaveBeenCalledTimes(2);
  });
});
