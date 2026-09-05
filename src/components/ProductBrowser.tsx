"use client";

import { useMemo, useState } from "react";
import type { Category, Product } from "@/lib/types";
import ProductGrid from "@/components/ProductGrid";
import { SearchIcon } from "@/components/icons";

export default function ProductBrowser({
  products,
  categories,
}: {
  products: Product[];
  categories: Category[];
}) {
  const [query, setQuery] = useState("");
  const [categorySlug, setCategorySlug] = useState<string>("all");
  const [sort, setSort] = useState<"name" | "category">("name");

  const filtered = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    const result = products.filter((product) => {
      const inCategory =
        categorySlug === "all" || product.categorySlug === categorySlug;
      if (!inCategory) return false;
      if (!normalized) return true;
      const category = categories.find(
        (item) => item.slug === product.categorySlug
      );
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

    return [...result].sort((a, b) =>
      sort === "category"
        ? a.categorySlug.localeCompare(b.categorySlug) ||
          a.name.localeCompare(b.name)
        : a.name.localeCompare(b.name)
    );
  }, [products, categories, query, categorySlug, sort]);

  return (
    <div>
      <div className="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <div className="flex flex-1 flex-col gap-4 lg:flex-row lg:items-center">
          <label className="relative flex-1">
            <span className="sr-only">Search products</span>
            <SearchIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search by product name, strength or category…"
              className="w-full rounded-md border border-slate-300 bg-white py-2.5 pl-11 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
            />
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-600">
            <span className="shrink-0 font-medium">Category</span>
            <select
              value={categorySlug}
              onChange={(event) => setCategorySlug(event.target.value)}
              className="rounded-md border border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
            >
              <option value="all">All categories</option>
              {categories.map((category) => (
                <option key={category.slug} value={category.slug}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-600">
            <span className="shrink-0 font-medium">Sort</span>
            <select
              value={sort}
              onChange={(event) =>
                setSort(event.target.value as "name" | "category")
              }
              className="rounded-md border border-slate-300 bg-white py-2.5 pl-3 pr-8 text-sm text-slate-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"
            >
              <option value="name">Name A–Z</option>
              <option value="category">Category</option>
            </select>
          </label>
        </div>
        {query || categorySlug !== "all" ? (
          <button
            type="button"
            onClick={() => {
              setQuery("");
              setCategorySlug("all");
            }}
            className="self-start text-sm font-semibold text-brand-700 hover:text-brand-800 lg:self-auto"
          >
            Clear filters
          </button>
        ) : null}
      </div>

      <div className="mt-6">
        <p className="mb-4 text-sm text-slate-500">
          Showing {filtered.length} of {products.length} products
        </p>
        <ProductGrid products={filtered} />
      </div>
    </div>
  );
}