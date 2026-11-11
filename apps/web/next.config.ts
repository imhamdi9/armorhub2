import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Self-contained server output, used by the production Docker image.
  output: "standalone",
};

export default nextConfig;
