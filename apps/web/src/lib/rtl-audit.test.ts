import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, relative, sep } from "node:path";

import { describe, expect, it } from "vitest";

/**
 * Arabic reads right to left, so a style that says "left" or "right" is wrong in half the site. Use the logical ones instead
 * (ms-/me-/ps-/pe-, text-start/text-end, start-/end-, border-s/e, rounded-s/e). Design previews under /ui and the two preview layouts
 * that only they use are exempt.
 */
const ROOT = join(__dirname, "..");
const EXEMPT = [`${sep}app${sep}ui${sep}`, `${sep}components${sep}ui${sep}`, `${sep}components${sep}layout${sep}shop-storefront.tsx`, `${sep}components${sep}layout${sep}shop-home.tsx`];
const PHYSICAL = /(^|[\s"'`])(-?(?:ml|mr|pl|pr)-(?:\d|\[)|text-(?:left|right)|float-(?:left|right)|border-(?:l|r)(?:-|[\s"'`])|rounded-(?:l|r|tl|tr|bl|br)(?:-|[\s"'`])|(?:left|right)-(?:\d|\[)|-(?:left|right)-(?:\d|\[))/;

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    return statSync(path).isDirectory() ? files(path) : path.endsWith(".tsx") ? [path] : [];
  });
}

describe("right-to-left safety", () => {
  it("uses no direction-specific utility classes outside the design previews", () => {
    const offenders: string[] = [];
    for (const file of files(ROOT).filter((f) => !EXEMPT.some((e) => f.includes(e)))) {
      readFileSync(file, "utf8").split("\n").forEach((line, i) => {
        // Only look inside class strings: a "left" in a comment or a sentence is fine.
        const classes = [...line.matchAll(/class(?:Name)?=(?:"([^"]*)"|\{`([^`]*)`\}|\{"([^"]*)"\})/g)].map((m) => m[1] ?? m[2] ?? m[3] ?? "").join(" ");
        if (PHYSICAL.test(` ${classes} `)) offenders.push(`${relative(ROOT, file)}:${i + 1}`);
      });
    }

    expect(offenders).toEqual([]);
  });
});
