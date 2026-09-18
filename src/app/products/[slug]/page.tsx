import type { Metadata } from "next";
import { notFound } from "next/navigation";
import Breadcrumbs from "@/components/Breadcrumbs";
import ProductDetail from "@/components/ProductDetail";
import ProductGrid from "@/components/ProductGrid";
import CTASection from "@/components/CTASection";
import {
  getCategoryBySlug,
  getCategories,
  getProductBySlug,
  getProducts,
  getProductsByCategory,
  getRelatedProducts,
} from "@/lib/data";

export const dynamicParams = false;

export function generateStaticParams() {
  const categorySlugs = getCategories().map((category) => ({
    slug: category.slug,
  }));
  const productSlugs = getProducts().map((product) => ({ slug: product.slug }));
  return [...categorySlugs, ...productSlugs];
}

export async function generateMetadata({
  params,
}: PageProps<"/products/[slug]">): Promise<Metadata> {
  const { slug } = await params;
  const category = getCategoryBySlug(slug);
  const product = getProductBySlug(slug);

  if (category) {
    return {
      title: `${category.name} Products`,
      description: `${category.description} Browse available formulations and request a quotation.`,
      alternates: { canonical: `/products/${category.slug}` },
    };
  }

  if (product) {
    return {
      title: product.name,
      description: `${product.name} — ${product.strength}, ${product.packaging}. ${product.description}`,
      alternates: { canonical: `/products/${product.slug}` },
    };
  }

  return {};
}

export default async function CatalogPage({
  params,
}: PageProps<"/products/[slug]">) {
  const { slug } = await params;

  const category = getCategoryBySlug(slug);
  if (category) {
    const products = getProductsByCategory(category.slug);
    return (
      <div className="bg-slate-950">
        <div className="border-b border-slate-800 bg-slate-900/40">
          <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
            <Breadcrumbs
              items={[
                { label: "Home", href: "/" },
                { label: "Products", href: "/products" },
                { label: category.name },
              ]}
            />
            <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-100">
              {category.name}
            </h1>
            <p className="mt-3 max-w-2xl text-base leading-7 text-slate-400">
              {category.description}
            </p>
            <p className="mt-4 text-sm font-medium text-brand-400">
              {products.length} {products.length === 1 ? "product" : "products"}{" "}
              available
            </p>
          </div>
        </div>
        <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
          <ProductGrid products={products} />
        </div>
        <CTASection
          title={`Need a quotation for ${category.name}?`}
          description="Share your pack preferences, target market and estimated quantities for a detailed quotation."
          secondaryLabel="Browse all products"
          secondaryHref="/products"
        />
      </div>
    );
  }

  const product = getProductBySlug(slug);
  if (product) {
    const productCategory = getCategoryBySlug(product.categorySlug);
    const related = getRelatedProducts(product);
    return (
      <div>
        <div className="mx-auto w-full max-w-7xl px-5 pt-8 sm:px-8">
          <Breadcrumbs
            items={[
              { label: "Home", href: "/" },
              { label: "Products", href: "/products" },
              {
                label: productCategory?.name ?? product.categorySlug,
                href: `/products/${product.categorySlug}`,
              },
              { label: product.name },
            ]}
          />
        </div>
        <ProductDetail
          product={product}
          category={productCategory}
          relatedProducts={related}
        />
      </div>
    );
  }

  notFound();
}