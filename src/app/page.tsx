import type { Metadata } from "next";
import Link from "next/link";
import DigitalSerenity from "@/components/ui/digital-serenity-animated-landing-page";
import TrustStrip from "@/components/TrustStrip";
import SectionHeading from "@/components/SectionHeading";
import CategoryCard from "@/components/CategoryCard";
import ProductGrid from "@/components/ProductGrid";
import CTASection from "@/components/CTASection";
import { getCategories, getCompany, getFeaturedProducts } from "@/lib/data";
import { DocumentIcon, GlobeIcon, ShieldIcon } from "@/components/icons";

export const metadata: Metadata = {
  title: "B2B Pharmaceutical Exporter from India",
  description:
    "Bliss Pharmex supplies quality-assured generic medicines to international distributors, wholesalers, importers and institutional buyers. Explore our product catalog and request a quotation.",
  alternates: { canonical: "/" },
};

export default function HomePage() {
  const categories = getCategories();
  const featured = getFeaturedProducts();
  const company = getCompany();

  return (
    <>
      <DigitalSerenity />
      <TrustStrip />

      <section className="mx-auto w-full max-w-7xl px-5 py-16 sm:px-8 lg:py-20">
        <div className="grid gap-10 lg:grid-cols-2 lg:items-center">
          <div>
            <SectionHeading
              eyebrow="Who We Are"
              title="A professional pharma partner, not a marketplace"
              description="We are an India-based pharmaceutical exporter helping buyers validate a supplier, inspect verified products and receive a quotation without friction. Every product is backed by complete documentation and export support."
            />
            <div className="mt-8 grid gap-4 sm:grid-cols-2">
              {company.certifications.map((certification) => (
                <div
                  key={certification}
                  className="flex items-center gap-3 rounded-lg border border-slate-800 bg-white/[0.03] px-4 py-3.5"
                >
                  <ShieldIcon className="h-5 w-5 shrink-0 text-brand-400" />
                  <span className="text-sm font-semibold text-slate-200">
                    {certification}
                  </span>
                </div>
              ))}
            </div>
          </div>
          <div className="rounded-lg border border-slate-800 bg-white/[0.03] p-8">
            <h3 className="text-lg font-semibold text-slate-100">
              Why global buyers source with us
            </h3>
            <ul className="mt-5 space-y-4">
              {[
                {
                  icon: DocumentIcon,
                  title: "Registration-ready documentation",
                  text: "CoA, stability data, site master files and market registration dossiers.",
                },
                {
                  icon: GlobeIcon,
                  title: "Flexible export supply",
                  text: "Custom pack formats, labeling and consolidated shipments to your market.",
                },
                {
                  icon: ShieldIcon,
                  title: "Transparent quality process",
                  text: "Batch traceability across WHO-GMP compliant manufacturing units.",
                },
              ].map((item) => (
                <li key={item.title} className="flex gap-3.5">
                  <item.icon className="mt-0.5 h-5 w-5 shrink-0 text-brand-400" />
                  <div>
                    <p className="text-sm font-semibold text-slate-100">
                      {item.title}
                    </p>
                    <p className="mt-1 text-sm leading-6 text-slate-400">
                      {item.text}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      <section className="border-t border-slate-800 bg-slate-950">
        <div className="mx-auto w-full max-w-7xl px-5 py-16 sm:px-8 lg:py-20">
          <SectionHeading
            eyebrow="Browse by Category"
            title="Product categories"
            description="Discover formulations across therapeutic segments, designed for distributor programs, tenders and private-label supply."
          />
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {categories.slice(0, 8).map((category) => (
              <CategoryCard key={category.slug} category={category} />
            ))}
          </div>
        </div>
      </section>

      <section className="border-t border-slate-800 bg-slate-900/20">
        <div className="mx-auto w-full max-w-7xl px-5 py-16 sm:px-8 lg:py-20">
          <div className="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <SectionHeading
              eyebrow="Featured"
              title="High-demand products"
              description="A snapshot of commonly requested formulations. Search the full catalog for your exact requirement."
            />
            <Link
              href="/products"
              className="inline-flex shrink-0 items-center justify-center rounded-md border border-brand-500 px-5 py-2.5 text-sm font-semibold text-brand-400 transition-colors hover:bg-brand-500/10"
            >
              View all products
            </Link>
          </div>
          <div className="mt-10">
            <ProductGrid products={featured} />
          </div>
        </div>
      </section>

      <CTASection
        title="Need a customized quotation?"
        description="Tell us your target markets, pack preferences and quantities. Our exports team responds with a detailed quotation within one business day."
        primaryLabel="Request a Quote"
        primaryHref="/request-quote"
        secondaryLabel="Contact Sales"
        secondaryHref="/contact"
      />
    </>
  );
}