import type { Metadata } from "next";
import Breadcrumbs from "@/components/Breadcrumbs";
import ProductBrowser from "@/components/ProductBrowser";
import { getCategories, getProducts } from "@/lib/data";

export const metadata: Metadata = {
  title: "Products",
  description:
    "Search and filter pharmaceutical products for export: antibiotics, cardiovascular, antidiabetics, respiratory, gastrointestinal, dermatological and nutritional categories.",
  alternates: { canonical: "/products" },
};

export default function ProductsPage() {
  const products = getProducts();
  const categories = getCategories();

  return (
    <div className="bg-slate-50">
      <div className="border-b border-slate-200 bg-white">
        <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
          <Breadcrumbs
            items={[{ label: "Home", href: "/" }, { label: "Products" }]}
          />
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900">
            Products
          </h1>
          <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600">
            Search our pharmaceutical catalog and request a quotation for any
            formulation. All products are supplied with complete documentation
            and export support.
          </p>
        </div>
      </div>

      <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
        <ProductBrowser products={products} categories={categories} />
      </div>
    </div>
  );
}