import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === "production";

/** Origin of the API when it is not same-origin (NEXT_PUBLIC_API_URL=https://api.example.com/api). */
function apiOrigin(): string {
  try {
    return new URL(process.env.NEXT_PUBLIC_API_URL ?? "").origin;
  } catch {
    return "";
  }
}

// Bot-challenge providers an admin may enable (Settings > Security). Allowed so the widget can load.
const CAPTCHA = [
  "https://challenges.cloudflare.com",
  "https://js.hcaptcha.com",
  "https://hcaptcha.com",
  "https://*.hcaptcha.com",
  "https://www.google.com/recaptcha/",
  "https://www.gstatic.com/recaptcha/",
];

/**
 * Content-Security-Policy: even if an attacker managed to inject markup, the browser refuses to
 * load scripts, frames, forms or connections from anywhere we did not list.
 * (Next.js needs 'unsafe-inline' for its bootstrap scripts, and 'unsafe-eval' only in development.)
 */
const cspFor = (frameAncestors: string) => [
  "default-src 'self'",
  `script-src 'self' 'unsafe-inline'${isProd ? "" : " 'unsafe-eval'"} ${CAPTCHA.join(" ")}`,
  "style-src 'self' 'unsafe-inline'",
  // Stores may show a logo hosted elsewhere (https only; the editor refuses anything else).
  "img-src 'self' data: blob: https:",
  "font-src 'self' data:",
  `connect-src 'self' ${apiOrigin()} ${CAPTCHA.join(" ")}${isProd ? "" : " ws: wss:"}`,
  `frame-src 'self' ${CAPTCHA.join(" ")}`,
  "form-action 'self'",
  "base-uri 'self'",
  "object-src 'none'",
  `frame-ancestors ${frameAncestors}`,
  isProd ? "upgrade-insecure-requests" : "",
]
  .filter(Boolean)
  .join("; ");

const csp = cspFor("'none'");
// Only the theme editor's own preview may be framed, and only by this same site.
const previewCsp = cspFor("'self'");

const nextConfig: NextConfig = {
  // Self-contained server output, used by the production Docker image.
  output: "standalone",
  poweredByHeader: false,

  async headers() {
    const common = [
      { key: "X-Content-Type-Options", value: "nosniff" },
      { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
      { key: "Permissions-Policy", value: "camera=(), microphone=(), payment=(), geolocation=(self)" },
      { key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" },
    ];

    return [
      {
        source: "/theme-preview",
        headers: [{ key: "Content-Security-Policy", value: previewCsp }, { key: "X-Frame-Options", value: "SAMEORIGIN" }, ...common],
      },
      {
        source: "/((?!theme-preview$).*)",
        headers: [
          { key: "Content-Security-Policy", value: csp },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), payment=(), geolocation=(self)" },
          { key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" },
        ],
      },
    ];
  },

  // Optional: serve the API from the same origin as the web app (no CORS, one
  // public port). Set API_PROXY_TARGET=http://host:8000 and
  // NEXT_PUBLIC_API_URL=/api at build time. Unset in normal deployments.
  async rewrites() {
    const target = process.env.API_PROXY_TARGET;

    return target ? [{ source: "/api/:path*", destination: `${target}/api/:path*` }] : [];
  },
};

export default nextConfig;
