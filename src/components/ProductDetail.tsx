import Image from "next/image";
import Link from "next/link";
import type { Category, Product } from "@/lib/types";
import { getCompany } from "@/lib/data";
import ProductGrid from "@/components/ProductGrid";
import {
  CheckIcon,
  DocumentIcon,
  GlobeIcon,
  MailIcon,
  WhatsAppIcon,
} from "@/components/icons";

export default function ProductDetail({
  product,
  category,
  relatedProducts,
}: {
  product: Product;
  category: Category | undefined;
  relatedProducts: Product[];
}) {
  const company = getCompany();

  const specs = [
    { label: "Product", value: product.name },
    { label: "Category", value: category?.name ?? product.categorySlug },
    { label: "Dosage form", value: product.dosageForm },
    { label: "Strength", value: product.strength },
    { label: "Packaging", value: product.packaging },
    { label: "MOQ", value: product.moq },
  ];

  return (
    <div className="bg-slate-950">
      <div className="border-b border-slate-800 bg-slate-900/40">
        <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
          <div className="grid gap-10 lg:grid-cols-2">
            <div className="relative aspect-[4/3] w-full overflow-hidden rounded-lg border border-slate-800 bg-slate-900">
              <Image
                src={product.image}
                alt={product.name}
                fill
                sizes="(min-width: 1024px) 50vw, 100vw"
                priority
                className="object-cover"
              />
            </div>

            <div>
              <p className="text-sm font-semibold uppercase tracking-widest text-brand-400">
                {category?.name}
              </p>
              <h1 className="mt-2 text-3xl font-semibold tracking-tight text-slate-100 sm:text-4xl">
                {product.name}
              </h1>
              <p className="mt-4 text-base leading-7 text-slate-400">
                {product.description}
              </p>

              <dl className="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                {specs.map((spec) => (
                  <div
                    key={spec.label}
                    className="border-b border-slate-800 pb-3"
                  >
                    <dt className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                      {spec.label}
                    </dt>
                    <dd className="mt-1 text-sm font-medium text-slate-100">
                      {spec.value}
                    </dd>
                  </div>
                ))}
              </dl>

              <div className="mt-8 flex flex-col gap-3">
                <a
                  href={`https://wa.me/${company.whatsappHref}?text=${encodeURIComponent(
                    `Hello ${company.name}, I am interested in ${product.name} (${product.strength}). Please share availability and a quotation.`
                  )}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center justify-center gap-2 rounded-md bg-green-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-700"
                >
                  <WhatsAppIcon className="h-4 w-4" />
                  Enquire on WhatsApp
                </a>
                <div className="flex flex-col gap-3 sm:flex-row">
                  <Link
                    href={`/request-quote?product=${encodeURIComponent(
                      product.slug
                    )}`}
                    className="inline-flex items-center justify-center rounded-md bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700"
                  >
                    Request a Detailed Quote
                  </Link>
                  <a
                    href={`mailto:${company.email}?subject=${encodeURIComponent(
                      `RFQ: ${product.name}`
                    )}`}
                    className="inline-flex items-center justify-center gap-2 rounded-md border border-slate-700 bg-white/[0.03] px-6 py-3.5 text-sm font-semibold text-slate-300 transition-colors hover:border-brand-500 hover:text-brand-300"
                  >
                    <MailIcon className="h-4 w-4" />
                    Email
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="mx-auto w-full max-w-7xl px-5 py-14 sm:px-8">
        <div className="grid gap-10 lg:grid-cols-3">
          <div className="lg:col-span-2">
            <h2 className="text-2xl font-semibold tracking-tight text-slate-100">
              About this product
            </h2>
            <p className="mt-4 text-base leading-7 text-slate-400">
              This formulation is manufactured in WHO-GMP compliant facilities
              and supplied with certificates of analysis, stability data and
              registration documentation on request. Pack sizes and labeling
              can be customized to your market requirements.
            </p>

            <h3 className="mt-8 text-lg font-semibold text-slate-100">
              Included with every supply
            </h3>
            <ul className="mt-4 grid gap-3 sm:grid-cols-2">
              {[
                "Certificate of Analysis (CoA)",
                "Batch & stability documentation",
                "Registration dossier support",
                "Custom pack labeling options",
                "Pharma-grade export packaging",
                "Consolidated shipment support",
              ].map((item) => (
                <li
                  key={item}
                  className="flex items-center gap-2.5 rounded-md border border-slate-800 bg-white/[0.03] px-4 py-3 text-sm font-medium text-slate-300"
                >
                  <CheckIcon className="h-4 w-4 shrink-0 text-green-500" />
                  {item}
                </li>
              ))}
            </ul>
          </div>

          <aside className="h-fit rounded-lg border border-slate-800 bg-white/[0.03] p-6">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
              Export &amp; compliance
            </h3>
            <ul className="mt-4 space-y-4 text-sm text-slate-400">
              <li className="flex gap-3">
                <ShieldChip />
                <span>
                  Supplied from WHO-GMP compliant manufacturing units.
                </span>
              </li>
              <li className="flex gap-3">
                <GlobeIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-400" />
                <span>
                  Documented support for registration in your destination
                  market.
                </span>
              </li>
              <li className="flex gap-3">
                <DocumentIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-400" />
                <span>
                  Compliance documentation provided after quality evaluation of
                  your order.
                </span>
              </li>
            </ul>
          </aside>
        </div>

        <div className="mt-16">
          <h2 className="text-2xl font-semibold tracking-tight text-slate-100">
            Related products
          </h2>
          <div className="mt-6">
            <ProductGrid products={relatedProducts} />
          </div>
        </div>
      </div>
    </div>
  );
}

function ShieldChip() {
  return (
    <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        strokeWidth={1.8}
        className="h-4 w-4 text-brand-400"
        aria-hidden
      >
        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" />
        <path d="m9 12 2 2 4-4" />
      </svg>
    </span>
  );
}