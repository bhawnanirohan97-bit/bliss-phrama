import Image from "next/image";
import Link from "next/link";
import { getCategoryBySlug } from "@/lib/data";
import type { Product } from "@/lib/types";

export default function ProductCard({ product }: { product: Product }) {
  const category = getCategoryBySlug(product.categorySlug);

  return (
    <article className="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white transition-all hover:border-brand-300 hover:shadow-md">
      <Link
        href={`/products/${product.slug}`}
        className="group block"
        aria-label={`View ${product.name}`}
      >
        <div className="relative aspect-[4/3] w-full overflow-hidden bg-slate-50">
          <Image
            src={product.image}
            alt={product.name}
            fill
            sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
            className="object-cover transition-transform duration-300 group-hover:scale-[1.02]"
          />
        </div>
        <div className="flex flex-1 flex-col p-5">
          <p className="text-xs font-semibold uppercase tracking-widest text-brand-700">
            {category?.name}
          </p>
          <h3 className="mt-1.5 text-base font-semibold leading-snug text-slate-900">
            {product.name}
          </h3>
          <dl className="mt-3 space-y-1.5 text-sm text-slate-600">
            <div className="flex justify-between gap-3">
              <dt className="text-slate-500">Strength</dt>
              <dd className="font-medium text-slate-700">{product.strength}</dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-slate-500">Packaging</dt>
              <dd className="font-medium text-slate-700">
                {product.packaging}
              </dd>
            </div>
            <div className="flex justify-between gap-3">
              <dt className="text-slate-500">MOQ</dt>
              <dd className="font-medium text-slate-700">{product.moq}</dd>
            </div>
          </dl>
        </div>
      </Link>
      <div className="border-t border-slate-100 p-5 pt-4">
        <Link
          href={`/request-quote?product=${encodeURIComponent(product.slug)}`}
          className="inline-flex w-full items-center justify-center rounded-md border border-brand-700 px-4 py-2.5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50"
        >
          Request Quote
        </Link>
      </div>
    </article>
  );
}