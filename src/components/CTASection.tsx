import Link from "next/link";

export default function CTASection({
  title,
  description,
  primaryLabel = "Request a Quote",
  primaryHref = "/request-quote",
  secondaryLabel,
  secondaryHref,
}: {
  title: string;
  description: string;
  primaryLabel?: string;
  primaryHref?: string;
  secondaryLabel?: string;
  secondaryHref?: string;
}) {
  return (
    <section className="bg-brand-900">
      <div className="mx-auto flex w-full max-w-7xl flex-col items-start justify-between gap-8 px-5 py-16 sm:px-8 lg:flex-row lg:items-center">
        <div className="max-w-2xl">
          <h2 className="text-3xl font-semibold tracking-tight text-white sm:text-4xl">
            {title}
          </h2>
          <p className="mt-3 text-base leading-7 text-brand-100">{description}</p>
        </div>
        <div className="flex flex-col gap-3 sm:flex-row">
          <Link
            href={primaryHref}
            className="inline-flex items-center justify-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-brand-800 shadow-sm transition-colors hover:bg-brand-50"
          >
            {primaryLabel}
          </Link>
          {secondaryLabel && secondaryHref && (
            <Link
              href={secondaryHref}
              className="inline-flex items-center justify-center rounded-md border border-brand-500 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-brand-800"
            >
              {secondaryLabel}
            </Link>
          )}
        </div>
      </div>
    </section>
  );
}