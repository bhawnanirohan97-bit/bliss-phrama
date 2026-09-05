import type { MetadataRoute } from "next";
import { SITE } from "@/lib/site";
import { getCategories, getProducts } from "@/lib/data";

export default function sitemap(): MetadataRoute.Sitemap {
  const staticPages = [
    { path: "", priority: 1, changeFrequency: "weekly" },
    { path: "products", priority: 0.9, changeFrequency: "weekly" },
    { path: "contact", priority: 0.7, changeFrequency: "monthly" },
    { path: "request-quote", priority: 0.8, changeFrequency: "monthly" },
    { path: "privacy-policy", priority: 0.3, changeFrequency: "yearly" },
    { path: "terms-and-conditions", priority: 0.3, changeFrequency: "yearly" },
    { path: "disclaimer", priority: 0.3, changeFrequency: "yearly" },
  ] as const;

  const categoryEntries = getCategories().map((category) => ({
    url: `${SITE.url}/products/${category.slug}`,
    lastModified: new Date(),
    changeFrequency: "weekly" as const,
    priority: 0.8,
  }));

  const productEntries = getProducts().map((product) => ({
    url: `${SITE.url}/products/${product.slug}`,
    lastModified: new Date(),
    changeFrequency: "weekly" as const,
    priority: 0.7,
  }));

  return [
    ...staticPages.map((page) => ({
      url: `${SITE.url}/${page.path}`,
      lastModified: new Date(),
      changeFrequency: page.changeFrequency,
      priority: page.priority,
    })),
    ...categoryEntries,
    ...productEntries,
  ];
}