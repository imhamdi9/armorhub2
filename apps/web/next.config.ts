import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Self-contained server output, used by the production Docker image.
  output: "standalone",

  // Optional: serve the API from the same origin as the web app (no CORS, one
  // public port). Set API_PROXY_TARGET=http://host:8000 and
  // NEXT_PUBLIC_API_URL=/api at build time. Unset in normal deployments.
  async rewrites() {
    const target = process.env.API_PROXY_TARGET;

    return target ? [{ source: "/api/:path*", destination: `${target}/api/:path*` }] : [];
  },
};

export default nextConfig;
