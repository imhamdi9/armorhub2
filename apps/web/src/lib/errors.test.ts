import { AxiosError, type AxiosResponse } from "axios";
import { describe, expect, it } from "vitest";

import { errorMessage } from "@/lib/errors";

function apiError(data: unknown, status = 422) {
  const error = new AxiosError("Request failed");
  error.response = { data, status } as AxiosResponse;
  return error;
}

describe("errorMessage", () => {
  it("prefers the first validation error", () => {
    const e = apiError({ message: "Invalid", errors: { email: ["Invalid credentials."] } });
    expect(errorMessage(e)).toBe("Invalid credentials.");
  });

  it("falls back to the API message, then the default", () => {
    expect(errorMessage(apiError({ message: "No partners available" }))).toBe("No partners available");
    expect(errorMessage(apiError({}))).toBe("Something went wrong.");
    expect(errorMessage(new Error("boom"), "Custom")).toBe("Custom");
  });
});
