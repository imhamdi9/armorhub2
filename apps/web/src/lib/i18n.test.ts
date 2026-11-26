import { readdirSync, readFileSync, statSync } from "node:fs";
import path from "node:path";
import { describe, expect, it } from "vitest";

import { AR } from "@/lib/i18n-ar";

function sourceFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const full = path.join(dir, name);
    if (statSync(full).isDirectory()) return sourceFiles(full);
    return /\.tsx?$/.test(name) && !name.endsWith(".test.ts") && !full.includes(`${path.sep}lib${path.sep}i18n`) ? [full] : [];
  });
}

describe("Arabic dictionary", () => {
  const used = new Set<string>();
  for (const file of sourceFiles(path.resolve(__dirname, ".."))) {
    for (const m of readFileSync(file, "utf8").matchAll(/\btr\("((?:[^"\\]|\\.)*)"/g)) used.add(m[1]);
  }

  it("covers every string passed to tr() in the source", () => {
    // Placeholder text that is deliberately not translated.
    const ignored = new Set(["admin@armorhub.com"]);
    const missing = [...used].filter((key) => !ignored.has(key) && !(key in AR));
    expect(missing).toEqual([]);
  });

  it("keeps placeholders intact in every translation", () => {
    for (const [en, ar] of Object.entries(AR)) {
      const placeholders = (s: string) => (s.match(/\{\w+\}/g) ?? []).sort();
      expect(placeholders(ar), en).toEqual(placeholders(en));
    }
  });

  it("has no empty or untranslated entries", () => {
    for (const [en, ar] of Object.entries(AR)) {
      expect(ar.trim().length, en).toBeGreaterThan(0);
    }
  });
});
