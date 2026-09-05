import Image from "next/image";
import Link from "next/link";
import { getCompany } from "@/lib/data";

export default function Hero() {
  const company = getCompany();

  return (
    <section className="relative overflow-hidden bg-slate-50">
      <div className="mx-auto grid w-full max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-2 lg:py-24">
        <div>
          <p className="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-brand-50 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-widest text-brand-800">
            B2B Pharmaceutical Exporter &middot; India
          </p>
          <h1 className="mt-6 text-4xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-5xl">
            Quality-assured medicines, supplied to buyers worldwide
          </h1>
          <p className="mt-5 max-w-xl text-lg leading-8 text-slate-600">
            {company.name} helps pharmaceutical distributors, wholesalers,
            importers and institutional buyers source verified generic
            medicines across 30+ markets — backed by complete documentation and
            dedicated RFQ support.
          </p>
          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <Link
              href="/products"
              className="inline-flex items-center justify-center rounded-md bg-brand-700 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-brand-800"
            >
              Explore Products
            </Link>
            <Link
              href="/request-quote"
              className="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-800 shadow-sm transition-colors hover:border-brand-300 hover:text-brand-700"
            >
              Request a Quote
            </Link>
          </div>
          <dl className="mt-10 grid grid-cols-3 gap-6 border-t border-slate-200 pt-8">
            {[
              { value: "500+", label: "Formulations" },
              { value: "30+", label: "Export markets" },
              { value: "12+", label: "Years exporting" },
            ].map((stat) => (
              <div key={stat.label}>
                <dt className="order-2 mt-1 text-sm text-slate-500">
                  {stat.label}
                </dt>
                <dd className="order-1 text-2xl font-semibold tracking-tight text-brand-800">
                  {stat.value}
                </dd>
              </div>
            ))}
          </dl>
        </div>

        <div className="relative hidden aspect-[4/3] overflow-hidden rounded-lg shadow-lg ring-1 ring-slate-200 lg:block">
          <Image
            src="/images/company/facility.svg"
            alt="Bliss Lifesciences manufacturing facility"
            fill
            sizes="(min-width: 1024px) 50vw, 100vw"
            priority
            className="object-cover"
          />
        </div>
      </div>
    </section>
  );
}