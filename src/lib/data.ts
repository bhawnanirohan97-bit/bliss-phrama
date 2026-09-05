import categoriesJson from "@/data/categories.json";
import productsJson from "@/data/products.json";
import companyJson from "@/data/company.json";
import type { Category, Company, Product } from "@/lib/types";

const categories = categoriesJson as Category[];
const products = productsJson as Product[];
const company = companyJson as Company;

export function getCompany(): Company {
  return company;
}

export function getCategories(): Category[] {
  return categories;
}

export function getProducts(): Product[] {
  return products;
}

export function getCategoryBySlug(slug: string): Category | undefined {
  return categories.find((category) => category.slug === slug);
}

export function getProductBySlug(slug: string): Product | undefined {
  return products.find((product) => product.slug === slug);
}

export function getProductsByCategory(categorySlug: string): Product[] {
  return products.filter((product) => product.categorySlug === categorySlug);
}

export function getFeaturedProducts(): Product[] {
  return products.filter((product) => product.featured);
}

export function getRelatedProducts(product: Product, limit = 4): Product[] {
  const sameCategory = getProductsByCategory(product.categorySlug).filter(
    (item) => item.slug !== product.slug
  );
  const fillers = products
    .filter((item) => item.categorySlug !== product.categorySlug)
    .sort(() => 0.5 - Math.random());

  return [...sameCategory, ...fillers].slice(0, limit);
}

export function searchProducts(query: string, categorySlug?: string): Product[] {
  const normalized = query.trim().toLowerCase();
  return products.filter((product) => {
    const matchesCategory =
      !categorySlug || product.categorySlug === categorySlug;
    if (!matchesCategory) return false;
    if (!normalized) return true;
    const category = getCategoryBySlug(product.categorySlug);
    const haystack = [
      product.name,
      product.strength,
      product.dosageForm,
      product.categorySlug,
      category?.name ?? "",
      ...product.tags,
    ]
      .join(" ")
      .toLowerCase();
    return haystack.includes(normalized);
  });
}

export function formatSlugParts(slug: string): string {
  return slug
    .split("-")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}