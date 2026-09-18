"use client";

import { useMemo, useState } from "react";
import type { Category, Product } from "@/lib/types";
import ProductGrid from "@/components/ProductGrid";
import { SearchIcon } from "@/components/icons";

const inputClass =
  "rounded-md border border-slate-700 bg-slate-900 py-2.5 text-sm text-slate-100 placeholder:text-slate-500 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20";

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
      <div className="flex flex-col gap-4 rounded-lg border border-slate-800 bg-white/[0.03] p-5 lg:flex-row lg:items-end lg:justify-between">
        <div className="flex flex-1 flex-col gap-4 lg:flex-row lg:items-center">
          <label className="relative flex-1">
            <span className="sr-only">Search products</span>
            <SearchIcon className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" />
            <input
              type="search"
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search by product name, strength or category…"
              className={`w-full pl-11 pr-4 ${inputClass}`}
            />
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-400">
            <span className="shrink-0 font-medium">Category</span>
            <select
              value={categorySlug}
              onChange={(event) => setCategorySlug(event.target.value)}
              className={`pl-3 pr-8 ${inputClass}`}
            >
              <option value="all">All categories</option>
              {categories.map((category) => (
                <option key={category.slug} value={category.slug}>
                  {category.name}
                </option>
              ))}
            </select>
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-400">
            <span className="shrink-0 font-medium">Sort</span>
            <select
              value={sort}
              onChange={(event) =>
                setSort(event.target.value as "name" | "category")
              }
              className={`pl-3 pr-8 ${inputClass}`}
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
            className="self-start text-sm font-semibold text-brand-400 hover:text-brand-300 lg:self-auto"
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