import type { Metadata } from "next";
import Breadcrumbs from "@/components/Breadcrumbs";
import QuoteForm from "@/components/QuoteForm";
import { getProducts } from "@/lib/data";
import { CheckIcon, DocumentIcon, GlobeIcon } from "@/components/icons";

export const metadata: Metadata = {
  title: "Request a Quote",
  description:
    "Submit a quotation request for pharmaceutical products for export. Share your products, quantity and target market for a detailed quotation.",
  alternates: { canonical: "/request-quote" },
};

export default async function RequestQuotePage({
  searchParams,
}: PageProps<"/request-quote">) {
  const params = await searchParams;
  const initialProductSlug =
    typeof params.product === "string" ? params.product : undefined;
  const products = getProducts();

  return (
    <div className="bg-slate-950">
      <div className="border-b border-slate-800 bg-slate-900/40">
        <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
          <Breadcrumbs
            items={[
              { label: "Home", href: "/" },
              { label: "Request a Quote" },
            ]}
          />
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-100">
            Request a Quote
          </h1>
          <p className="mt-3 max-w-2xl text-base leading-7 text-slate-400">
            Share your requirement and our exports team will respond with
            product availability, pack options, MOQs and pricing within one
            business day.
          </p>
        </div>
      </div>

      <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-8">
        <div className="grid gap-10 lg:grid-cols-3">
          <div className="rounded-lg border border-slate-800 bg-white/[0.03] p-6 sm:p-8 lg:col-span-2">
            <h2 className="text-lg font-semibold text-slate-100">
              Tell us what you need
            </h2>
            <div className="mt-6">
              <QuoteForm
                products={products}
                initialProductSlug={initialProductSlug}
              />
            </div>
          </div>

          <aside className="space-y-4">
            <div className="rounded-lg border border-slate-800 bg-white/[0.03] p-6">
              <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
                What happens next
              </h2>
              <ol className="mt-4 space-y-4 text-sm text-slate-400">
                {[
                  "We review product availability and registration status.",
                  "You receive a detailed quotation with pricing and pack options.",
                  "Buyer evaluation, sample and documentation support.",
                  "Confirmed orders ship with full export documentation.",
                ].map((step, index) => (
                  <li key={step} className="flex gap-3">
                    <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-500/15 text-xs font-bold text-brand-400">
                      {index + 1}
                    </span>
                    <p className="leading-6">{step}</p>
                  </li>
                ))}
              </ol>
            </div>

            <div className="rounded-lg border border-slate-800 bg-white/[0.03] p-6">
              <h2 className="text-sm font-semibold uppercase tracking-wider text-slate-500">
                Quotation checklist
              </h2>
              <ul className="mt-4 space-y-3 text-sm text-slate-400">
                <li className="flex gap-2.5">
                  <CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                  Product names / generic names
                </li>
                <li className="flex gap-2.5">
                  <CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-green-500" />
                  Strength, dosage form & pack size
                </li>
                <li className="flex gap-2.5">
                  <DocumentIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-400" />
                  Required documentation (CoA, dossier, registration)
                </li>
                <li className="flex gap-2.5">
                  <GlobeIcon className="mt-0.5 h-4 w-4 shrink-0 text-brand-400" />
                  Destination country & quantities
                </li>
              </ul>
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}