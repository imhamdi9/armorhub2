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

  it("says the server is waking up when there is no answer or the host's own error page, not that the sign-in failed", () => {
    expect(errorMessage(new AxiosError("Network Error"), "Could not sign in. Please try again.")).toMatch(/waking up/);
    expect(errorMessage(apiError("<html>Bad Gateway</html>", 502), "Could not sign in.")).toMatch(/waking up/);
    expect(errorMessage(apiError({ message: "Server Error" }, 500))).toBe("Server Error");
  });
});
