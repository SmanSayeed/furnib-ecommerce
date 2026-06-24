import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Emit a minimal self-contained server (.next/standalone) for a small,
  // fast-booting production Docker image.
  output: "standalone",
};

export default nextConfig;
