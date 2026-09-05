import type { Product } from "@/lib/types";
import ProductCard from "@/components/ProductCard";

export default function ProductGrid({ products }: { products: Product[] }) {
  if (products.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
        <p className="text-base font-medium text-slate-700">
          No products match your search.
        </p>
        <p className="mt-1 text-sm text-slate-500">
          Try a different keyword or clear the filters.
        </p>
      </div>
    );
  }

  return (
    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}