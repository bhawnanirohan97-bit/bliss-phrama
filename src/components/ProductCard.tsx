import Image from "next/image";
import Link from "next/link";
import { getCategoryBySlug, getCompany } from "@/lib/data";
import type { Product } from "@/lib/types";
import { WhatsAppIcon } from "@/components/icons";

function whatsappUrl(product: Product): string {
  const company = getCompany();
  const message = `Hello ${company.name}, I am interested in ${product.name} (${product.strength}). Please share availability and a quotation.`;
  return `https://wa.me/${company.whatsappHref}?text=${encodeURIComponent(
    message
  )}`;
}

export default function ProductCard({ product }: { product: Product }) {
  const category = getCategoryBySlug(product.categorySlug);

  return (
    <article className="flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white transition-all hover:border-brand-300 hover:shadow-md">
      <a
        href={whatsappUrl(product)}
        target="_blank"
        rel="noopener noreferrer"
        className="group block"
        aria-label={`Enquire about ${product.name} on WhatsApp`}
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
              <dt className="text-slate-500">Price</dt>
              <dd className="font-medium text-slate-700">{product.price}</dd>
            </div>
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
          <span className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors group-hover:bg-green-700">
            <WhatsAppIcon className="h-4 w-4" />
            Enquire on WhatsApp
          </span>
        </div>
      </a>
      <div className="border-t border-slate-100 p-5 pt-4 text-center">
        <Link
          href={`/products/${product.slug}`}
          className="text-sm font-semibold text-brand-700 transition-colors hover:text-brand-800"
        >
          View full details
        </Link>
      </div>
    </article>
  );
}