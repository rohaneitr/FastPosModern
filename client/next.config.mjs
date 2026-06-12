import withPWAInit from "@ducanh2912/next-pwa";

const withPWA = withPWAInit({
  dest: "public",
  disable: process.env.NODE_ENV === "development",
  fallbacks: {
    document: "/~offline",
  },
});

/** @type {import('next').NextConfig} */
const nextConfig = {
  turbopack: {},
  async rewrites() {
    return [
      {
        source: '/api/v1/:path*',
        destination: 'http://backend:8000/api/v1/:path*',
      },
      {
        source: '/sanctum/csrf-cookie',
        destination: 'http://backend:8000/sanctum/csrf-cookie',
      }
    ];
  },
};

export default withPWA(nextConfig);
