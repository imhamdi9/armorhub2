// Writes the website's Arabic dictionary where the API can read it (apps/api/lang/ar.json), so the API answers in Arabic with the same wording.
// Run it after adding Arabic strings:  node scripts/export-ar.mjs
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const source = readFileSync(resolve(here, "../src/lib/i18n-ar.ts"), "utf8");
const out = {};
// Lines of the form   "English text": "النص العربي",
for (const m of source.matchAll(/^\s*"((?:[^"\\]|\\.)*)"\s*:\s*"((?:[^"\\]|\\.)*)"\s*,?\s*$/gm)) {
  out[JSON.parse(`"${m[1]}"`)] = JSON.parse(`"${m[2]}"`);
}
const target = resolve(here, "../../api/lang/ar.json");
mkdirSync(dirname(target), { recursive: true });
writeFileSync(target, JSON.stringify(out, null, 2) + "\n");
console.log(`ar.json: ${Object.keys(out).length} strings`);
