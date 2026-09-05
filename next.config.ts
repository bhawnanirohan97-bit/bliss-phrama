import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "**.imimg.com",
        pathname: "**",
      },
    ],
  },
};

export default nextConfig;
