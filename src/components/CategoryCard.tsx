import Image from "next/image";
import Link from "next/link";
import { getProductsByCategory } from "@/lib/data";
import type { Category } from "@/lib/types";
import { ChevronRightIcon } from "@/components/icons";

export default function CategoryCard({ category }: { category: Category }) {
  const count = getProductsByCategory(category.slug).length;

  return (
    <Link
      href={`/products/${category.slug}`}
      className="group flex flex-col overflow-hidden rounded-lg border border-slate-800 bg-white/[0.03] transition-all hover:border-brand-500 hover:shadow-lg hover:shadow-brand-950"
    >
      <div className="relative aspect-[4/3] w-full overflow-hidden bg-slate-900">
        <Image
          src={category.image}
          alt={`${category.name} category`}
          fill
          sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 100vw"
          className="object-cover transition-transform duration-300 group-hover:scale-[1.02]"
        />
      </div>
      <div className="flex flex-1 flex-col p-5">
        <h3 className="text-base font-semibold text-slate-100">
          {category.name}
        </h3>
        <p className="mt-2 flex-1 text-sm leading-6 text-slate-400">
          {category.description}
        </p>
        <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-400">
          {count} {count === 1 ? "product" : "products"}
          <ChevronRightIcon className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
        </span>
      </div>
    </Link>
  );
}