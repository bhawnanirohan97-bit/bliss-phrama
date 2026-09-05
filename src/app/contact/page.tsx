import type { Metadata } from "next";
import Breadcrumbs from "@/components/Breadcrumbs";
import ContactForm from "@/components/ContactForm";
import { getCompany } from "@/lib/data";
import {
  ClockIcon,
  GlobeIcon,
  MailIcon,
  MapPinIcon,
  PhoneIcon,
  WhatsAppIcon,
} from "@/components/icons";

export const metadata: Metadata = {
  title: "Contact Us",
  description:
    "Contact Bliss Pharmex for pharmaceutical sourcing, quotations and export inquiries. Reach us by phone, email, WhatsApp or the contact form.",
  alternates: { canonical: "/contact" },
};

export default function ContactPage() {
  const company = getCompany();

  return (
    <div className="bg-slate-50">
      <div className="border-b border-slate-200 bg-white">
        <div className="mx-auto w-full max-w-7xl px-5 py-10 sm:px-8">
          <Breadcrumbs
            items={[{ label: "Home", href: "/" }, { label: "Contact" }]}
          />
          <h1 className="mt-4 text-4xl font-semibold tracking-tight text-slate-900">
            Contact Us
          </h1>
          <p className="mt-3 max-w-2xl text-base leading-7 text-slate-600">
            Reach our exports and sales team directly. We respond to inquiries
            within one business day.
          </p>
        </div>
      </div>

      <div className="mx-auto w-full max-w-7xl px-5 py-12 sm:px-8">
        <div className="grid gap-10 lg:grid-cols-5">
          <div className="space-y-4 lg:col-span-2">
            <div className="rounded-lg border border-slate-200 bg-white p-6">
              <h2 className="text-lg font-semibold text-slate-900">
                Sales & exports office
              </h2>
              <ul className="mt-4 space-y-3.5 text-sm text-slate-600">
                <li className="flex gap-3">
                  <MapPinIcon className="mt-0.5 h-5 w-5 shrink-0 text-brand-700" />
                  <span>
                    {company.addressLine1}, {company.city}, {company.state}{" "}
                    {company.postalCode}, {company.country}
                  </span>
                </li>
                <li className="flex gap-3">
                  <PhoneIcon className="mt-0.5 h-5 w-5 shrink-0 text-brand-700" />
                  <a
                    href={`tel:+${company.phoneHref}`}
                    className="font-medium text-slate-800 hover:text-brand-700"
                  >
                    {company.phone}
                  </a>
                </li>
                <li className="flex gap-3">
                  <MailIcon className="mt-0.5 h-5 w-5 shrink-0 text-brand-700" />
                  <a
                    href={`mailto:${company.email}`}
                    className="font-medium text-slate-800 hover:text-brand-700"
                  >
                    {company.email}
                  </a>
                </li>
                <li className="flex gap-3">
                  <ClockIcon className="mt-0.5 h-5 w-5 shrink-0 text-brand-700" />
                  <span>{company.workingHours}</span>
                </li>
              </ul>
            </div>

            <a
              href={`https://wa.me/${company.whatsappHref}`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center justify-between gap-3 rounded-lg bg-green-600 p-6 text-white transition-colors hover:bg-green-700"
            >
              <div>
                <p className="text-base font-semibold">Chat on WhatsApp</p>
                <p className="mt-1 text-sm text-green-100">
                  {company.whatsapp}
                </p>
              </div>
              <WhatsAppIcon className="h-8 w-8 shrink-0" />
            </a>

            <div className="rounded-lg border border-slate-200 bg-white p-6">
              <h2 className="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-slate-500">
                <GlobeIcon className="h-4 w-4" />
                Export markets
              </h2>
              <div className="mt-4 flex flex-wrap gap-2">
                {company.exportMarkets.map((market) => (
                  <span
                    key={market}
                    className="rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-800"
                  >
                    {market}
                  </span>
                ))}
              </div>
            </div>
          </div>

          <div className="rounded-lg border border-slate-200 bg-white p-6 sm:p-8 lg:col-span-3">
            <h2 className="text-lg font-semibold text-slate-900">
              Send us a message
            </h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">
              Fill in the form and the right person from our team will get back
              to you.
            </p>
            <div className="mt-6">
              <ContactForm />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}