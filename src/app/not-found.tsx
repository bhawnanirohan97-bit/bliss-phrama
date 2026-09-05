import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex flex-1 items-center justify-center bg-slate-50 px-5 py-24">
      <div className="max-w-xl text-center">
        <p className="text-sm font-semibold uppercase tracking-widest text-brand-700">
          404
        </p>
        <h1 className="mt-3 text-4xl font-semibold tracking-tight text-slate-900">
          Page not found
        </h1>
        <p className="mt-4 text-base leading-7 text-slate-600">
          The page you&apos;re looking for doesn&apos;t exist or has been
          moved. Browse our product catalog or contact our team for assistance.
        </p>
        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <Link
            href="/products"
            className="inline-flex items-center justify-center rounded-md bg-brand-700 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-800"
          >
            Browse Products
          </Link>
          <Link
            href="/contact"
            className="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition-colors hover:border-brand-300 hover:text-brand-700"
          >
            Contact Us
          </Link>
        </div>
      </div>
    </div>
  );
}