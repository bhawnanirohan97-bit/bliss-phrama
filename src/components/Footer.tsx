import Link from "next/link";
import { getCategories, getCompany } from "@/lib/data";
import {
  ClockIcon,
  GlobeIcon,
  MailIcon,
  MapPinIcon,
  PhoneIcon,
} from "@/components/icons";

const LEGAL_LINKS = [
  { href: "/privacy-policy", label: "Privacy Policy" },
  { href: "/terms-and-conditions", label: "Terms & Conditions" },
  { href: "/disclaimer", label: "Disclaimer" },
];

export default function Footer() {
  const company = getCompany();
  const categories = getCategories();

  return (
    <footer className="border-t border-slate-800 bg-slate-950 text-slate-300">
      <div className="mx-auto w-full max-w-7xl px-5 py-14 sm:px-8">
        <div className="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <Link href="/" className="flex items-center gap-3">
              <img
                src="/images/company/logo.png"
                alt={company.name}
                style={{ height: "40px", width: "auto", borderRadius: "6px", background: "white", padding: "4px" }}
              />
              <span className="text-base font-semibold tracking-tight text-white">
                {company.name}
              </span>
            </Link>
            <p className="mt-4 max-w-xs text-sm leading-6 text-slate-400">
              {company.tagline}. Supplying quality-assured generic
              pharmaceuticals to distributors, wholesalers and institutional
              buyers worldwide.
            </p>
          </div>

          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-white">
              Quick Links
            </h3>
            <ul className="mt-4 space-y-2.5 text-sm">
              {[
                { href: "/", label: "Home" },
                { href: "/products", label: "Products" },
                { href: "/contact", label: "Contact Us" },
                { href: "/request-quote", label: "Request a Quote" },
              ].map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    className="text-slate-400 transition-colors hover:text-white"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-white">
              Product Categories
            </h3>
            <ul className="mt-4 space-y-2.5 text-sm">
              {categories.slice(0, 6).map((category) => (
                <li key={category.slug}>
                  <Link
                    href={`/products/${category.slug}`}
                    className="text-slate-400 transition-colors hover:text-white"
                  >
                    {category.name}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-white">
              Contact
            </h3>
            <ul className="mt-4 space-y-3 text-sm text-slate-400">
              <li className="flex gap-2.5">
                <MapPinIcon className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <span>
                  {company.addressLine1}, {company.city}, {company.state}{" "}
                  {company.postalCode}, {company.country}
                </span>
              </li>
              <li className="flex gap-2.5">
                <PhoneIcon className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <a
                  href={`tel:+${company.phoneHref}`}
                  className="transition-colors hover:text-white"
                >
                  {company.phone}
                </a>
              </li>
              <li className="flex gap-2.5">
                <MailIcon className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <a
                  href={`mailto:${company.email}`}
                  className="break-all transition-colors hover:text-white"
                >
                  {company.email}
                </a>
              </li>
              <li className="flex gap-2.5">
                <ClockIcon className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <span>{company.workingHours}</span>
              </li>
              <li className="flex gap-2.5">
                <GlobeIcon className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                <span>
                  Exporting to:{" "}
                  {company.exportMarkets.slice(0, 4).join(", ")}
                </span>
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-12 flex flex-col items-start justify-between gap-4 border-t border-slate-800 pt-6 text-sm text-slate-500 sm:flex-row sm:items-center">
          <p>
            &copy; {new Date().getFullYear()} {company.legalName}. All rights
            reserved.
          </p>
          <ul className="flex flex-wrap gap-x-6 gap-y-2">
            {LEGAL_LINKS.map((link) => (
              <li key={link.href}>
                <Link
                  href={link.href}
                  className="transition-colors hover:text-white"
                >
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </footer>
  );
}