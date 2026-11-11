import type { MetadataRoute } from "next";

const site = () => (process.env.SITE_URL ?? "").replace(/\/$/, "");

export default function robots(): MetadataRoute.Robots {
  return {
    rules: { userAgent: "*", allow: ["/", "/s/"], disallow: ["/dashboard", "/login", "/register", "/forgot-password", "/reset-password", "/api/"] },
    ...(site() ? { sitemap: `${site()}/sitemap.xml` } : {}),
  };
}
