import { imageSrc } from "@/lib/surfaces";

/** The public address of the site (set SITE_URL in production). Without it, canonical and social-card addresses are left out rather than guessed. */
export const siteUrl = () => (process.env.SITE_URL ?? "").replace(/\/$/, "");

/** An absolute address for an image, which social cards require. Returns undefined when there is none or the site address is unknown. */
export function absoluteImage(path: string | null | undefined): string | undefined {
  const resolved = imageSrc(path ?? null);
  if (!resolved) return undefined;
  if (/^https:\/\//i.test(resolved)) return resolved;

  return siteUrl() ? `${siteUrl()}${resolved.startsWith("/") ? "" : "/"}${resolved}` : undefined;
}

/** Plain text for a description: no markup, one line, a sensible length. */
export const plain = (text: string | null | undefined, max = 160) => (text ?? "").replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim().slice(0, max) || undefined;
