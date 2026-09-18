import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex flex-1 items-center justify-center bg-slate-950 px-5 py-24">
      <div className="max-w-xl text-center">
        <p className="text-sm font-semibold uppercase tracking-widest text-brand-400">
          404
        </p>
        <h1 className="mt-3 text-4xl font-semibold tracking-tight text-slate-100">
          Page not found
        </h1>
        <p className="mt-4 text-base leading-7 text-slate-400">
          The page you&apos;re looking for doesn&apos;t exist or has been
          moved. Browse our product catalog or contact our team for assistance.
        </p>
        <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
          <Link
            href="/products"
            className="inline-flex items-center justify-center rounded-md bg-brand-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-700"
          >
            Browse Products
          </Link>
          <Link
            href="/contact"
            className="inline-flex items-center justify-center rounded-md border border-slate-700 bg-white/[0.03] px-6 py-3 text-sm font-semibold text-slate-300 transition-colors hover:border-brand-500 hover:text-brand-300"
          >
            Contact Us
          </Link>
        </div>
      </div>
    </div>
  );
}